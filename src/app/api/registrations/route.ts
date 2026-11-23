import { NextRequest, NextResponse } from "next/server";
import { and, asc, desc, eq, inArray, isNull, notInArray, sql } from "drizzle-orm";
import { db, schema } from "@/db";
import {
  createSession,
  getCurrentUser,
  hashPassword,
  recordAuditLog,
  verifyPassword,
} from "@/lib/auth";
import { FESTIVAL_EDITION_SLUG } from "@/lib/constants";
import { queueNotification } from "@/lib/notifications";
import {
  generateQrDataUrl,
  generateUniqueRegistrationCode,
  issueCredentialForRegistration,
} from "@/lib/qr";
import { baseRegistrationSchema } from "@/lib/validation";

async function getActiveEdition() {
  const [bySlug] = await db
    .select()
    .from(schema.festivalEditions)
    .where(eq(schema.festivalEditions.slug, FESTIVAL_EDITION_SLUG))
    .limit(1);
  if (bySlug) return bySlug;

  const [active] = await db
    .select()
    .from(schema.festivalEditions)
    .where(eq(schema.festivalEditions.isActive, true))
    .limit(1);
  return active ?? null;
}

export async function GET() {
  try {
    const edition = await getActiveEdition();
    if (!edition) {
      return NextResponse.json(
        { error: "No active festival edition configured." },
        { status: 404 }
      );
    }

    const [categories, disabilityCategories, accommodationTypes, user] =
      await Promise.all([
        db
          .select()
          .from(schema.registrationCategories)
          .where(
            and(
              eq(schema.registrationCategories.editionId, edition.id),
              eq(schema.registrationCategories.isActive, true)
            )
          )
          .orderBy(asc(schema.registrationCategories.displayOrder)),
        db
          .select()
          .from(schema.disabilityCategories)
          .orderBy(asc(schema.disabilityCategories.displayOrder)),
        db
          .select()
          .from(schema.accommodationTypes)
          .where(eq(schema.accommodationTypes.isActive, true))
          .orderBy(asc(schema.accommodationTypes.displayOrder)),
        getCurrentUser(),
      ]);

    if (!user) {
      return NextResponse.json({
        edition,
        categories,
        disabilityCategories,
        accommodationTypes,
        user: null,
        registrations: [],
      });
    }

    const userRegs = await db
      .select()
      .from(schema.registrations)
      .where(
        and(
          eq(schema.registrations.userId, user.id),
          isNull(schema.registrations.deletedAt)
        )
      )
      .orderBy(desc(schema.registrations.createdAt));

    const regIds = userRegs.map((r) => r.id);

    const [creds, volApps, sliApps, delDetails, accommReqs, userCheckins] =
      regIds.length > 0
        ? await Promise.all([
            db
              .select()
              .from(schema.qrCredentials)
              .where(inArray(schema.qrCredentials.registrationId, regIds))
              .orderBy(desc(schema.qrCredentials.issuedAt)),
            db
              .select()
              .from(schema.volunteerApplications)
              .where(inArray(schema.volunteerApplications.registrationId, regIds)),
            db
              .select()
              .from(schema.sliApplications)
              .where(inArray(schema.sliApplications.registrationId, regIds)),
            db
              .select()
              .from(schema.delegateDetails)
              .where(inArray(schema.delegateDetails.registrationId, regIds)),
            db
              .select()
              .from(schema.accommodationRequests)
              .where(inArray(schema.accommodationRequests.registrationId, regIds))
              .orderBy(desc(schema.accommodationRequests.createdAt)),
            db
              .select()
              .from(schema.checkins)
              .where(inArray(schema.checkins.registrationId, regIds))
              .orderBy(desc(schema.checkins.checkedInAt)),
          ])
        : [[], [], [], [], [], []];

    const categoryNameMap = new Map(categories.map((c) => [c.code, c.name]));

    const enrichedRegistrations = await Promise.all(
      userRegs.map(async (reg) => {
        const activeCred = creds.find(
          (c) => c.registrationId === reg.id && c.status === "ACTIVE"
        );
        const qrDataUrl = activeCred
          ? await generateQrDataUrl(activeCred.credentialCode)
          : null;

        return {
          ...reg,
          categoryName: categoryNameMap.get(reg.categoryCode) || reg.categoryCode,
          activeCredential: activeCred
            ? {
                id: activeCred.id,
                credentialCode: activeCred.credentialCode,
                status: activeCred.status,
                issuedAt: activeCred.issuedAt,
                qrDataUrl,
              }
            : null,
          volunteerApplication:
            volApps.find((v) => v.registrationId === reg.id) ?? null,
          sliApplication: sliApps.find((s) => s.registrationId === reg.id) ?? null,
          delegateDetails:
            delDetails.find((d) => d.registrationId === reg.id) ?? null,
          accommodationRequests: accommReqs.filter(
            (a) => a.registrationId === reg.id
          ),
          checkins: userCheckins.filter((c) => c.registrationId === reg.id),
        };
      })
    );

    return NextResponse.json({
      edition,
      categories,
      disabilityCategories,
      accommodationTypes,
      user,
      registrations: enrichedRegistrations,
    });
  } catch (err) {
    console.error("GET /api/registrations error:", err);
    return NextResponse.json(
      { error: "Unable to load registration data." },
      { status: 500 }
    );
  }
}

export async function POST(req: NextRequest) {
  try {
    const body = await req.json().catch(() => null);
    const parsed = baseRegistrationSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        {
          error:
            parsed.error.issues[0]?.message ||
            "Please check the highlighted form fields.",
          issues: parsed.error.issues,
        },
        { status: 400 }
      );
    }

    const data = parsed.data;
    const edition = await getActiveEdition();
    if (!edition) {
      return NextResponse.json(
        { error: "Festival registration is not currently active." },
        { status: 404 }
      );
    }

    const categoryCode = data.categoryCode.toUpperCase();
    const [category] = await db
      .select()
      .from(schema.registrationCategories)
      .where(
        and(
          eq(schema.registrationCategories.editionId, edition.id),
          eq(schema.registrationCategories.code, categoryCode),
          eq(schema.registrationCategories.isActive, true)
        )
      )
      .limit(1);

    if (!category) {
      return NextResponse.json(
        { error: "Selected registration category is not available." },
        { status: 400 }
      );
    }

    const now = new Date();
    if (category.opensAt && category.opensAt > now) {
      return NextResponse.json(
        { error: "Registration for this category has not opened yet." },
        { status: 400 }
      );
    }
    if (category.closesAt && category.closesAt < now) {
      return NextResponse.json(
        { error: "Registration for this category has closed." },
        { status: 400 }
      );
    }

    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
      req.headers.get("x-real-ip") ||
      null;
    const userAgent = req.headers.get("user-agent") || null;

    // Resolve or create single user account (supports multiple registrations under ONE account)
    const currentUser = await getCurrentUser();
    const normalizedEmail = data.email.toLowerCase();
    let userId: string;
    let sessionCreated = false;

    if (currentUser) {
      userId = currentUser.id;
    } else {
      const [existingUser] = await db
        .select()
        .from(schema.users)
        .where(eq(schema.users.email, normalizedEmail))
        .limit(1);

      if (existingUser) {
        if (!existingUser.isActive || existingUser.deletedAt) {
          return NextResponse.json(
            {
              error:
                "This email is associated with a deactivated account. Please contact the festival desk.",
            },
            { status: 403 }
          );
        }
        userId = existingUser.id;

        // If user provided password matching existing account, sign them in seamlessly
        if (data.password && existingUser.passwordHash) {
          const matches = await verifyPassword(data.password, existingUser.passwordHash);
          if (matches && !existingUser.mfaEnabled) {
            await createSession(existingUser.id, {
              ipAddress: ipAddress ?? undefined,
              userAgent: userAgent ?? undefined,
            });
            sessionCreated = true;
          }
        }
      } else {
        const passwordHash = data.password
          ? await hashPassword(data.password)
          : null;

        const [newUser] = await db
          .insert(schema.users)
          .values({
            email: normalizedEmail,
            passwordHash,
            fullName: data.fullName,
            phone: data.phone,
            country: data.country || "India",
            isActive: true,
          })
          .returning();

        let [participantRole] = await db
          .select()
          .from(schema.roles)
          .where(eq(schema.roles.code, "PARTICIPANT"))
          .limit(1);

        if (!participantRole) {
          [participantRole] = await db
            .insert(schema.roles)
            .values({
              code: "PARTICIPANT",
              name: "Participant",
              description: "Festival participant account.",
            })
            .returning();
        }

        await db
          .insert(schema.userRoles)
          .values({ userId: newUser.id, roleId: participantRole.id })
          .onConflictDoNothing();

        userId = newUser.id;

        if (data.password) {
          await createSession(newUser.id, {
            ipAddress: ipAddress ?? undefined,
            userAgent: userAgent ?? undefined,
          });
          sessionCreated = true;
        }
      }
    }

    // Prevent duplicate active registration in the SAME category for this edition
    const [duplicateReg] = await db
      .select()
      .from(schema.registrations)
      .where(
        and(
          eq(schema.registrations.userId, userId),
          eq(schema.registrations.editionId, edition.id),
          eq(schema.registrations.categoryCode, categoryCode),
          isNull(schema.registrations.deletedAt),
          notInArray(schema.registrations.status, [
            "WITHDRAWN",
            "CANCELLED",
            "REJECTED",
          ])
        )
      )
      .limit(1);

    if (duplicateReg) {
      return NextResponse.json(
        {
          error: `You already have an active ${category.name} registration (${duplicateReg.registrationCode}). You can still register for a different role under the same account.`,
          existingRegistrationCode: duplicateReg.registrationCode,
        },
        { status: 409 }
      );
    }

    // Server-side coupon validation & fee calculation
    const feeAmountInr = category.feeAmountInr ?? 0;
    let discountInr = 0;
    let appliedCoupon: typeof schema.couponCodes.$inferSelect | null = null;

    if (data.couponCode && data.couponCode.trim().length > 0) {
      const normalizedCode = data.couponCode.trim().toUpperCase();
      const [coupon] = await db
        .select()
        .from(schema.couponCodes)
        .where(
          and(
            eq(schema.couponCodes.editionId, edition.id),
            eq(schema.couponCodes.code, normalizedCode)
          )
        )
        .limit(1);

      if (!coupon || !coupon.isActive) {
        return NextResponse.json(
          { error: "The coupon code entered is invalid or inactive." },
          { status: 400 }
        );
      }

      if (coupon.validFrom && coupon.validFrom > now) {
        return NextResponse.json(
          { error: "This coupon code is not active yet." },
          { status: 400 }
        );
      }

      if (coupon.validUntil && coupon.validUntil < now) {
        return NextResponse.json(
          { error: "This coupon code has expired." },
          { status: 400 }
        );
      }

      if (coupon.maxUses !== null && coupon.usedCount >= coupon.maxUses) {
        return NextResponse.json(
          { error: "This coupon code has reached its maximum usage limit." },
          { status: 400 }
        );
      }

      const eligible = coupon.eligibleCategories || [];
      if (eligible.length > 0 && !eligible.includes(categoryCode)) {
        return NextResponse.json(
          {
            error: `This coupon code is not eligible for the ${category.name} category.`,
          },
          { status: 400 }
        );
      }

      const userRedemptions = await db
        .select({ id: schema.couponRedemptions.id })
        .from(schema.couponRedemptions)
        .where(
          and(
            eq(schema.couponRedemptions.couponId, coupon.id),
            eq(schema.couponRedemptions.userId, userId)
          )
        );

      if (userRedemptions.length >= coupon.maxUsesPerUser) {
        return NextResponse.json(
          { error: "You have already used this coupon code." },
          { status: 400 }
        );
      }

      if (coupon.discountType === "FULL_WAIVER") {
        discountInr = feeAmountInr;
      } else if (coupon.discountType === "PERCENTAGE") {
        discountInr = Math.min(
          feeAmountInr,
          Math.round((feeAmountInr * coupon.discountValue) / 100)
        );
      } else {
        discountInr = Math.min(feeAmountInr, coupon.discountValue);
      }
      appliedCoupon = coupon;
    }

    const netAmountInr = Math.max(0, feeAmountInr - discountInr);

    // Determine initial registration status & payment status
    let status: "SUBMITTED" | "CONFIRMED" = "CONFIRMED";
    let paymentStatus: "NOT_REQUIRED" | "PENDING" = "NOT_REQUIRED";
    let shouldIssueQr = true;

    if (category.requiresApproval) {
      status = "SUBMITTED";
      paymentStatus = "NOT_REQUIRED";
      shouldIssueQr = false;
    } else if (category.requiresPayment && netAmountInr > 0) {
      status = "SUBMITTED";
      paymentStatus = "PENDING";
      shouldIssueQr = false;
    }

    const registrationCode = await generateUniqueRegistrationCode();

    const [registration] = await db
      .insert(schema.registrations)
      .values({
        registrationCode,
        editionId: edition.id,
        userId,
        categoryId: category.id,
        categoryCode,
        status,
        paymentStatus,
        fullName: data.fullName,
        email: normalizedEmail,
        phone: data.phone,
        country: data.country || "India",
        occupation: data.occupation,
        organizationName: data.organizationName || null,
        designation: data.designation || null,
        isPersonWithDisability: data.isPersonWithDisability,
        attendingDays: data.attendingDays,
        interestedTracks: data.interestedTracks,
        submittedAt: now,
      })
      .returning();

    // Store sanitized form response snapshot (never store password)
    const { password: _omittedPassword, ...safeAnswers } = data;
    void _omittedPassword;
    await db.insert(schema.registrationResponses).values({
      registrationId: registration.id,
      answers: safeAnswers,
    });

    // Role-specific details
    if (categoryCode === "DELEGATE" && data.delegateDetails) {
      await db.insert(schema.delegateDetails).values({
        registrationId: registration.id,
        gender: data.delegateDetails.gender || null,
        age: data.delegateDetails.age ?? null,
        cityState: data.delegateDetails.cityState || null,
        paraSportsInterests: data.delegateDetails.paraSportsInterests || [],
        outstationTravelRequired: Boolean(data.delegateDetails.outstationTravelRequired),
        accommodationNeeded: Boolean(data.delegateDetails.accommodationNeeded),
        foodPreference: data.delegateDetails.foodPreference || null,
        emergencyContactName: data.delegateDetails.emergencyContactName || null,
        emergencyContactPhone: data.delegateDetails.emergencyContactPhone || null,
        guardianName: data.delegateDetails.guardianName || null,
      });
    }

    if (categoryCode === "VOLUNTEER") {
      const v = data.volunteerDetails;
      await db.insert(schema.volunteerApplications).values({
        registrationId: registration.id,
        age: v?.age ?? null,
        gender: v?.gender || null,
        cityState: v?.cityState || null,
        institutionName: v?.institutionName || data.organizationName || null,
        isMsuStudentOrAlumni: Boolean(v?.isMsuStudentOrAlumni),
        crrNumber: v?.crrNumber || null,
        preferredRoles: v?.preferredRoles || [],
        availabilityDays: v?.availabilityDays?.length
          ? v.availabilityDays
          : data.attendingDays,
        languagesSpoken: v?.languagesSpoken || [],
        relevantExperience: v?.relevantExperience || null,
        emergencyContactName: v?.emergencyContactName || null,
        emergencyContactPhone: v?.emergencyContactPhone || null,
      });
    }

    if (categoryCode === "SLI") {
      const s = data.sliDetails;
      await db.insert(schema.sliApplications).values({
        registrationId: registration.id,
        cityState: s?.cityState || null,
        basedInGujarat: s?.basedInGujarat ?? true,
        crrNumber: s?.crrNumber || null,
        qualifications: s?.qualifications || null,
        trainingInstitute: s?.trainingInstitute || null,
        certificationLevel: s?.certificationLevel || null,
        yearsOfExperience: s?.yearsOfExperience ?? null,
        professionalAffiliation: s?.professionalAffiliation || data.organizationName || null,
        areasOfExpertise: s?.areasOfExpertise || [],
        interpretationPreferences: s?.interpretationPreferences || [],
        availabilityDays: s?.availabilityDays?.length
          ? s.availabilityDays
          : data.attendingDays,
        videoDemoUrl: s?.videoDemoUrl || null,
        honorariumOrTravelNotes: s?.honorariumOrTravelNotes || null,
      });
    }

    // Separate Privacy & Consent Records (DPDP Act compliant)
    await db.insert(schema.privacyConsentRecords).values([
      {
        userId,
        registrationId: registration.id,
        consentType: "ZERO_TOLERANCE_HARASSMENT",
        consentVersion: "1.0",
        granted: true,
        ipAddress,
      },
      {
        userId,
        registrationId: registration.id,
        consentType: "DATA_PROCESSING_DPDP",
        consentVersion: "1.0",
        granted: true,
        ipAddress,
      },
      {
        userId,
        registrationId: registration.id,
        consentType: "PHOTOGRAPHY_MEDIA",
        consentVersion: "1.0",
        granted: Boolean(data.consentMediaPhotography),
        ipAddress,
      },
    ]);

    // Protected Disability Disclosure Table (optional & independent of category)
    if (
      data.isPersonWithDisability === "YES" &&
      (data.disabilityCategoryCodes.length > 0 ||
        (data.disabilitySelfDescription && data.disabilitySelfDescription.trim().length > 0))
    ) {
      if (data.disabilityCategoryCodes.length > 0) {
        const matchedCats = await db
          .select()
          .from(schema.disabilityCategories)
          .where(inArray(schema.disabilityCategories.code, data.disabilityCategoryCodes));

        for (let i = 0; i < matchedCats.length; i++) {
          await db.insert(schema.participantDisabilityDisclosures).values({
            registrationId: registration.id,
            disabilityCategoryId: matchedCats[i].id,
            selfDescription:
              i === 0 ? data.disabilitySelfDescription || null : null,
          });
        }
      } else {
        await db.insert(schema.participantDisabilityDisclosures).values({
          registrationId: registration.id,
          disabilityCategoryId: null,
          selfDescription: data.disabilitySelfDescription || null,
        });
      }
    }

    // Protected Accommodation Requests Table
    if (
      data.accommodationTypes.length > 0 ||
      (data.accommodationFreeText && data.accommodationFreeText.trim().length > 0)
    ) {
      await db.insert(schema.accommodationRequests).values({
        registrationId: registration.id,
        userId,
        selectedTypes: data.accommodationTypes,
        freeTextDetails: data.accommodationFreeText || null,
        status: "REQUESTED",
      });
    }

    // Coupon redemption record
    if (appliedCoupon) {
      await db.insert(schema.couponRedemptions).values({
        couponId: appliedCoupon.id,
        registrationId: registration.id,
        userId,
        discountAppliedInr: discountInr,
      });
      await db
        .update(schema.couponCodes)
        .set({ usedCount: sql`${schema.couponCodes.usedCount} + 1` })
        .where(eq(schema.couponCodes.id, appliedCoupon.id));
    }

    // QR Pass Issuance & Notifications
    let qrPass: {
      credentialCode: string;
      rawToken: string;
      qrDataUrl: string;
    } | null = null;

    if (shouldIssueQr) {
      const issued = await issueCredentialForRegistration(registration.id, userId);
      qrPass = {
        credentialCode: issued.credential.credentialCode,
        rawToken: issued.rawToken,
        qrDataUrl: issued.qrDataUrl,
      };

      await queueNotification({
        templateCode: "REGISTRATION_CONFIRMED",
        recipientUserId: userId,
        recipientEmail: normalizedEmail,
        variables: {
          fullName: data.fullName,
          registrationCode: registration.registrationCode,
          category: category.name,
        },
        fallbackSubject: `Confirmed: Purple Festival Gujarat 2027 (${registration.registrationCode})`,
      });
    } else if (category.requiresApproval) {
      await queueNotification({
        templateCode: "APPLICATION_SUBMITTED",
        recipientUserId: userId,
        recipientEmail: normalizedEmail,
        variables: {
          fullName: data.fullName,
          registrationCode: registration.registrationCode,
          category: category.name,
        },
        fallbackSubject: `Application Received: Purple Festival Gujarat 2027 (${registration.registrationCode})`,
      });
    }

    await recordAuditLog({
      actorUserId: userId,
      action: "REGISTRATION_SUBMITTED",
      resourceType: "registration",
      resourceId: registration.id,
      ipAddress,
      userAgent,
      metadata: {
        registrationCode: registration.registrationCode,
        categoryCode,
        status,
        paymentStatus,
        qrIssued: Boolean(qrPass),
      },
    });

    return NextResponse.json(
      {
        registration,
        category: {
          code: category.code,
          name: category.name,
          requiresApproval: category.requiresApproval,
          requiresPayment: category.requiresPayment,
        },
        pricing: {
          feeAmountInr,
          discountInr,
          netAmountInr,
          couponCode: appliedCoupon?.code ?? null,
        },
        qrPass,
        sessionCreated,
      },
      { status: 201 }
    );
  } catch (err) {
    console.error("POST /api/registrations error:", err);
    return NextResponse.json(
      { error: "Unable to complete registration right now. Please try again." },
      { status: 500 }
    );
  }
}
