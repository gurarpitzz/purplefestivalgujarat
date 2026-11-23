import { NextRequest, NextResponse } from "next/server";
import { and, eq, gt } from "drizzle-orm";
import QRCode from "qrcode";
import { z } from "zod";
import { db, schema } from "@/db";
import {
  generateMfaSecret,
  getCurrentUser,
  recordAuditLog,
  verifyMfaToken,
} from "@/lib/auth";

const mfaVerifySchema = z.object({
  code: z.string().trim().min(6, "Please enter the 6-digit authenticator code.").max(10),
  action: z.enum(["enable", "disable"]).default("enable"),
});

export async function GET() {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const { secret, otpauthUrl } = generateMfaSecret(user.email);
    const qrDataUrl = await QRCode.toDataURL(otpauthUrl, {
      errorCorrectionLevel: "M",
      margin: 2,
      width: 240,
      color: {
        dark: "#17151C",
        light: "#F9F7FC",
      },
    });

    // Clear previous pending MFA setup tokens for this user
    await db
      .delete(schema.authAccounts)
      .where(
        and(
          eq(schema.authAccounts.userId, user.id),
          eq(schema.authAccounts.provider, "mfa_setup")
        )
      );

    await db.insert(schema.authAccounts).values({
      userId: user.id,
      provider: "mfa_setup",
      providerAccountId: user.email,
      tokenHash: secret,
      expiresAt: new Date(Date.now() + 15 * 60 * 1000),
    });

    return NextResponse.json({
      mfaEnabled: user.mfaEnabled,
      secret,
      otpauthUrl,
      qrDataUrl,
    });
  } catch (err) {
    console.error("GET /api/auth/mfa error:", err);
    return NextResponse.json(
      { error: "Unable to initialize MFA setup." },
      { status: 500 }
    );
  }
}

export async function POST(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const body = await req.json().catch(() => null);
    const parsed = mfaVerifySchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: parsed.error.issues[0]?.message || "Invalid MFA verification code." },
        { status: 400 }
      );
    }

    const cleanedCode = parsed.data.code.replace(/\s+/g, "");
    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
      req.headers.get("x-real-ip") ||
      null;
    const userAgent = req.headers.get("user-agent") || null;

    if (parsed.data.action === "disable") {
      const [dbUser] = await db
        .select()
        .from(schema.users)
        .where(eq(schema.users.id, user.id))
        .limit(1);

      if (!dbUser?.mfaEnabled || !dbUser.mfaSecretEncrypted) {
        return NextResponse.json({ error: "MFA is not currently enabled." }, { status: 400 });
      }

      if (!verifyMfaToken(cleanedCode, dbUser.mfaSecretEncrypted)) {
        return NextResponse.json(
          { error: "Invalid authenticator code. Could not disable MFA." },
          { status: 400 }
        );
      }

      await db
        .update(schema.users)
        .set({
          mfaEnabled: false,
          mfaSecretEncrypted: null,
          updatedAt: new Date(),
        })
        .where(eq(schema.users.id, user.id));

      await recordAuditLog({
        actorUserId: user.id,
        action: "MFA_DISABLED",
        resourceType: "user",
        resourceId: user.id,
        ipAddress,
        userAgent,
      });

      return NextResponse.json({ success: true, mfaEnabled: false });
    }

    const now = new Date();
    const [pendingSetup] = await db
      .select()
      .from(schema.authAccounts)
      .where(
        and(
          eq(schema.authAccounts.userId, user.id),
          eq(schema.authAccounts.provider, "mfa_setup"),
          gt(schema.authAccounts.expiresAt, now)
        )
      )
      .limit(1);

    if (!pendingSetup?.tokenHash) {
      return NextResponse.json(
        { error: "MFA setup session expired. Please generate a new QR code." },
        { status: 400 }
      );
    }

    const valid = verifyMfaToken(cleanedCode, pendingSetup.tokenHash);
    if (!valid) {
      return NextResponse.json(
        { error: "Invalid verification code. Please check your authenticator app." },
        { status: 400 }
      );
    }

    await db
      .update(schema.users)
      .set({
        mfaEnabled: true,
        mfaSecretEncrypted: pendingSetup.tokenHash,
        updatedAt: new Date(),
      })
      .where(eq(schema.users.id, user.id));

    await db
      .update(schema.sessions)
      .set({ mfaVerified: true })
      .where(eq(schema.sessions.id, user.sessionId));

    await db
      .delete(schema.authAccounts)
      .where(eq(schema.authAccounts.id, pendingSetup.id));

    await recordAuditLog({
      actorUserId: user.id,
      action: "MFA_ENABLED",
      resourceType: "user",
      resourceId: user.id,
      ipAddress,
      userAgent,
    });

    return NextResponse.json({
      success: true,
      mfaEnabled: true,
      message: "Multi-factor authentication is now active on your account.",
    });
  } catch (err) {
    console.error("POST /api/auth/mfa error:", err);
    return NextResponse.json(
      { error: "Unable to verify MFA code." },
      { status: 500 }
    );
  }
}
