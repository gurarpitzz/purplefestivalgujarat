import { NextRequest, NextResponse } from "next/server";
import { eq } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import {
  getCurrentUser,
  hashPassword,
  recordAuditLog,
  verifyPassword,
} from "@/lib/auth";
import { internationalPhoneRegex } from "@/lib/validation";

const updateProfileSchema = z.object({
  fullName: z.string().trim().min(2, "Full name must be at least 2 characters.").max(200).optional(),
  phone: z
    .string()
    .trim()
    .regex(internationalPhoneRegex, "Please enter a valid phone number.")
    .optional()
    .or(z.literal("")),
  country: z.string().trim().min(2).max(100).optional(),
  currentPassword: z.string().optional(),
  newPassword: z
    .string()
    .min(8, "New password must be at least 8 characters.")
    .max(128)
    .optional(),
});

export async function PATCH(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const body = await req.json().catch(() => null);
    const parsed = updateProfileSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        {
          error: parsed.error.issues[0]?.message || "Invalid profile update.",
          issues: parsed.error.issues,
        },
        { status: 400 }
      );
    }

    const data = parsed.data;
    const updates: Partial<typeof schema.users.$inferInsert> = {
      updatedAt: new Date(),
    };

    if (data.fullName !== undefined) updates.fullName = data.fullName;
    if (data.phone !== undefined) updates.phone = data.phone || null;
    if (data.country !== undefined) updates.country = data.country;

    let passwordChanged = false;
    if (data.newPassword) {
      const [dbUser] = await db
        .select()
        .from(schema.users)
        .where(eq(schema.users.id, user.id))
        .limit(1);

      if (dbUser?.passwordHash) {
        if (!data.currentPassword) {
          return NextResponse.json(
            { error: "Please enter your current password to set a new password." },
            { status: 400 }
          );
        }
        const ok = await verifyPassword(data.currentPassword, dbUser.passwordHash);
        if (!ok) {
          return NextResponse.json(
            { error: "Current password is incorrect." },
            { status: 400 }
          );
        }
      }

      updates.passwordHash = await hashPassword(data.newPassword);
      passwordChanged = true;
    }

    const [updated] = await db
      .update(schema.users)
      .set(updates)
      .where(eq(schema.users.id, user.id))
      .returning();

    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
      req.headers.get("x-real-ip") ||
      null;

    await recordAuditLog({
      actorUserId: user.id,
      action: passwordChanged ? "PASSWORD_CHANGED" : "PROFILE_UPDATED",
      resourceType: "user",
      resourceId: user.id,
      ipAddress,
      userAgent: req.headers.get("user-agent") || null,
      metadata: {
        updatedFields: Object.keys(updates).filter((k) => k !== "passwordHash"),
        passwordChanged,
      },
    });

    return NextResponse.json({
      success: true,
      user: {
        id: updated.id,
        email: updated.email,
        fullName: updated.fullName,
        phone: updated.phone,
        country: updated.country,
        mfaEnabled: updated.mfaEnabled,
      },
      message: passwordChanged
        ? "Your password and profile have been updated."
        : "Your profile has been updated.",
    });
  } catch (err) {
    console.error("PATCH /api/account/profile error:", err);
    return NextResponse.json(
      { error: "Unable to update profile right now." },
      { status: 500 }
    );
  }
}
