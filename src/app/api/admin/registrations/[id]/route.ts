import { NextRequest, NextResponse } from "next/server";
import { and, desc, eq } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission, hasRole, recordAuditLog } from "@/lib/auth";
import { PERMISSIONS } from "@/lib/constants";
import { issueCredentialForRegistration, revokeCredential } from "@/lib/qr";
import { queueNotification } from "@/lib/notifications";

const patchRegistrationSchema = z.object({
  status: z
    .enum([
      "DRAFT",
      "SUBMITTED",
      "UNDER_REVIEW",
      "ADDITIONAL_INFO_REQUIRED",
      "CONFIRMED",
      "APPROVED",
      "WAITLISTED",
      "REJECTED",
      "WITHDRAWN",
      "CANCELLED",
    ])
    .optional(),
  reviewNotes: z.string().max(4000).optional(),
  volunteerAssignment: z
    .object({
      assignedDepartment: z.string().max(120).optional().nullable(),
      assignedResponsibility: z.string().max(2000).optional().nullable(),
      assignedSupervisor: z.string().max(150).optional().nullable(),
      assignedVenue: z.string().max(200).optional().nullable(),
      assignedShift: z.string().max(100).optional().nullable(),
      reportingTime: z.string().max(80).optional().nullable(),
      assignmentNotes: z.string().max(2000).optional().nullable(),
    })
    .optional(),
  sliAssignment: z
    .object({
      assignedSessions: z
        .array(
          z.object({
            sessionTitle: z.string().max(200),
            venue: z.string().max(200),
            dutyTime: z.string().max(100),
            day: z.string().max(60),
          })
        )
        .optional(),
      assignedVenue: z.string().max(200).optional().nullable(),
      dutyTimes: z.string().max(150).optional().nullable(),
      assignmentNotes: z.string().max(2000).optional().nullable(),
    })
    .optional(),
  credentialAction: z.enum(["ISSUE", "REISSUE", "REVOKE"]).optional(),
  revocationReason: z.string().max(500).optional(),
});

export async function GET(
  _req: NextRequest,
  context: { params: Promise<{ id: string }> }
) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasRole(user, ["SUPERADMIN", "ADMIN"])) {
      return NextResponse.json({ error: "Admin access required." }, { status: 403 });
    }

    const { id } = await context.params;
    const [reg] = await db
      .select()
      .from(schema.registrations)
      .where(eq(schema.registrations.id, id))
      .limit(1);

    if (!reg) {
      return NextResponse.json({ error: "Registration not found." }, { status: 404 });
    }

    const canReadAccomm = hasPermission(user, PERMISSIONS.ACCOMMODATIONS_READ);
    const canReadPayments = hasPermission(user, PERMISSIONS.PAYMENTS_READ);

    const [
      delegateRows,
      volunteerRows,
      sliRows,
      credentials,
      checkins,
      paymentOrders,
      accommRows,
      disclosureRows,
    ] = await Promise.all([
      db
        .select()
        .from(schema.delegateDetails)
        .where(eq(schema.delegateDetails.registrationId, reg.id))
        .limit(1),
      db
        .select()
        .from(schema.volunteerApplications)
        .where(eq(schema.volunteerApplications.registrationId, reg.id))
        .limit(1),
      db
        .select()
        .from(schema.sliApplications)
        .where(eq(schema.sliApplications.registrationId, reg.id))
        .limit(1),
      db
        .select()
        .from(schema.qrCredentials)
        .where(eq(schema.qrCredentials.registrationId, reg.id))
        .orderBy(desc(schema.qrCredentials.issuedAt)),
      db
        .select()
        .from(schema.checkins)
        .where(eq(schema.checkins.registrationId, reg.id))
        .orderBy(desc(schema.checkins.checkedInAt)),
      canReadPayments
        ? db
            .select()
            .from(schema.paymentOrders)
            .where(eq(schema.paymentOrders.registrationId, reg.id))
            .orderBy(desc(schema.paymentOrders.createdAt))
        : Promise.resolve([]),
      canReadAccomm
        ? db
            .select()
            .from(schema.accommodationRequests)
            .where(eq(schema.accommodationRequests.registrationId, reg.id))
        : Promise.resolve(null),
      canReadAccomm
        ? db
            .select({
              id: schema.participantDisabilityDisclosures.id,
              selfDescription: schema.participantDisabilityDisclosures.selfDescription,
              categoryCode: schema.disabilityCategories.code,
              categoryName: schema.disabilityCategories.name,
              rpwdGroup: schema.disabilityCategories.rpwdGroup,
            })
            .from(schema.participantDisabilityDisclosures)
            .leftJoin(
              schema.disabilityCategories,
              eq(
                schema.participantDisabilityDisclosures.disabilityCategoryId,
                schema.disabilityCategories.id
              )
            )
            .where(eq(schema.participantDisabilityDisclosures.registrationId, reg.id))
        : Promise.resolve(null),
    ]);

    return NextResponse.json({
      registration: {
        ...reg,
        // Mask disability disclosure flag if user cannot read accommodations
        isPersonWithDisability: canReadAccomm ? reg.isPersonWithDisability : "RESTRICTED",
      },
      delegateDetails: delegateRows[0] ?? null,
      volunteerDetails: volunteerRows[0] ?? null,
      sliDetails: sliRows[0] ?? null,
      credentials,
      checkins,
      paymentOrders,
      accommodationsRestricted: !canReadAccomm,
      accommodations: canReadAccomm ? accommRows : null,
      disabilityDisclosures: canReadAccomm ? disclosureRows : null,
    });
  } catch (err) {
    console.error("GET /api/admin/registrations/[id] error:", err);
    return NextResponse.json({ error: "Failed to load registration detail." }, { status: 500 });
  }
}

export async function PATCH(
  req: NextRequest,
  context: { params: Promise<{ id: string }> }
) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasRole(user, ["SUPERADMIN", "ADMIN"])) {
      return NextResponse.json({ error: "Admin access required." }, { status: 403 });
    }

    const { id } = await context.params;
    const body = await req.json().catch(() => null);
    const parsed = patchRegistrationSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: "Invalid update payload.", details: parsed.error.flatten() },
        { status: 400 }
      );
    }

    const [existing] = await db
      .select()
      .from(schema.registrations)
      .where(eq(schema.registrations.id, id))
      .limit(1);

    if (!existing) {
      return NextResponse.json({ error: "Registration not found." }, { status: 404 });
    }

    const data = parsed.data;
    const beforeState = {
      status: existing.status,
      reviewNotes: existing.reviewNotes,
    };

    const updateValues: Partial<typeof schema.registrations.$inferInsert> = {
      updatedAt: new Date(),
    };

    if (data.status !== undefined) {
      updateValues.status = data.status;
      updateValues.reviewerId = user.id;
      updateValues.reviewedAt = new Date();
    }
    if (data.reviewNotes !== undefined) {
      updateValues.reviewNotes = data.reviewNotes;
      updateValues.reviewerId = user.id;
      updateValues.reviewedAt = new Date();
    }

    const [updatedReg] = await db
      .update(schema.registrations)
      .set(updateValues)
      .where(eq(schema.registrations.id, id))
      .returning();

    // Volunteer assignment update
    let updatedVolunteer = null;
    if (data.volunteerAssignment) {
      const [existingVol] = await db
        .select()
        .from(schema.volunteerApplications)
        .where(eq(schema.volunteerApplications.registrationId, id))
        .limit(1);

      if (existingVol) {
        const [v] = await db
          .update(schema.volunteerApplications)
          .set({
            assignedDepartment:
              data.volunteerAssignment.assignedDepartment ?? existingVol.assignedDepartment,
            assignedResponsibility:
              data.volunteerAssignment.assignedResponsibility ?? existingVol.assignedResponsibility,
            assignedSupervisor:
              data.volunteerAssignment.assignedSupervisor ?? existingVol.assignedSupervisor,
            assignedVenue: data.volunteerAssignment.assignedVenue ?? existingVol.assignedVenue,
            assignedShift: data.volunteerAssignment.assignedShift ?? existingVol.assignedShift,
            reportingTime: data.volunteerAssignment.reportingTime ?? existingVol.reportingTime,
            assignmentNotes:
              data.volunteerAssignment.assignmentNotes ?? existingVol.assignmentNotes,
            updatedAt: new Date(),
          })
          .where(eq(schema.volunteerApplications.id, existingVol.id))
          .returning();
        updatedVolunteer = v;
      } else {
        const [v] = await db
          .insert(schema.volunteerApplications)
          .values({
            registrationId: id,
            assignedDepartment: data.volunteerAssignment.assignedDepartment ?? null,
            assignedResponsibility: data.volunteerAssignment.assignedResponsibility ?? null,
            assignedSupervisor: data.volunteerAssignment.assignedSupervisor ?? null,
            assignedVenue: data.volunteerAssignment.assignedVenue ?? null,
            assignedShift: data.volunteerAssignment.assignedShift ?? null,
            reportingTime: data.volunteerAssignment.reportingTime ?? null,
            assignmentNotes: data.volunteerAssignment.assignmentNotes ?? null,
          })
          .returning();
        updatedVolunteer = v;
      }
    }

    // SLI assignment update
    let updatedSli = null;
    if (data.sliAssignment) {
      const [existingSli] = await db
        .select()
        .from(schema.sliApplications)
        .where(eq(schema.sliApplications.registrationId, id))
        .limit(1);

      if (existingSli) {
        const [s] = await db
          .update(schema.sliApplications)
          .set({
            assignedSessions:
              data.sliAssignment.assignedSessions ?? existingSli.assignedSessions,
            assignedVenue: data.sliAssignment.assignedVenue ?? existingSli.assignedVenue,
            dutyTimes: data.sliAssignment.dutyTimes ?? existingSli.dutyTimes,
            assignmentNotes: data.sliAssignment.assignmentNotes ?? existingSli.assignmentNotes,
            updatedAt: new Date(),
          })
          .where(eq(schema.sliApplications.id, existingSli.id))
          .returning();
        updatedSli = s;
      } else {
        const [s] = await db
          .insert(schema.sliApplications)
          .values({
            registrationId: id,
            assignedSessions: data.sliAssignment.assignedSessions ?? [],
            assignedVenue: data.sliAssignment.assignedVenue ?? null,
            dutyTimes: data.sliAssignment.dutyTimes ?? null,
            assignmentNotes: data.sliAssignment.assignmentNotes ?? null,
          })
          .returning();
        updatedSli = s;
      }
    }

    // Credential issuance / revocation logic
    let credentialResult: {
      action: string;
      credentialCode?: string;
      credentialId?: string;
      warning?: string;
    } | null = null;

    const transitionedToApproved =
      data.status &&
      ["APPROVED", "CONFIRMED"].includes(data.status) &&
      !["APPROVED", "CONFIRMED"].includes(existing.status);

    if (data.credentialAction === "REVOKE") {
      if (!hasPermission(user, PERMISSIONS.CREDENTIALS_REVOKE)) {
        return NextResponse.json(
          { error: "Permission required to revoke credentials." },
          { status: 403 }
        );
      }
      const activeCreds = await db
        .select()
        .from(schema.qrCredentials)
        .where(
          and(
            eq(schema.qrCredentials.registrationId, id),
            eq(schema.qrCredentials.status, "ACTIVE")
          )
        );
      for (const c of activeCreds) {
        await revokeCredential(
          c.id,
          user.id,
          data.revocationReason || "Revoked by festival administrator"
        );
      }
      credentialResult = { action: "REVOKED" };
    } else if (data.credentialAction === "ISSUE" || data.credentialAction === "REISSUE") {
      if (!hasPermission(user, PERMISSIONS.CREDENTIALS_ISSUE)) {
        return NextResponse.json(
          { error: "Permission required to issue credentials." },
          { status: 403 }
        );
      }
      try {
        const issued = await issueCredentialForRegistration(id, user.id);
        credentialResult = {
          action: data.credentialAction,
          credentialCode: issued.credential.credentialCode,
          credentialId: issued.credential.id,
        };
      } catch (e) {
        credentialResult = {
          action: "SKIPPED",
          warning: e instanceof Error ? e.message : "Could not issue credential.",
        };
      }
    } else if (transitionedToApproved) {
      // Automatically issue QR credential if none active & payment satisfied
      const existingActive = await db
        .select({ id: schema.qrCredentials.id, code: schema.qrCredentials.credentialCode })
        .from(schema.qrCredentials)
        .where(
          and(
            eq(schema.qrCredentials.registrationId, id),
            eq(schema.qrCredentials.status, "ACTIVE")
          )
        )
        .limit(1);

      if (existingActive.length === 0) {
        try {
          const issued = await issueCredentialForRegistration(id, user.id);
          credentialResult = {
            action: "AUTO_ISSUED",
            credentialCode: issued.credential.credentialCode,
            credentialId: issued.credential.id,
          };
        } catch (e) {
          credentialResult = {
            action: "AUTO_ISSUE_SKIPPED",
            warning: e instanceof Error ? e.message : "Payment pending before QR issuance.",
          };
        }
      }

      // Queue APPLICATION_APPROVED notification
      await queueNotification({
        templateCode: "APPLICATION_APPROVED",
        recipientUserId: updatedReg.userId,
        recipientEmail: updatedReg.email,
        variables: {
          fullName: updatedReg.fullName,
          registrationCode: updatedReg.registrationCode,
          category: updatedReg.categoryCode,
        },
        fallbackSubject: `Approved: Purple Festival Gujarat 2027 (${updatedReg.registrationCode})`,
      });
    }

    // Record admin action & audit log
    await db.insert(schema.adminActions).values({
      adminUserId: user.id,
      targetUserId: updatedReg.userId,
      actionType: data.credentialAction
        ? `CREDENTIAL_${data.credentialAction}`
        : data.status
          ? `REGISTRATION_STATUS_${data.status}`
          : "REGISTRATION_UPDATED",
      reason: data.reviewNotes || data.revocationReason || null,
      beforeState,
      afterState: {
        status: updatedReg.status,
        reviewNotes: updatedReg.reviewNotes,
        volunteerAssignment: data.volunteerAssignment ?? undefined,
        sliAssignment: data.sliAssignment ?? undefined,
        credentialResult,
      },
    });

    await recordAuditLog({
      actorUserId: user.id,
      action: data.status ? `REGISTRATION_${data.status}` : "REGISTRATION_UPDATED",
      resourceType: "registration",
      resourceId: updatedReg.id,
      metadata: {
        registrationCode: updatedReg.registrationCode,
        previousStatus: existing.status,
        newStatus: updatedReg.status,
        credentialAction: data.credentialAction ?? null,
      },
    });

    return NextResponse.json({
      registration: updatedReg,
      volunteerDetails: updatedVolunteer,
      sliDetails: updatedSli,
      credentialResult,
    });
  } catch (err) {
    console.error("PATCH /api/admin/registrations/[id] error:", err);
    return NextResponse.json({ error: "Failed to update registration." }, { status: 500 });
  }
}
