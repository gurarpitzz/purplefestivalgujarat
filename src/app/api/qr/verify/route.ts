import { NextRequest, NextResponse } from "next/server";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission } from "@/lib/auth";
import { formatIstTimestamp, PERMISSIONS } from "@/lib/constants";
import { verifyCredentialInput } from "@/lib/qr";

const verifyBodySchema = z.object({
  input: z.string().trim().min(1, "QR token or Credential ID is required.").max(256),
  scopeOverride: z.string().trim().max(100).optional(),
});

/**
 * POST /api/qr/verify
 * Restricted strictly to RGST, ADMIN, SUPERADMIN (PERMISSIONS.QR_SCAN).
 * Data minimization: returns ONLY minimal venue check-in info.
 * NEVER returns disability, medical, accommodation, email, or phone data.
 */
export async function POST(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    if (!hasPermission(user, PERMISSIONS.QR_SCAN)) {
      return NextResponse.json(
        { error: "Access denied. QR scanning is restricted to authorized RGST and Admin staff." },
        { status: 403 }
      );
    }

    const body = await req.json().catch(() => null);
    const parsed = verifyBodySchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: "Please provide a valid QR code or Registration/Credential ID." },
        { status: 400 }
      );
    }

    const result = await verifyCredentialInput(parsed.data.input, {
      scopeOverride: parsed.data.scopeOverride,
    });

    // Log SCANNED event whenever a credential record was located
    if (result.credentialId) {
      await db.insert(schema.credentialEvents).values({
        credentialId: result.credentialId,
        actorUserId: user.id,
        eventType: "SCANNED",
        details: {
          valid: result.valid,
          reason: result.reason,
          entitlementScope: result.entitlementScope ?? null,
        },
      });
    }

    const attendanceStatus =
      result.reason === "ALREADY_CHECKED_IN"
        ? "ALREADY_CHECKED_IN"
        : result.valid
          ? "NOT_CHECKED_IN"
          : "INELIGIBLE";

    return NextResponse.json(
      {
        valid: result.valid,
        reason: result.reason,
        message: result.message,
        credentialId: result.credentialId ?? null,
        credentialCode: result.credentialCode ?? null,
        registrationCode: result.registrationCode ?? null,
        participantName: result.participantName ?? null,
        categoryCode: result.categoryCode ?? null,
        credentialStatus: result.credentialStatus ?? null,
        registrationStatus: result.registrationStatus ?? null,
        attendanceStatus,
        entitlementScope: result.entitlementScope ?? null,
        existingCheckin: result.existingCheckin
          ? {
              checkedInAt: result.existingCheckin.checkedInAt,
              checkedInAtIst: formatIstTimestamp(result.existingCheckin.checkedInAt),
              venueLabel: result.existingCheckin.venueLabel,
              checkinDateIst: result.existingCheckin.checkinDateIst,
            }
          : null,
      },
      {
        headers: { "Cache-Control": "no-store" },
      }
    );
  } catch (err) {
    console.error("[POST /api/qr/verify] Error:", err);
    return NextResponse.json(
      { error: "Failed to verify credential." },
      { status: 500 }
    );
  }
}
