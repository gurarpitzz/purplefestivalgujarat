import { NextRequest, NextResponse } from "next/server";
import { and, eq, isNull } from "drizzle-orm";
import { db, schema } from "@/db";
import {
  createSession,
  getPortalPathForRoles,
  getUserRolesAndPermissions,
  recordAuditLog,
  verifyMfaToken,
  verifyPassword,
} from "@/lib/auth";
import { loginSchema } from "@/lib/validation";

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_DURATION_MS = 15 * 60 * 1000; // 15 minutes

function getClientInfo(req: NextRequest) {
  const ipAddress =
    req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
    req.headers.get("x-real-ip") ||
    null;
  const userAgent = req.headers.get("user-agent") || null;
  return { ipAddress, userAgent };
}

export async function POST(req: NextRequest) {
  try {
    const body = await req.json().catch(() => null);
    const parsed = loginSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        {
          error: parsed.error.issues[0]?.message || "Invalid login input.",
          issues: parsed.error.issues,
        },
        { status: 400 }
      );
    }

    const email = parsed.data.email.toLowerCase();
    const { password, mfaCode } = parsed.data;
    const { ipAddress, userAgent } = getClientInfo(req);

    const [user] = await db
      .select()
      .from(schema.users)
      .where(and(eq(schema.users.email, email), isNull(schema.users.deletedAt)))
      .limit(1);

    if (!user || !user.isActive || !user.passwordHash) {
      return NextResponse.json(
        { error: "Invalid email address or password." },
        { status: 401 }
      );
    }

    const now = new Date();
    if (user.lockedUntil && user.lockedUntil > now) {
      const minutesRemaining = Math.max(
        1,
        Math.ceil((user.lockedUntil.getTime() - now.getTime()) / 60000)
      );
      return NextResponse.json(
        {
          error: `Account temporarily locked due to multiple failed sign-in attempts. Please try again in ${minutesRemaining} minute${minutesRemaining === 1 ? "" : "s"}.`,
          lockedUntil: user.lockedUntil.toISOString(),
        },
        { status: 423 }
      );
    }

    const passwordValid = await verifyPassword(password, user.passwordHash);
    if (!passwordValid) {
      const nextAttempts = (user.failedLoginAttempts ?? 0) + 1;
      const shouldLock = nextAttempts >= MAX_FAILED_ATTEMPTS;
      const lockedUntil = shouldLock
        ? new Date(Date.now() + LOCKOUT_DURATION_MS)
        : null;

      await db
        .update(schema.users)
        .set({
          failedLoginAttempts: nextAttempts,
          lockedUntil,
          updatedAt: new Date(),
        })
        .where(eq(schema.users.id, user.id));

      await recordAuditLog({
        actorUserId: user.id,
        action: shouldLock ? "LOGIN_ACCOUNT_LOCKED" : "LOGIN_FAILED",
        resourceType: "user",
        resourceId: user.id,
        ipAddress,
        userAgent,
        metadata: { failedAttempts: nextAttempts },
      });

      if (shouldLock) {
        return NextResponse.json(
          {
            error:
              "Too many failed sign-in attempts. Your account has been locked for 15 minutes for security.",
          },
          { status: 423 }
        );
      }

      return NextResponse.json(
        { error: "Invalid email address or password." },
        { status: 401 }
      );
    }

    // MFA TOTP check when enabled
    if (user.mfaEnabled && user.mfaSecretEncrypted) {
      const cleanedCode = mfaCode?.replace(/\s+/g, "") || "";
      if (!cleanedCode) {
        return NextResponse.json(
          {
            mfaRequired: true,
            message:
              "Multi-factor authentication is enabled. Please enter your 6-digit authenticator code.",
          },
          { status: 200 }
        );
      }

      const mfaOk = verifyMfaToken(cleanedCode, user.mfaSecretEncrypted);
      if (!mfaOk) {
        await recordAuditLog({
          actorUserId: user.id,
          action: "LOGIN_MFA_FAILED",
          resourceType: "user",
          resourceId: user.id,
          ipAddress,
          userAgent,
        });
        return NextResponse.json(
          {
            error: "Invalid authenticator code. Please try again.",
            mfaRequired: true,
          },
          { status: 401 }
        );
      }
    }

    await db
      .update(schema.users)
      .set({
        failedLoginAttempts: 0,
        lockedUntil: null,
        lastLoginAt: new Date(),
        updatedAt: new Date(),
      })
      .where(eq(schema.users.id, user.id));

    await createSession(user.id, {
      ipAddress: ipAddress ?? undefined,
      userAgent: userAgent ?? undefined,
      mfaVerified: Boolean(user.mfaEnabled),
    });

    const { roles, permissions } = await getUserRolesAndPermissions(user.id);
    const redirectTo = getPortalPathForRoles(roles);

    await recordAuditLog({
      actorUserId: user.id,
      action: "LOGIN_SUCCESS",
      resourceType: "session",
      resourceId: user.id,
      ipAddress,
      userAgent,
      metadata: { roles, mfaVerified: Boolean(user.mfaEnabled) },
    });

    return NextResponse.json({
      user: {
        id: user.id,
        email: user.email,
        fullName: user.fullName,
        phone: user.phone,
        country: user.country,
        roles,
        permissions,
        mfaEnabled: user.mfaEnabled,
      },
      redirectTo,
    });
  } catch (err) {
    console.error("POST /api/auth/login error:", err);
    return NextResponse.json(
      { error: "Unable to complete sign-in right now. Please try again." },
      { status: 500 }
    );
  }
}
