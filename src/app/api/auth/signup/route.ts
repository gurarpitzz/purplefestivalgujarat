import { NextRequest, NextResponse } from "next/server";
import { eq } from "drizzle-orm";
import { db, schema } from "@/db";
import {
  createSession,
  getUserRolesAndPermissions,
  hashPassword,
  recordAuditLog,
} from "@/lib/auth";
import { signupSchema } from "@/lib/validation";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json().catch(() => null);
    const parsed = signupSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        {
          error: parsed.error.issues[0]?.message || "Invalid signup details.",
          issues: parsed.error.issues,
        },
        { status: 400 }
      );
    }

    const email = parsed.data.email.toLowerCase();
    const { fullName, phone, country, password } = parsed.data;

    const [existing] = await db
      .select({ id: schema.users.id })
      .from(schema.users)
      .where(eq(schema.users.email, email))
      .limit(1);

    if (existing) {
      return NextResponse.json(
        {
          error:
            "An account with this email address already exists. Please sign in instead.",
        },
        { status: 409 }
      );
    }

    const passwordHash = await hashPassword(password);

    const [user] = await db
      .insert(schema.users)
      .values({
        email,
        passwordHash,
        fullName,
        phone: phone || null,
        country: country || "India",
        isActive: true,
        lastLoginAt: new Date(),
      })
      .returning();

    let [participantRole] = await db
      .select()
      .from(schema.roles)
      .where(eq(schema.roles.code, "PARTICIPANT"))
      .limit(1);

    if (!participantRole) {
      [participantRole] = await db
        .insert(schema.roles)
        .values({
          code: "PARTICIPANT",
          name: "Participant",
          description: "Attendee, delegate, volunteer, or SLI professional account.",
        })
        .returning();
    }

    await db
      .insert(schema.userRoles)
      .values({
        userId: user.id,
        roleId: participantRole.id,
      })
      .onConflictDoNothing();

    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
      req.headers.get("x-real-ip") ||
      null;
    const userAgent = req.headers.get("user-agent") || null;

    await createSession(user.id, {
      ipAddress: ipAddress ?? undefined,
      userAgent: userAgent ?? undefined,
      mfaVerified: false,
    });

    const { roles, permissions } = await getUserRolesAndPermissions(user.id);

    await recordAuditLog({
      actorUserId: user.id,
      action: "ACCOUNT_SIGNUP",
      resourceType: "user",
      resourceId: user.id,
      ipAddress,
      userAgent,
      metadata: { email },
    });

    return NextResponse.json(
      {
        user: {
          id: user.id,
          email: user.email,
          fullName: user.fullName,
          phone: user.phone,
          country: user.country,
          roles,
          permissions,
          mfaEnabled: false,
        },
        redirectTo: "/dashboard",
      },
      { status: 201 }
    );
  } catch (err) {
    console.error("POST /api/auth/signup error:", err);
    return NextResponse.json(
      { error: "Unable to create account right now. Please try again." },
      { status: 500 }
    );
  }
}
