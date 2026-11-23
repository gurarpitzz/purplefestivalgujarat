import { NextRequest, NextResponse } from "next/server";
import { and, desc, eq } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission, hasRole, recordAuditLog } from "@/lib/auth";
import {
  formatIstTimestamp,
  getIstDateString,
  PERMISSIONS,
} from "@/lib/constants";
import {
  generateQrDataUrl,
  issueCredentialForRegistration,
  revokeCredential,
} from "@/lib/qr";

const manageCredentialSchema = z.object({
  action: z.enum(["reissue", "revoke"]).default("reissue"),
  reason: z.string().trim().max(300).optional(),
});

export async function GET(
  _req: NextRequest,
  context: { params: Promise<{ registrationId: string }> }
) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const { registrationId } = await context.params;
    if (!registrationId) {
      return NextResponse.json({ error: "Registration ID is required." }, { status: 400 });
    }

    const [reg] = await db
      .select({
        id: schema.registrations.id,
        registrationCode: schema.registrations.registrationCode,
        userId: schema.registrations.userId,
        editionId: schema.registrations.editionId,
        categoryId: schema.registrations.categoryId,
        categoryCode: schema.registrations.categoryCode,
        status: schema.registrations.status,
        paymentStatus: schema.registrations.paymentStatus,
        fullName: schema.registrations.fullName,
      })
      .from(schema.registrations)
      .where(eq(schema.registrations.id, registrationId))
      .limit(1);

    if (!reg) {
      return NextResponse.json({ error: "Registration not found." }, { status: 404 });
    }

    const isOwner = reg.userId === user.id;
    const isAdmin = hasRole(user, ["ADMIN", "SUPERADMIN"]);
    if (!isOwner && !isAdmin) {
      return NextResponse.json(
        { error: "You do not have permission to view this pass." },
        { status: 403 }
      );
    }

    const [category] = await db
      .select({
        name: schema.registrationCategories.name,
      })
      .from(schema.registrationCategories)
      .where(eq(schema.registrationCategories.id, reg.categoryId))
      .limit(1);

    const [credential] = await db
      .select()
      .from(schema.qrCredentials)
      .where(
        and(
          eq(schema.qrCredentials.registrationId, reg.id),
          eq(schema.qrCredentials.status, "ACTIVE")
        )
      )
      .orderBy(desc(schema.qrCredentials.issuedAt))
      .limit(1);

    if (!credential) {
      return NextResponse.json(
        {
          error:
            "No active QR pass is issued for this registration yet. Passes are issued once your registration is confirmed or approved.",
          registrationId: reg.id,
          registrationCode: reg.registrationCode,
          registrationStatus: reg.status,
          paymentStatus: reg.paymentStatus,
          hasActiveCredential: false,
        },
        { status: 404 }
      );
    }

    // Encode credentialCode (contains zero PII and verifies cleanly via verifyCredentialInput)
    const qrPayload = credential.credentialCode;
    const qrDataUrl = await generateQrDataUrl(qrPayload);

    const regCheckins = await db
      .select({
        id: schema.checkins.id,
        venueLabel: schema.checkins.venueLabel,
        checkinDateIst: schema.checkins.checkinDateIst,
        checkedInAt: schema.checkins.checkedInAt,
      })
      .from(schema.checkins)
      .where(eq(schema.checkins.registrationId, reg.id))
      .orderBy(desc(schema.checkins.checkedInAt));

    const todayIst = getIstDateString();
    const checkedInToday = regCheckins.some((c) => c.checkinDateIst === todayIst);
    const latest = regCheckins[0] ?? null;

    const categoryName = category?.name ?? reg.categoryCode;
    const accessibleText = `Purple Festival Gujarat 2027 Digital Entry Pass for ${reg.fullName}. Category: ${categoryName}. Registration ID: ${reg.registrationCode}. Credential ID: ${credential.credentialCode}. Status: ${credential.status}. Present this QR code or quote your Credential ID at the Registration Desk (RGST).`;

    return NextResponse.json(
      {
        hasActiveCredential: true,
        registrationId: reg.id,
        registrationCode: reg.registrationCode,
        participantName: reg.fullName,
        categoryCode: reg.categoryCode,
        categoryName,
        registrationStatus: reg.status,
        paymentStatus: reg.paymentStatus,
        credential: {
          id: credential.id,
          credentialCode: credential.credentialCode,
          status: credential.status,
          issuedAt: credential.issuedAt.toISOString(),
          issuedAtIst: formatIstTimestamp(credential.issuedAt),
        },
        qrDataUrl,
        qrPayload,
        accessibleText,
        attendance: {
          checkedInToday,
          totalCheckins: regCheckins.length,
          latestCheckin: latest
            ? {
                checkedInAt: latest.checkedInAt.toISOString(),
                checkedInAtIst: formatIstTimestamp(latest.checkedInAt),
                venueLabel: latest.venueLabel,
                checkinDateIst: latest.checkinDateIst,
              }
            : null,
        },
        pdfUrl: `/api/qr/${reg.id}/pdf`,
      },
      {
        headers: { "Cache-Control": "no-store" },
      }
    );
  } catch (err) {
    console.error("[GET /api/qr/[registrationId]] Error:", err);
    return NextResponse.json(
      { error: "Failed to load QR pass." },
      { status: 500 }
    );
  }
}

export async function POST(
  req: NextRequest,
  context: { params: Promise<{ registrationId: string }> }
) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const { registrationId } = await context.params;
    const body = await req.json().catch(() => ({}));
    const parsed = manageCredentialSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: "Invalid request payload.", details: parsed.error.flatten() },
        { status: 400 }
      );
    }

    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || null;
    const userAgent = req.headers.get("user-agent") || null;

    if (parsed.data.action === "revoke") {
      if (!hasPermission(user, PERMISSIONS.CREDENTIALS_REVOKE)) {
        return NextResponse.json(
          { error: "Insufficient permissions to revoke credentials." },
          { status: 403 }
        );
      }

      const [activeCred] = await db
        .select()
        .from(schema.qrCredentials)
        .where(
          and(
            eq(schema.qrCredentials.registrationId, registrationId),
            eq(schema.qrCredentials.status, "ACTIVE")
          )
        )
        .orderBy(desc(schema.qrCredentials.issuedAt))
        .limit(1);

      if (!activeCred) {
        return NextResponse.json(
          { error: "No active credential found to revoke." },
          { status: 404 }
        );
      }

      const reason = parsed.data.reason || "Revoked by administrator";
      const revoked = await revokeCredential(activeCred.id, user.id, reason);

      await recordAuditLog({
        actorUserId: user.id,
        action: "CREDENTIAL_REVOKED",
        resourceType: "qr_credential",
        resourceId: activeCred.id,
        ipAddress,
        userAgent,
        metadata: { registrationId, credentialCode: activeCred.credentialCode, reason },
      });

      return NextResponse.json({
        success: true,
        action: "revoke",
        credential: revoked,
      });
    }

    // Reissue action
    if (!hasPermission(user, PERMISSIONS.CREDENTIALS_ISSUE)) {
      return NextResponse.json(
        { error: "Insufficient permissions to issue or reissue credentials." },
        { status: 403 }
      );
    }

    try {
      const issued = await issueCredentialForRegistration(registrationId, user.id);

      await recordAuditLog({
        actorUserId: user.id,
        action: "CREDENTIAL_REISSUED",
        resourceType: "qr_credential",
        resourceId: issued.credential.id,
        ipAddress,
        userAgent,
        metadata: {
          registrationId,
          credentialCode: issued.credential.credentialCode,
        },
      });

      return NextResponse.json(
        {
          success: true,
          action: "reissue",
          credential: {
            id: issued.credential.id,
            credentialCode: issued.credential.credentialCode,
            status: issued.credential.status,
            issuedAt: issued.credential.issuedAt.toISOString(),
            issuedAtIst: formatIstTimestamp(issued.credential.issuedAt),
          },
          qrDataUrl: issued.qrDataUrl,
        },
        { status: 201 }
      );
    } catch (issueErr) {
      const msg =
        issueErr instanceof Error ? issueErr.message : "Failed to issue credential.";
      return NextResponse.json({ error: msg }, { status: 400 });
    }
  } catch (err) {
    console.error("[POST /api/qr/[registrationId]] Error:", err);
    return NextResponse.json(
      { error: "Failed to manage QR credential." },
      { status: 500 }
    );
  }
}
