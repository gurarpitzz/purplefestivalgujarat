import crypto from "crypto";
import { NextRequest, NextResponse } from "next/server";
import { and, eq, gt, isNull } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { hashPassword, hashToken, recordAuditLog } from "@/lib/auth";
import { queueNotification } from "@/lib/notifications";

const requestResetSchema = z.object({
  email: z.string().trim().email("Please enter a valid email address."),
});

const completeResetSchema = z.object({
  token: z.string().trim().min(10, "Invalid password reset token."),
  newPassword: z
    .string()
    .min(8, "New password must be at least 8 characters.")
    .max(128),
});

export async function POST(req: NextRequest) {
  try {
    const body = await req.json().catch(() => null);
    const parsed = requestResetSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: parsed.error.issues[0]?.message || "Invalid email address." },
        { status: 400 }
      );
    }

    const email = parsed.data.email.toLowerCase();
    const [user] = await db
      .select()
      .from(schema.users)
      .where(and(eq(schema.users.email, email), isNull(schema.users.deletedAt)))
      .limit(1);

    if (user && user.isActive) {
      const rawToken = crypto.randomBytes(32).toString("base64url");
      const tokenHash = hashToken(rawToken);
      const expiresAt = new Date(Date.now() + 60 * 60 * 1000); // 1 hour

      // Remove existing unused password_reset tokens for this user
      await db
        .delete(schema.authAccounts)
        .where(
          and(
            eq(schema.authAccounts.userId, user.id),
            eq(schema.authAccounts.provider, "password_reset")
          )
        );

      await db.insert(schema.authAccounts).values({
        userId: user.id,
        provider: "password_reset",
        providerAccountId: user.email,
        tokenHash,
        expiresAt,
      });

      const origin =
        process.env.NEXT_PUBLIC_APP_URL ||
        req.headers.get("origin") ||
        "http://localhost:3000";
      const resetUrl = `${origin.replace(/\/$/, "")}/login?resetToken=${encodeURIComponent(rawToken)}`;

      await queueNotification({
        templateCode: "PASSWORD_RESET",
        recipientUserId: user.id,
        recipientEmail: user.email,
        variables: {
          fullName: user.fullName,
          resetUrl,
        },
        fallbackSubject: "Reset your Purple Festival Gujarat 2027 password",
      });

      const ipAddress =
        req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
        req.headers.get("x-real-ip") ||
        null;

      await recordAuditLog({
        actorUserId: user.id,
        action: "PASSWORD_RESET_REQUESTED",
        resourceType: "user",
        resourceId: user.id,
        ipAddress,
        userAgent: req.headers.get("user-agent") || null,
      });
    }

    // Always return generic success message to prevent email enumeration
    return NextResponse.json({
      success: true,
      message:
        "If an account exists for that email address, password reset instructions have been queued.",
    });
  } catch (err) {
    console.error("POST /api/auth/password-reset error:", err);
    return NextResponse.json(
      { error: "Unable to process password reset request." },
      { status: 500 }
    );
  }
}

export async function PUT(req: NextRequest) {
  try {
    const body = await req.json().catch(() => null);
    const parsed = completeResetSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: parsed.error.issues[0]?.message || "Invalid reset request." },
        { status: 400 }
      );
    }

    const tokenHash = hashToken(parsed.data.token);
    const now = new Date();

    const [resetRecord] = await db
      .select()
      .from(schema.authAccounts)
      .where(
        and(
          eq(schema.authAccounts.provider, "password_reset"),
          eq(schema.authAccounts.tokenHash, tokenHash),
          gt(schema.authAccounts.expiresAt, now)
        )
      )
      .limit(1);

    if (!resetRecord) {
      return NextResponse.json(
        { error: "This password reset link is invalid or has expired." },
        { status: 400 }
      );
    }

    const newHash = await hashPassword(parsed.data.newPassword);

    await db
      .update(schema.users)
      .set({
        passwordHash: newHash,
        failedLoginAttempts: 0,
        lockedUntil: null,
        updatedAt: new Date(),
      })
      .where(eq(schema.users.id, resetRecord.userId));

    // Revoke all existing sessions for security
    await db
      .update(schema.sessions)
      .set({ revokedAt: new Date() })
      .where(eq(schema.sessions.userId, resetRecord.userId));

    // Consume reset token
    await db
      .delete(schema.authAccounts)
      .where(eq(schema.authAccounts.id, resetRecord.id));

    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
      req.headers.get("x-real-ip") ||
      null;

    await recordAuditLog({
      actorUserId: resetRecord.userId,
      action: "PASSWORD_RESET_COMPLETED",
      resourceType: "user",
      resourceId: resetRecord.userId,
      ipAddress,
      userAgent: req.headers.get("user-agent") || null,
    });

    return NextResponse.json({
      success: true,
      message: "Your password has been updated. Please sign in with your new password.",
    });
  } catch (err) {
    console.error("PUT /api/auth/password-reset error:", err);
    return NextResponse.json(
      { error: "Unable to reset password right now." },
      { status: 500 }
    );
  }
}
