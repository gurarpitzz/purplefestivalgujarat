import { NextRequest, NextResponse } from "next/server";
import { and, eq } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission, recordAuditLog } from "@/lib/auth";
import {
  formatIstTimestamp,
  getIstDateString,
  PERMISSIONS,
} from "@/lib/constants";
import { confirmParticipantCheckin } from "@/lib/qr";

const confirmCheckinSchema = z.object({
  credentialId: z.string().uuid("Valid credentialId is required."),
  venueLabel: z.string().trim().min(1, "Venue or station label is required.").max(150),
  stationId: z.string().uuid().nullable().optional(),
  method: z.enum(["QR_CAMERA", "USB_SCANNER", "MANUAL_ID"]).default("QR_CAMERA"),
  scopeOverride: z.string().trim().max(100).optional(),
});

/**
 * POST /api/checkins/confirm
 * Restricted strictly to RGST, ADMIN, SUPERADMIN (PERMISSIONS.CHECKINS_CONFIRM).
 * Explicitly confirms attendance with DB-level unique constraint duplicate protection.
 */
export async function POST(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    if (!hasPermission(user, PERMISSIONS.CHECKINS_CONFIRM)) {
      return NextResponse.json(
        { error: "Access denied. Check-in confirmation requires RGST or Admin authorization." },
        { status: 403 }
      );
    }

    const body = await req.json().catch(() => null);
    const parsed = confirmCheckinSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: "Invalid check-in request.", details: parsed.error.flatten() },
        { status: 400 }
      );
    }

    const { credentialId, venueLabel, stationId, method, scopeOverride } = parsed.data;
    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || null;
    const userAgent = req.headers.get("user-agent") || null;

    // Guard registration eligibility before confirming check-in
    const [credRow] = await db
      .select({
        credentialId: schema.qrCredentials.id,
        credentialCode: schema.qrCredentials.credentialCode,
        credentialStatus: schema.qrCredentials.status,
        editionId: schema.qrCredentials.editionId,
        registrationId: schema.registrations.id,
        registrationCode: schema.registrations.registrationCode,
        registrationStatus: schema.registrations.status,
        paymentStatus: schema.registrations.paymentStatus,
        participantName: schema.registrations.fullName,
        categoryCode: schema.registrations.categoryCode,
      })
      .from(schema.qrCredentials)
      .innerJoin(
        schema.registrations,
        eq(schema.qrCredentials.registrationId, schema.registrations.id)
      )
      .where(eq(schema.qrCredentials.id, credentialId))
      .limit(1);

    if (!credRow) {
      return NextResponse.json({ error: "Credential not found." }, { status: 404 });
    }

    if (credRow.credentialStatus !== "ACTIVE") {
      return NextResponse.json(
        { error: `Credential is ${credRow.credentialStatus} and cannot be checked in.` },
        { status: 400 }
      );
    }

    if (!["CONFIRMED", "APPROVED"].includes(credRow.registrationStatus)) {
      return NextResponse.json(
        { error: `Registration status is ${credRow.registrationStatus} and is not eligible for entry.` },
        { status: 400 }
      );
    }

    if (
      credRow.paymentStatus !== "NOT_REQUIRED" &&
      credRow.paymentStatus !== "CAPTURED"
    ) {
      return NextResponse.json(
        { error: `Payment status is ${credRow.paymentStatus}. Entry requires verified payment.` },
        { status: 400 }
      );
    }

    const result = await confirmParticipantCheckin({
      credentialId,
      operatorUserId: user.id,
      venueLabel,
      stationId: stationId ?? null,
      method,
      scopeOverride,
    });

    await recordAuditLog({
      actorUserId: user.id,
      action: result.success
        ? "CHECKIN_CONFIRMED"
        : result.duplicate
          ? "CHECKIN_DUPLICATE_PREVENTED"
          : "CHECKIN_REJECTED",
      resourceType: "checkin",
      resourceId: result.checkin?.id ?? credentialId,
      ipAddress,
      userAgent,
      metadata: {
        credentialId,
        registrationCode: credRow.registrationCode,
        venueLabel,
        stationId: stationId ?? null,
        method,
        duplicate: Boolean(result.duplicate),
      },
    });

    if (result.duplicate) {
      const [edition] = await db
        .select({ attendancePolicy: schema.festivalEditions.attendancePolicy })
        .from(schema.festivalEditions)
        .where(eq(schema.festivalEditions.id, credRow.editionId))
        .limit(1);

      const entitlementScope =
        scopeOverride ||
        (edition?.attendancePolicy === "ONCE_PER_FESTIVAL"
          ? "FESTIVAL"
          : getIstDateString());

      const [existing] = await db
        .select({
          checkedInAt: schema.checkins.checkedInAt,
          venueLabel: schema.checkins.venueLabel,
          checkinDateIst: schema.checkins.checkinDateIst,
        })
        .from(schema.checkins)
        .where(
          and(
            eq(schema.checkins.credentialId, credentialId),
            eq(schema.checkins.entitlementScope, entitlementScope)
          )
        )
        .limit(1);

      return NextResponse.json(
        {
          success: false,
          duplicate: true,
          message: "ALREADY CHECKED IN",
          existingCheckin: existing
            ? {
                checkedInAt: existing.checkedInAt.toISOString(),
                checkedInAtIst: formatIstTimestamp(existing.checkedInAt),
                venueLabel: existing.venueLabel,
                checkinDateIst: existing.checkinDateIst,
              }
            : null,
        },
        { status: 409 }
      );
    }

    if (!result.success || !result.checkin) {
      return NextResponse.json(
        { success: false, message: result.message },
        { status: 400 }
      );
    }

    return NextResponse.json(
      {
        success: true,
        message: "Participant checked in successfully.",
        checkin: {
          id: result.checkin.id,
          credentialId: result.checkin.credentialId,
          credentialCode: credRow.credentialCode,
          registrationCode: credRow.registrationCode,
          participantName: credRow.participantName,
          categoryCode: credRow.categoryCode,
          venueLabel: result.checkin.venueLabel,
          stationId: result.checkin.stationId,
          entitlementScope: result.checkin.entitlementScope,
          checkinDateIst: result.checkin.checkinDateIst,
          method: result.checkin.method,
          checkedInAt: result.checkin.checkedInAt.toISOString(),
          checkedInAtIst: formatIstTimestamp(result.checkin.checkedInAt),
        },
      },
      { status: 201 }
    );
  } catch (err) {
    console.error("[POST /api/checkins/confirm] Error:", err);
    return NextResponse.json(
      { error: "Failed to confirm participant check-in." },
      { status: 500 }
    );
  }
}
