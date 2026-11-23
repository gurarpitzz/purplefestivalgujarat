import crypto from "crypto";
import QRCode from "qrcode";
import { and, desc, eq } from "drizzle-orm";
import { db, schema } from "@/db";
import { getIstDateString } from "./constants";

function getQrSigningSecret(): string {
  return process.env.QR_SIGNING_SECRET || "dev-qr-secret-change-in-production";
}

export function hashQrToken(rawToken: string): string {
  return crypto
    .createHmac("sha256", getQrSigningSecret())
    .update(rawToken)
    .digest("hex");
}

/**
 * Generates an opaque, cryptographically unpredictable QR token containing ZERO PII.
 */
export function generateOpaqueQrToken(): string {
  const nonce = crypto.randomBytes(24).toString("base64url");
  const sig = crypto
    .createHmac("sha256", getQrSigningSecret())
    .update(nonce)
    .digest("base64url")
    .slice(0, 16);
  return `PFG27.${nonce}.${sig}`;
}

export function isValidQrTokenFormat(token: string): boolean {
  const parts = token.trim().split(".");
  if (parts.length !== 3 || parts[0] !== "PFG27") return false;
  const [, nonce, sig] = parts;
  if (!nonce || !sig) return false;
  const expected = crypto
    .createHmac("sha256", getQrSigningSecret())
    .update(nonce)
    .digest("base64url")
    .slice(0, 16);
  return crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(expected));
}

export async function generateUniqueRegistrationCode(): Promise<string> {
  const existingCountRows = await db
    .select({ id: schema.registrations.id })
    .from(schema.registrations);
  const seq = existingCountRows.length + 1;
  const suffix = crypto.randomBytes(2).toString("hex").toUpperCase();
  const padded = String(seq).padStart(6, "0");
  return `PFG-2027-${padded}-${suffix}`;
}

export async function generateQrDataUrl(payload: string): Promise<string> {
  return QRCode.toDataURL(payload, {
    errorCorrectionLevel: "M",
    margin: 2,
    width: 320,
    color: {
      dark: "#17151C",
      light: "#F9F7FC",
    },
  });
}

/**
 * Issues a new ACTIVE QR credential for an eligible (CONFIRMED or APPROVED) registration.
 * Never issues active credentials to rejected, draft, or unpaid registrations where payment is required.
 */
export async function issueCredentialForRegistration(
  registrationId: string,
  actorUserId?: string | null
): Promise<{
  credential: typeof schema.qrCredentials.$inferSelect;
  rawToken: string;
  qrDataUrl: string;
}> {
  const [reg] = await db
    .select()
    .from(schema.registrations)
    .where(eq(schema.registrations.id, registrationId))
    .limit(1);

  if (!reg) {
    throw new Error("Registration not found.");
  }

  const eligibleStatuses = ["CONFIRMED", "APPROVED"];
  if (!eligibleStatuses.includes(reg.status)) {
    throw new Error(
      `Cannot issue QR credential while registration status is ${reg.status}.`
    );
  }

  if (
    reg.paymentStatus !== "NOT_REQUIRED" &&
    reg.paymentStatus !== "CAPTURED"
  ) {
    throw new Error(
      `Cannot issue QR credential while payment status is ${reg.paymentStatus}.`
    );
  }

  // Fetch all existing credentials for this registration (to guarantee unique reissue codes even after revocation)
  const existingCreds = await db
    .select()
    .from(schema.qrCredentials)
    .where(eq(schema.qrCredentials.registrationId, reg.id));

  const activeCreds = existingCreds.filter((c) => c.status === "ACTIVE");

  const rawToken = generateOpaqueQrToken();
  const tokenHash = hashQrToken(rawToken);
  const credentialCode = reg.registrationCode;

  // Ensure unique credentialCode if reissuing (whether previous was ACTIVE or REVOKED)
  const finalCode =
    existingCreds.length > 0
      ? `${credentialCode}-R${existingCreds.length + 1}`
      : credentialCode;

  const [created] = await db
    .insert(schema.qrCredentials)
    .values({
      credentialCode: finalCode,
      tokenHash,
      registrationId: reg.id,
      userId: reg.userId,
      editionId: reg.editionId,
      categoryCode: reg.categoryCode,
      status: "ACTIVE",
    })
    .returning();

  for (const old of activeCreds) {
    await db
      .update(schema.qrCredentials)
      .set({
        status: "REVOKED",
        revokedAt: new Date(),
        revokedBy: actorUserId ?? null,
        revocationReason: "Superseded by reissued credential",
        replacedByCredentialId: created.id,
      })
      .where(eq(schema.qrCredentials.id, old.id));
  }

  await db.insert(schema.credentialEvents).values({
    credentialId: created.id,
    actorUserId: actorUserId ?? null,
    eventType: activeCreds.length > 0 ? "REISSUED" : "ISSUED",
    details: { credentialCode: finalCode, registrationId: reg.id },
  });

  // Note: QR encodes credentialCode for reliable scanning + verification token when available
  // We encode the opaque rawToken inside the QR code; manual fallback uses credentialCode.
  const qrDataUrl = await generateQrDataUrl(rawToken);
  return { credential: created, rawToken, qrDataUrl };
}

export async function revokeCredential(
  credentialId: string,
  actorUserId: string,
  reason: string
) {
  const [updated] = await db
    .update(schema.qrCredentials)
    .set({
      status: "REVOKED",
      revokedAt: new Date(),
      revokedBy: actorUserId,
      revocationReason: reason,
    })
    .where(eq(schema.qrCredentials.id, credentialId))
    .returning();

  if (updated) {
    await db.insert(schema.credentialEvents).values({
      credentialId: updated.id,
      actorUserId,
      eventType: "REVOKED",
      details: { reason },
    });
  }

  return updated;
}

export interface VerificationResult {
  valid: boolean;
  reason:
    | "VALID"
    | "ALREADY_CHECKED_IN"
    | "NOT_FOUND"
    | "REVOKED"
    | "INELIGIBLE_REGISTRATION"
    | "PAYMENT_PENDING";
  message: string;
  credentialId?: string;
  credentialCode?: string;
  registrationId?: string;
  registrationCode?: string;
  participantName?: string;
  categoryCode?: string;
  credentialStatus?: string;
  registrationStatus?: string;
  entitlementScope?: string;
  existingCheckin?: {
    checkedInAt: string;
    venueLabel: string;
    checkinDateIst: string;
  };
}

/**
 * Verifies a scanned QR token OR manual credential/registration code.
 * Data minimization: NEVER returns medical/disability details to the scanner screen.
 */
export async function verifyCredentialInput(
  rawInput: string,
  options?: { scopeOverride?: string }
): Promise<VerificationResult> {
  const cleaned = rawInput.trim();
  if (!cleaned) {
    return { valid: false, reason: "NOT_FOUND", message: "Empty credential input." };
  }

  let credential: typeof schema.qrCredentials.$inferSelect | undefined;

  if (cleaned.startsWith("PFG27.")) {
    if (!isValidQrTokenFormat(cleaned)) {
      return {
        valid: false,
        reason: "NOT_FOUND",
        message: "Invalid or tampered QR credential signature.",
      };
    }
    const tokenHash = hashQrToken(cleaned);
    const [found] = await db
      .select()
      .from(schema.qrCredentials)
      .where(eq(schema.qrCredentials.tokenHash, tokenHash))
      .limit(1);
    credential = found;
  } else {
    // Manual ID lookup by credentialCode or registrationCode
    const [foundByCode] = await db
      .select()
      .from(schema.qrCredentials)
      .where(eq(schema.qrCredentials.credentialCode, cleaned.toUpperCase()))
      .orderBy(desc(schema.qrCredentials.issuedAt))
      .limit(1);
    credential = foundByCode;

    if (!credential) {
      const [regByCode] = await db
        .select()
        .from(schema.registrations)
        .where(eq(schema.registrations.registrationCode, cleaned.toUpperCase()))
        .limit(1);
      if (regByCode) {
        const [foundByReg] = await db
          .select()
          .from(schema.qrCredentials)
          .where(eq(schema.qrCredentials.registrationId, regByCode.id))
          .orderBy(desc(schema.qrCredentials.issuedAt))
          .limit(1);
        credential = foundByReg;
      }
    }
  }

  if (!credential) {
    return {
      valid: false,
      reason: "NOT_FOUND",
      message: "No festival credential found matching this QR code or Registration ID.",
    };
  }

  const [reg] = await db
    .select()
    .from(schema.registrations)
    .where(eq(schema.registrations.id, credential.registrationId))
    .limit(1);

  if (!reg) {
    return { valid: false, reason: "NOT_FOUND", message: "Associated registration record missing." };
  }

  if (credential.status !== "ACTIVE") {
    return {
      valid: false,
      reason: "REVOKED",
      message: `Credential is ${credential.status}${credential.revocationReason ? `: ${credential.revocationReason}` : "."}`,
      credentialId: credential.id,
      credentialCode: credential.credentialCode,
      registrationId: reg.id,
      registrationCode: reg.registrationCode,
      participantName: reg.fullName,
      categoryCode: reg.categoryCode,
      credentialStatus: credential.status,
      registrationStatus: reg.status,
    };
  }

  if (!["CONFIRMED", "APPROVED"].includes(reg.status)) {
    return {
      valid: false,
      reason: "INELIGIBLE_REGISTRATION",
      message: `Registration status is ${reg.status} and not eligible for entry.`,
      credentialId: credential.id,
      credentialCode: credential.credentialCode,
      registrationId: reg.id,
      registrationCode: reg.registrationCode,
      participantName: reg.fullName,
      categoryCode: reg.categoryCode,
      credentialStatus: credential.status,
      registrationStatus: reg.status,
    };
  }

  if (reg.paymentStatus !== "NOT_REQUIRED" && reg.paymentStatus !== "CAPTURED") {
    return {
      valid: false,
      reason: "PAYMENT_PENDING",
      message: `Payment status is ${reg.paymentStatus}. Entry requires verified payment.`,
      credentialId: credential.id,
      credentialCode: credential.credentialCode,
      registrationId: reg.id,
      registrationCode: reg.registrationCode,
      participantName: reg.fullName,
      categoryCode: reg.categoryCode,
      credentialStatus: credential.status,
      registrationStatus: reg.status,
    };
  }

  // Determine attendance scope from edition policy
  const [edition] = await db
    .select()
    .from(schema.festivalEditions)
    .where(eq(schema.festivalEditions.id, credential.editionId))
    .limit(1);

  const todayIst = getIstDateString();
  const entitlementScope =
    options?.scopeOverride ||
    (edition?.attendancePolicy === "ONCE_PER_FESTIVAL" ? "FESTIVAL" : todayIst);

  const [existingCheckin] = await db
    .select()
    .from(schema.checkins)
    .where(
      and(
        eq(schema.checkins.credentialId, credential.id),
        eq(schema.checkins.entitlementScope, entitlementScope)
      )
    )
    .limit(1);

  if (existingCheckin) {
    return {
      valid: false,
      reason: "ALREADY_CHECKED_IN",
      message: "ALREADY CHECKED IN for this attendance period.",
      credentialId: credential.id,
      credentialCode: credential.credentialCode,
      registrationId: reg.id,
      registrationCode: reg.registrationCode,
      participantName: reg.fullName,
      categoryCode: reg.categoryCode,
      credentialStatus: credential.status,
      registrationStatus: reg.status,
      entitlementScope,
      existingCheckin: {
        checkedInAt: existingCheckin.checkedInAt.toISOString(),
        venueLabel: existingCheckin.venueLabel,
        checkinDateIst: existingCheckin.checkinDateIst,
      },
    };
  }

  return {
    valid: true,
    reason: "VALID",
    message: "Valid credential. Ready to confirm entry.",
    credentialId: credential.id,
    credentialCode: credential.credentialCode,
    registrationId: reg.id,
    registrationCode: reg.registrationCode,
    participantName: reg.fullName,
    categoryCode: reg.categoryCode,
    credentialStatus: credential.status,
    registrationStatus: reg.status,
    entitlementScope,
  };
}

/**
 * Explicitly confirms a check-in with database-level duplicate prevention (unique index).
 */
export async function confirmParticipantCheckin(params: {
  credentialId: string;
  operatorUserId: string;
  venueLabel: string;
  stationId?: string | null;
  method: "QR_CAMERA" | "USB_SCANNER" | "MANUAL_ID";
  scopeOverride?: string;
}): Promise<{
  success: boolean;
  duplicate?: boolean;
  message: string;
  checkin?: typeof schema.checkins.$inferSelect;
}> {
  const [credential] = await db
    .select()
    .from(schema.qrCredentials)
    .where(eq(schema.qrCredentials.id, params.credentialId))
    .limit(1);

  if (!credential || credential.status !== "ACTIVE") {
    return { success: false, message: "Credential is not active." };
  }

  const [edition] = await db
    .select()
    .from(schema.festivalEditions)
    .where(eq(schema.festivalEditions.id, credential.editionId))
    .limit(1);

  const todayIst = getIstDateString();
  const entitlementScope =
    params.scopeOverride ||
    (edition?.attendancePolicy === "ONCE_PER_FESTIVAL" ? "FESTIVAL" : todayIst);

  try {
    const [inserted] = await db
      .insert(schema.checkins)
      .values({
        editionId: credential.editionId,
        credentialId: credential.id,
        registrationId: credential.registrationId,
        userId: credential.userId,
        operatorUserId: params.operatorUserId,
        stationId: params.stationId ?? null,
        venueLabel: params.venueLabel,
        entitlementScope,
        checkinDateIst: todayIst,
        method: params.method,
        result: "CHECKED_IN",
      })
      .returning();

    await db.insert(schema.credentialEvents).values({
      credentialId: credential.id,
      actorUserId: params.operatorUserId,
      eventType: "CHECKED_IN",
      details: {
        venueLabel: params.venueLabel,
        entitlementScope,
        method: params.method,
      },
    });

    return {
      success: true,
      message: "Participant checked in successfully.",
      checkin: inserted,
    };
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : String(err);
    const code = (err as { code?: string; cause?: { code?: string } })?.code ||
      (err as { cause?: { code?: string } })?.cause?.code;
    if (code === "23505" || msg.toLowerCase().includes("unique") || msg.toLowerCase().includes("duplicate")) {
      await db.insert(schema.credentialEvents).values({
        credentialId: credential.id,
        actorUserId: params.operatorUserId,
        eventType: "DUPLICATE_ATTEMPT",
        details: { venueLabel: params.venueLabel, entitlementScope },
      });
      return {
        success: false,
        duplicate: true,
        message: "ALREADY CHECKED IN — Duplicate check-in prevented.",
      };
    }
    throw err;
  }
}

