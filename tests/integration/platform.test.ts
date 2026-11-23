import { describe, it, expect, beforeAll, vi } from "vitest";
import { authenticator } from "otplib";
import { eq } from "drizzle-orm";

// Force isolated in-memory PostgreSQL (PGlite) BEFORE any @/db module evaluation
vi.hoisted(() => {
  process.env.PGLITE_DATA_DIR = "memory://";
  delete process.env.DATABASE_URL;
  process.env.AUTH_SECRET = "test-auth-secret-0123456789abcdef0123456789abcdef";
  process.env.QR_SIGNING_SECRET = "test-qr-secret-0123456789abcdef0123456789abcdef";
});

import { db, schema } from "@/db";
import { runMigrations } from "@/db/migrate";
import { seedDatabase } from "@/db/seed";
import {
  hashPassword,
  verifyPassword,
  generateMfaSecret,
  verifyMfaToken,
  getUserRolesAndPermissions,
  hasPermission,
  type AuthenticatedUser,
} from "@/lib/auth";
import {
  generateOpaqueQrToken,
  isValidQrTokenFormat,
  hashQrToken,
  generateUniqueRegistrationCode,
  issueCredentialForRegistration,
  revokeCredential,
  verifyCredentialInput,
  confirmParticipantCheckin,
} from "@/lib/qr";
import { generateAccessiblePassPdf } from "@/lib/pass-pdf";
import { baseRegistrationSchema } from "@/lib/validation";
import { PERMISSIONS } from "@/lib/constants";

let editionId: string;
let operatorUserId: string;
let categoryIdByCode: Record<string, string> = {};
let roleIdByCode: Record<string, string> = {};

async function createTestUser(params: {
  email: string;
  fullName: string;
  roleCode: "SUPERADMIN" | "ADMIN" | "RGST" | "PARTICIPANT";
}) {
  const passwordHash = await hashPassword("PurpleFest@2027!");
  const [user] = await db
    .insert(schema.users)
    .values({
      email: params.email,
      fullName: params.fullName,
      passwordHash,
      phone: "+91 98250 12345",
      country: "India",
      isActive: true,
    })
    .returning();

  const roleId = roleIdByCode[params.roleCode];
  if (roleId) {
    await db
      .insert(schema.userRoles)
      .values({ userId: user.id, roleId })
      .onConflictDoNothing();
  }

  return user;
}

async function createTestRegistration(params: {
  userId: string;
  fullName: string;
  email: string;
  categoryCode: string;
  status: "SUBMITTED" | "CONFIRMED" | "APPROVED" | "REJECTED";
  paymentStatus?: "NOT_REQUIRED" | "PENDING" | "CAPTURED";
  isPersonWithDisability?: "YES" | "NO" | "PREFER_NOT_TO_SAY";
}) {
  const registrationCode = await generateUniqueRegistrationCode();
  const categoryId = categoryIdByCode[params.categoryCode];
  const [reg] = await db
    .insert(schema.registrations)
    .values({
      registrationCode,
      editionId,
      userId: params.userId,
      categoryId,
      categoryCode: params.categoryCode,
      status: params.status,
      paymentStatus: params.paymentStatus ?? "NOT_REQUIRED",
      fullName: params.fullName,
      email: params.email,
      phone: "+91 98250 54321",
      country: "India",
      occupation: "Academic",
      isPersonWithDisability: params.isPersonWithDisability ?? "PREFER_NOT_TO_SAY",
      attendingDays: ["DAY_1", "DAY_2"],
      interestedTracks: ["Inclusive Education"],
    })
    .returning();

  return reg;
}

beforeAll(async () => {
  expect(process.env.PGLITE_DATA_DIR).toBe("memory://");
  await runMigrations();
  const seeded = await seedDatabase();
  editionId = seeded.editionId;

  const categories = await db.select().from(schema.registrationCategories);
  for (const cat of categories) {
    categoryIdByCode[cat.code] = cat.id;
  }

  const roles = await db.select().from(schema.roles);
  for (const r of roles) {
    roleIdByCode[r.code] = r.id;
  }

  const rgstOperator = await createTestUser({
    email: "rgst.desk1@purplefestivalgujarat.test",
    fullName: "Desk Operator One",
    roleCode: "RGST",
  });
  operatorUserId = rgstOperator.id;
});

describe("1. Registration Validation", () => {
  const validBasePayload = {
    fullName: "Aarav Patel",
    email: "aarav.patel@example.com",
    phone: "+91 98250 12345",
    country: "India",
    categoryCode: "ATTENDEE",
    occupation: "Student",
    isPersonWithDisability: "PREFER_NOT_TO_SAY" as const,
    consentZeroTolerance: true as const,
    consentDataProcessing: true as const,
    consentMediaPhotography: false,
  };

  it("rejects missing or invalid required fields", () => {
    const missingName = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      fullName: "A",
    });
    expect(missingName.success).toBe(false);

    const badEmail = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      email: "not-an-email",
    });
    expect(badEmail.success).toBe(false);

    const missingOccupation = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      occupation: "",
    });
    expect(missingOccupation.success).toBe(false);
  });

  it("validates international phone numbers properly", () => {
    for (const goodPhone of ["+91 98250 12345", "+44 (20) 7946-0958", "+1-415-555-0199", "9825012345"]) {
      const res = baseRegistrationSchema.safeParse({ ...validBasePayload, phone: goodPhone });
      expect(res.success).toBe(true);
    }

    for (const badPhone of ["123", "abc-def-ghij", "++91999999999999999999999"]) {
      const res = baseRegistrationSchema.safeParse({ ...validBasePayload, phone: badPhone });
      expect(res.success).toBe(false);
    }
  });

  it("separates mandatory zero-tolerance/data consents from optional media photography consent", () => {
    // Optional media consent = false must still pass
    const withoutMedia = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      consentMediaPhotography: false,
    });
    expect(withoutMedia.success).toBe(true);
    if (withoutMedia.success) {
      expect(withoutMedia.data.consentMediaPhotography).toBe(false);
    }

    // Missing mandatory zero-tolerance consent must fail
    const noZeroTolerance = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      consentZeroTolerance: false,
    });
    expect(noZeroTolerance.success).toBe(false);

    // Missing mandatory data processing consent must fail
    const noDataConsent = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      consentDataProcessing: false,
    });
    expect(noDataConsent.success).toBe(false);
  });

  it("keeps disability status completely independent of registration category", () => {
    const pwdVolunteer = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      categoryCode: "VOLUNTEER",
      isPersonWithDisability: "YES",
      disabilityCategoryCodes: ["LOW_VISION", "LOCOMOTOR_DISABILITY"],
      accommodationTypes: ["LARGE_PRINT", "WHEELCHAIR_SEATING"],
    });
    expect(pwdVolunteer.success).toBe(true);

    const pwdSli = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      categoryCode: "SLI",
      isPersonWithDisability: "YES",
      disabilityCategoryCodes: ["HEARING_IMPAIRMENT"],
    });
    expect(pwdSli.success).toBe(true);

    const nonPwdDelegate = baseRegistrationSchema.safeParse({
      ...validBasePayload,
      categoryCode: "DELEGATE",
      isPersonWithDisability: "NO",
    });
    expect(nonPwdDelegate.success).toBe(true);
  });
});

describe("2. Auth, TOTP MFA & RBAC Permission Matrix", () => {
  it("hashes and verifies passwords using bcrypt", async () => {
    const raw = "Vadodara@Purple2027!";
    const hash = await hashPassword(raw);

    expect(hash).not.toBe(raw);
    expect(hash.startsWith("$2")).toBe(true);
    expect(await verifyPassword(raw, hash)).toBe(true);
    expect(await verifyPassword("WrongPassword!123", hash)).toBe(false);
  });

  it("generates TOTP MFA secret and verifies valid/invalid OTP codes", () => {
    const { secret, otpauthUrl } = generateMfaSecret("admin@purplefestivalgujarat.com");
    expect(secret.length).toBeGreaterThanOrEqual(16);
    expect(otpauthUrl).toContain("otpauth://totp/");
    expect(otpauthUrl).toContain("Purple%20Festival%20Gujarat%202027");

    const validCode = authenticator.generate(secret);
    expect(verifyMfaToken(validCode, secret)).toBe(true);
    expect(verifyMfaToken("000000", secret)).toBe(false);
  });

  it("enforces strict RBAC permission matrix across SUPERADMIN, ADMIN, RGST, and PARTICIPANT", async () => {
    const superadmin = await createTestUser({
      email: "superadmin.rbac@purplefestivalgujarat.test",
      fullName: "Superadmin User",
      roleCode: "SUPERADMIN",
    });
    const admin = await createTestUser({
      email: "admin.rbac@purplefestivalgujarat.test",
      fullName: "Admin User",
      roleCode: "ADMIN",
    });
    const rgst = await createTestUser({
      email: "rgst.rbac@purplefestivalgujarat.test",
      fullName: "RGST User",
      roleCode: "RGST",
    });
    const participant = await createTestUser({
      email: "participant.rbac@purplefestivalgujarat.test",
      fullName: "Participant User",
      roleCode: "PARTICIPANT",
    });

    const superPerms = await getUserRolesAndPermissions(superadmin.id);
    const adminPerms = await getUserRolesAndPermissions(admin.id);
    const rgstPerms = await getUserRolesAndPermissions(rgst.id);
    const partPerms = await getUserRolesAndPermissions(participant.id);

    const toAuthUser = (
      id: string,
      email: string,
      fullName: string,
      rp: Awaited<ReturnType<typeof getUserRolesAndPermissions>>
    ): AuthenticatedUser => ({
      id,
      email,
      fullName,
      phone: null,
      country: "India",
      roles: rp.roles,
      permissions: rp.permissions,
      mfaEnabled: false,
      mfaVerified: false,
      sessionId: "test-session",
    });

    const superAuth = toAuthUser(superadmin.id, superadmin.email, superadmin.fullName, superPerms);
    const adminAuth = toAuthUser(admin.id, admin.email, admin.fullName, adminPerms);
    const rgstAuth = toAuthUser(rgst.id, rgst.email, rgst.fullName, rgstPerms);
    const partAuth = toAuthUser(participant.id, participant.email, participant.fullName, partPerms);

    // SUPERADMIN has admin_accounts.manage and all permissions
    expect(hasPermission(superAuth, PERMISSIONS.ADMIN_ACCOUNTS_MANAGE)).toBe(true);
    expect(hasPermission(superAuth, PERMISSIONS.CMS_MANAGE)).toBe(true);
    expect(hasPermission(superAuth, PERMISSIONS.AUDIT_LOGS_READ)).toBe(true);

    // ADMIN does NOT have admin_accounts.manage, but has operational permissions
    expect(hasPermission(adminAuth, PERMISSIONS.ADMIN_ACCOUNTS_MANAGE)).toBe(false);
    expect(hasPermission(adminAuth, PERMISSIONS.REGISTRATIONS_READ_ALL)).toBe(true);
    expect(hasPermission(adminAuth, PERMISSIONS.ACCOMMODATIONS_READ)).toBe(true);
    expect(hasPermission(adminAuth, PERMISSIONS.VOLUNTEERS_REVIEW)).toBe(true);

    // RGST has ONLY qr.scan, checkins.confirm, attendance.read.own (+ account.read.own)
    expect(hasPermission(rgstAuth, PERMISSIONS.QR_SCAN)).toBe(true);
    expect(hasPermission(rgstAuth, PERMISSIONS.CHECKINS_CONFIRM)).toBe(true);
    expect(hasPermission(rgstAuth, PERMISSIONS.ATTENDANCE_READ_OWN)).toBe(true);
    expect(hasPermission(rgstAuth, PERMISSIONS.REGISTRATIONS_READ_ALL)).toBe(false);
    expect(hasPermission(rgstAuth, PERMISSIONS.CMS_MANAGE)).toBe(false);
    expect(hasPermission(rgstAuth, PERMISSIONS.ACCOMMODATIONS_READ)).toBe(false);
    expect(hasPermission(rgstAuth, PERMISSIONS.ADMIN_ACCOUNTS_MANAGE)).toBe(false);
    expect(hasPermission(rgstAuth, PERMISSIONS.ATTENDANCE_READ_FULL)).toBe(false);

    // PARTICIPANT has ONLY account.read.own
    expect(partPerms.permissions).toEqual([PERMISSIONS.ACCOUNT_READ_OWN]);
    expect(hasPermission(partAuth, PERMISSIONS.ACCOUNT_READ_OWN)).toBe(true);
    expect(hasPermission(partAuth, PERMISSIONS.QR_SCAN)).toBe(false);
    expect(hasPermission(partAuth, PERMISSIONS.REGISTRATIONS_READ_ALL)).toBe(false);
  });
});

describe("3. QR Cryptography & Security", () => {
  it("generates opaque signed tokens in PFG27.<nonce>.<sig> format with ZERO PII", () => {
    const token = generateOpaqueQrToken();
    const parts = token.split(".");

    expect(parts).toHaveLength(3);
    expect(parts[0]).toBe("PFG27");
    expect(parts[1].length).toBeGreaterThanOrEqual(24);
    expect(parts[2].length).toBe(16);
    expect(isValidQrTokenFormat(token)).toBe(true);

    // Must not contain any PII markers
    expect(token).not.toMatch(/@|disability|blind|wheelchair|patel|gmail/i);
  });

  it("rejects tampered tokens and stores only SHA-256 HMAC tokenHash in DB", async () => {
    const user = await createTestUser({
      email: "crypto.attendee@purplefestivalgujarat.test",
      fullName: "Meera Shah",
      roleCode: "PARTICIPANT",
    });
    const reg = await createTestRegistration({
      userId: user.id,
      fullName: user.fullName,
      email: user.email,
      categoryCode: "ATTENDEE",
      status: "CONFIRMED",
      isPersonWithDisability: "YES",
    });

    const { credential, rawToken } = await issueCredentialForRegistration(reg.id);

    // Raw token is NOT stored in tokenHash; SHA-256 HMAC hash is stored
    expect(credential.tokenHash).not.toBe(rawToken);
    expect(credential.tokenHash).toBe(hashQrToken(rawToken));
    expect(credential.tokenHash).toMatch(/^[a-f0-9]{64}$/);
    expect(rawToken).not.toContain("Meera");
    expect(rawToken).not.toContain(user.email);

    // Tampered signature must fail format validation and verification
    const [prefix, nonce, sig] = rawToken.split(".");
    const tamperedSig = sig.slice(0, -1) + (sig.endsWith("A") ? "B" : "A");
    const tamperedToken = `${prefix}.${nonce}.${tamperedSig}`;

    expect(isValidQrTokenFormat(tamperedToken)).toBe(false);
    const verifyTampered = await verifyCredentialInput(tamperedToken);
    expect(verifyTampered.valid).toBe(false);
    expect(verifyTampered.reason).toBe("NOT_FOUND");
  });

  it("refuses to issue an active QR credential to an ineligible SUBMITTED volunteer", async () => {
    const user = await createTestUser({
      email: "ineligible.vol@purplefestivalgujarat.test",
      fullName: "Rohan Desai",
      roleCode: "PARTICIPANT",
    });
    const reg = await createTestRegistration({
      userId: user.id,
      fullName: user.fullName,
      email: user.email,
      categoryCode: "VOLUNTEER",
      status: "SUBMITTED",
    });

    await expect(issueCredentialForRegistration(reg.id)).rejects.toThrow(
      /Cannot issue QR credential while registration status is SUBMITTED/i
    );
  });
});

describe("4. End-to-End QR Check-in, Concurrency, Revocation & Reissue", () => {
  it("verifies confirmed attendee with data minimization, checks in with IST date, and blocks duplicate scan", async () => {
    const user = await createTestUser({
      email: "checkin.attendee@purplefestivalgujarat.test",
      fullName: "Kavita Joshi",
      roleCode: "PARTICIPANT",
    });
    const reg = await createTestRegistration({
      userId: user.id,
      fullName: user.fullName,
      email: user.email,
      categoryCode: "ATTENDEE",
      status: "CONFIRMED",
      isPersonWithDisability: "YES",
    });

    // Attach sensitive accommodation request to ensure scanner NEVER exposes it
    await db.insert(schema.accommodationRequests).values({
      registrationId: reg.id,
      userId: user.id,
      selectedTypes: ["ISL_INTERPRETER", "WHEELCHAIR_SEATING"],
      freeTextDetails: "Requires front-row seating and ISL interpreter.",
    });

    const { credential, rawToken } = await issueCredentialForRegistration(reg.id);
    expect(credential.credentialCode).toMatch(/^PFG-2027-/);

    // 1. First scan returns valid: true with minimal fields and ZERO disability/medical data
    const scan1 = await verifyCredentialInput(rawToken);
    expect(scan1.valid).toBe(true);
    expect(scan1.reason).toBe("VALID");
    expect(scan1.participantName).toBe("Kavita Joshi");
    expect(scan1.categoryCode).toBe("ATTENDEE");
    expect(scan1.credentialCode).toBe(credential.credentialCode);

    const serializedScan = JSON.stringify(scan1).toLowerCase();
    expect(serializedScan).not.toContain("disability");
    expect(serializedScan).not.toContain("accommodation");
    expect(serializedScan).not.toContain("isl_interpreter");
    expect(serializedScan).not.toContain("wheelchair");

    // 2. Confirm check-in records IST date (YYYY-MM-DD)
    const confirmed = await confirmParticipantCheckin({
      credentialId: credential.id,
      operatorUserId,
      venueLabel: "Sama Indoor Stadium — Main Gate A",
      method: "QR_CAMERA",
    });
    expect(confirmed.success).toBe(true);
    expect(confirmed.checkin?.checkinDateIst).toMatch(/^\d{4}-\d{2}-\d{2}$/);

    // 3. Second scan returns ALREADY_CHECKED_IN (valid: false)
    const scan2 = await verifyCredentialInput(rawToken);
    expect(scan2.valid).toBe(false);
    expect(scan2.reason).toBe("ALREADY_CHECKED_IN");
    expect(scan2.existingCheckin?.venueLabel).toBe("Sama Indoor Stadium — Main Gate A");
  });

  it("enforces DB unique constraint under concurrent check-in race condition", async () => {
    const user = await createTestUser({
      email: "race.attendee@purplefestivalgujarat.test",
      fullName: "Nikhil Mehta",
      roleCode: "PARTICIPANT",
    });
    const reg = await createTestRegistration({
      userId: user.id,
      fullName: user.fullName,
      email: user.email,
      categoryCode: "DELEGATE",
      status: "CONFIRMED",
    });

    const { credential } = await issueCredentialForRegistration(reg.id);

    const [r1, r2] = await Promise.all([
      confirmParticipantCheckin({
        credentialId: credential.id,
        operatorUserId,
        venueLabel: "Gate A",
        method: "QR_CAMERA",
      }),
      confirmParticipantCheckin({
        credentialId: credential.id,
        operatorUserId,
        venueLabel: "Gate B",
        method: "QR_CAMERA",
      }),
    ]);

    const successes = [r1, r2].filter((r) => r.success);
    const duplicates = [r1, r2].filter((r) => !r.success && r.duplicate);

    expect(successes).toHaveLength(1);
    expect(duplicates).toHaveLength(1);

    const rows = await db
      .select()
      .from(schema.checkins)
      .where(eq(schema.checkins.credentialId, credential.id));
    expect(rows).toHaveLength(1);
  });

  it("immediately invalidates a revoked credential and supports safe reissuance", async () => {
    const user = await createTestUser({
      email: "revoke.reissue@purplefestivalgujarat.test",
      fullName: "Priya Nair",
      roleCode: "PARTICIPANT",
    });
    const reg = await createTestRegistration({
      userId: user.id,
      fullName: user.fullName,
      email: user.email,
      categoryCode: "ATTENDEE",
      status: "CONFIRMED",
    });

    // Issue initial credential and revoke it
    const first = await issueCredentialForRegistration(reg.id);
    expect((await verifyCredentialInput(first.rawToken)).valid).toBe(true);

    await revokeCredential(first.credential.id, operatorUserId, "Lost phone at venue");
    const afterRevoke = await verifyCredentialInput(first.rawToken);
    expect(afterRevoke.valid).toBe(false);
    expect(afterRevoke.reason).toBe("REVOKED");

    // Reissue a second credential, then reissue a third to verify automatic old-credential revocation
    const second = await issueCredentialForRegistration(reg.id, operatorUserId);
    expect((await verifyCredentialInput(second.rawToken)).valid).toBe(true);

    const third = await issueCredentialForRegistration(reg.id, operatorUserId);
    const secondVerify = await verifyCredentialInput(second.rawToken);
    expect(secondVerify.valid).toBe(false);
    expect(secondVerify.reason).toBe("REVOKED");

    const thirdVerify = await verifyCredentialInput(third.rawToken);
    expect(thirdVerify.valid).toBe(true);
    expect(thirdVerify.reason).toBe("VALID");
  });
});

describe("5. Accessible PDF Pass Generation", () => {
  it("generates a valid %PDF- binary document with QR pass details", async () => {
    const token = generateOpaqueQrToken();
    const pdfBytes = await generateAccessiblePassPdf({
      participantName: "Ananya Trivedi",
      registrationCode: "PFG-2027-000042-AB12",
      credentialCode: "PFG-2027-000042-AB12",
      categoryName: "General Attendee",
      status: "CONFIRMED",
      qrPayload: token,
      roleAssignment: "Main Auditorium — Hospitality Desk",
    });

    expect(pdfBytes).toBeInstanceOf(Uint8Array);
    expect(pdfBytes.byteLength).toBeGreaterThan(1500);
    const header = Buffer.from(pdfBytes.slice(0, 5)).toString("ascii");
    expect(header).toBe("%PDF-");
  });
});

describe("6. Volunteer & SLI Approval Lifecycle", () => {
  it("requires APPROVED status before Volunteer credential can be issued", async () => {
    const volUser = await createTestUser({
      email: "volunteer.flow@purplefestivalgujarat.test",
      fullName: "Devansh Parmar",
      roleCode: "PARTICIPANT",
    });
    const volReg = await createTestRegistration({
      userId: volUser.id,
      fullName: volUser.fullName,
      email: volUser.email,
      categoryCode: "VOLUNTEER",
      status: "SUBMITTED",
    });

    // Cannot issue while SUBMITTED
    await expect(issueCredentialForRegistration(volReg.id)).rejects.toThrow(/SUBMITTED/);

    // Approve Volunteer
    await db
      .update(schema.registrations)
      .set({ status: "APPROVED", reviewedAt: new Date() })
      .where(eq(schema.registrations.id, volReg.id));

    const issued = await issueCredentialForRegistration(volReg.id, operatorUserId);
    expect(issued.credential.status).toBe("ACTIVE");
    const verified = await verifyCredentialInput(issued.rawToken);
    expect(verified.valid).toBe(true);
    expect(verified.categoryCode).toBe("VOLUNTEER");
  });

  it("requires APPROVED status before SLI credential can be issued", async () => {
    const sliUser = await createTestUser({
      email: "sli.flow@purplefestivalgujarat.test",
      fullName: "Neha Kulkarni",
      roleCode: "PARTICIPANT",
    });
    const sliReg = await createTestRegistration({
      userId: sliUser.id,
      fullName: sliUser.fullName,
      email: sliUser.email,
      categoryCode: "SLI",
      status: "SUBMITTED",
    });

    // Cannot issue while SUBMITTED
    await expect(issueCredentialForRegistration(sliReg.id)).rejects.toThrow(/SUBMITTED/);

    // Approve SLI
    await db
      .update(schema.registrations)
      .set({ status: "APPROVED", reviewedAt: new Date() })
      .where(eq(schema.registrations.id, sliReg.id));

    const issued = await issueCredentialForRegistration(sliReg.id, operatorUserId);
    expect(issued.credential.status).toBe("ACTIVE");
    const verified = await verifyCredentialInput(issued.rawToken);
    expect(verified.valid).toBe(true);
    expect(verified.categoryCode).toBe("SLI");
  });
});
