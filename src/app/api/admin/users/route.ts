import { NextRequest, NextResponse } from "next/server";
import { and, desc, eq, inArray, isNull } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import {
  getCurrentUser,
  hashPassword,
  hasPermission,
  hasRole,
  recordAuditLog,
} from "@/lib/auth";
import { PERMISSIONS } from "@/lib/constants";

const createStaffSchema = z.object({
  fullName: z.string().trim().min(2, "Full name is required.").max(200),
  email: z.string().trim().email("Valid email is required.").max(255),
  phone: z.string().trim().max(40).optional().or(z.literal("")),
  password: z.string().min(8, "Password must be at least 8 characters.").max(128),
  roleCode: z.enum(["ADMIN", "RGST"]),
  customPermissions: z.array(z.string()).optional().default([]),
});

const patchUserSchema = z.object({
  userId: z.string().uuid(),
  isActive: z.boolean().optional(),
  customPermissions: z.array(z.string()).optional(),
  newPassword: z.string().min(8).max(128).optional(),
});

function requireSuperadmin(user: Awaited<ReturnType<typeof getCurrentUser>>) {
  if (!user) {
    return NextResponse.json({ error: "Authentication required." }, { status: 401 });
  }
  if (
    !hasRole(user, ["SUPERADMIN"]) ||
    !hasPermission(user, PERMISSIONS.ADMIN_ACCOUNTS_MANAGE)
  ) {
    return NextResponse.json(
      { error: "Superadmin privilege (admin_accounts.manage) is required." },
      { status: 403 }
    );
  }
  return null;
}

export async function GET(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    const denied = requireSuperadmin(user);
    if (denied) return denied;

    const { searchParams } = new URL(req.url);
    const roleFilter = searchParams.get("role")?.trim().toUpperCase() || "";
    const search = searchParams.get("search")?.trim().toLowerCase() || "";

    const allUsers = await db
      .select({
        id: schema.users.id,
        email: schema.users.email,
        fullName: schema.users.fullName,
        phone: schema.users.phone,
        country: schema.users.country,
        isActive: schema.users.isActive,
        mfaEnabled: schema.users.mfaEnabled,
        lastLoginAt: schema.users.lastLoginAt,
        createdAt: schema.users.createdAt,
      })
      .from(schema.users)
      .where(isNull(schema.users.deletedAt))
      .orderBy(desc(schema.users.createdAt));

    const userIds = allUsers.map((u) => u.id);
    const roleLinks =
      userIds.length > 0
        ? await db
            .select({
              userId: schema.userRoles.userId,
              roleId: schema.userRoles.roleId,
              roleCode: schema.roles.code,
              customPermissions: schema.userRoles.customPermissions,
            })
            .from(schema.userRoles)
            .innerJoin(schema.roles, eq(schema.userRoles.roleId, schema.roles.id))
            .where(inArray(schema.userRoles.userId, userIds))
        : [];

    const rolesByUser = new Map<
      string,
      { roles: string[]; customPermissions: string[] }
    >();
    for (const rl of roleLinks) {
      const existing = rolesByUser.get(rl.userId) ?? {
        roles: [],
        customPermissions: [],
      };
      existing.roles.push(rl.roleCode);
      if (Array.isArray(rl.customPermissions)) {
        for (const p of rl.customPermissions) {
          if (!existing.customPermissions.includes(p)) {
            existing.customPermissions.push(p);
          }
        }
      }
      rolesByUser.set(rl.userId, existing);
    }

    let items = allUsers.map((u) => {
      const r = rolesByUser.get(u.id);
      return {
        ...u,
        roles: r && r.roles.length > 0 ? r.roles : ["PARTICIPANT"],
        customPermissions: r?.customPermissions ?? [],
      };
    });

    if (roleFilter) {
      items = items.filter((i) => i.roles.includes(roleFilter));
    }
    if (search) {
      items = items.filter(
        (i) =>
          i.fullName.toLowerCase().includes(search) ||
          i.email.toLowerCase().includes(search) ||
          (i.phone || "").toLowerCase().includes(search)
      );
    }

    return NextResponse.json({
      items,
      availablePermissions: Object.values(PERMISSIONS),
    });
  } catch (err) {
    console.error("GET /api/admin/users error:", err);
    return NextResponse.json({ error: "Failed to load users." }, { status: 500 });
  }
}

export async function POST(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    const denied = requireSuperadmin(user);
    if (denied) return denied;

    const body = await req.json().catch(() => null);
    const parsed = createStaffSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: "Invalid staff account payload.", details: parsed.error.flatten() },
        { status: 400 }
      );
    }

    const d = parsed.data;
    const normalizedEmail = d.email.toLowerCase();

    const [existing] = await db
      .select({ id: schema.users.id })
      .from(schema.users)
      .where(eq(schema.users.email, normalizedEmail))
      .limit(1);

    if (existing) {
      return NextResponse.json(
        { error: "An account with this email already exists." },
        { status: 409 }
      );
    }

    const [roleRow] = await db
      .select()
      .from(schema.roles)
      .where(eq(schema.roles.code, d.roleCode))
      .limit(1);

    if (!roleRow) {
      return NextResponse.json(
        { error: `Role ${d.roleCode} is not seeded in the database.` },
        { status: 500 }
      );
    }

    const passwordHash = await hashPassword(d.password);

    const [createdUser] = await db
      .insert(schema.users)
      .values({
        email: normalizedEmail,
        emailVerifiedAt: new Date(),
        passwordHash,
        fullName: d.fullName,
        phone: d.phone || null,
        country: "India",
        isActive: true,
      })
      .returning();

    const safeCreatedUser = {
      id: createdUser.id,
      email: createdUser.email,
      fullName: createdUser.fullName,
      phone: createdUser.phone,
      isActive: createdUser.isActive,
      createdAt: createdUser.createdAt,
    };

    const validPerms = new Set<string>(Object.values(PERMISSIONS));
    const filteredCustomPerms =
      d.roleCode === "ADMIN"
        ? d.customPermissions.filter((p) => validPerms.has(p))
        : [];

    await db.insert(schema.userRoles).values({
      userId: createdUser.id,
      roleId: roleRow.id,
      customPermissions: filteredCustomPerms,
      assignedBy: user!.id,
    });

    await db.insert(schema.adminActions).values({
      adminUserId: user!.id,
      targetUserId: createdUser.id,
      actionType: `STAFF_ACCOUNT_CREATED_${d.roleCode}`,
      afterState: {
        email: createdUser.email,
        roleCode: d.roleCode,
        customPermissions: filteredCustomPerms,
      },
    });

    await recordAuditLog({
      actorUserId: user!.id,
      action: "STAFF_USER_CREATED",
      resourceType: "user",
      resourceId: createdUser.id,
      metadata: {
        email: createdUser.email,
        roleCode: d.roleCode,
        customPermissions: filteredCustomPerms,
      },
    });

    return NextResponse.json(
      {
        user: {
          ...safeCreatedUser,
          roles: [d.roleCode],
          customPermissions: filteredCustomPerms,
        },
      },
      { status: 201 }
    );
  } catch (err) {
    console.error("POST /api/admin/users error:", err);
    return NextResponse.json({ error: "Failed to create staff account." }, { status: 500 });
  }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    const denied = requireSuperadmin(user);
    if (denied) return denied;

    const body = await req.json().catch(() => null);
    const parsed = patchUserSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json({ error: "Invalid user update payload." }, { status: 400 });
    }

    const { userId, isActive, customPermissions, newPassword } = parsed.data;

    if (userId === user!.id && isActive === false) {
      return NextResponse.json(
        { error: "You cannot deactivate your own Superadmin account." },
        { status: 400 }
      );
    }

    const [target] = await db
      .select()
      .from(schema.users)
      .where(eq(schema.users.id, userId))
      .limit(1);

    if (!target) {
      return NextResponse.json({ error: "User not found." }, { status: 404 });
    }

    const updates: Partial<typeof schema.users.$inferInsert> = {
      updatedAt: new Date(),
    };
    if (isActive !== undefined) updates.isActive = isActive;
    if (newPassword) updates.passwordHash = await hashPassword(newPassword);

    if (isActive !== undefined || newPassword) {
      await db.update(schema.users).set(updates).where(eq(schema.users.id, userId));
      // Revoke active sessions if deactivated
      if (isActive === false) {
        await db
          .update(schema.sessions)
          .set({ revokedAt: new Date() })
          .where(eq(schema.sessions.userId, userId));
      }
    }

    if (customPermissions !== undefined) {
      const validPerms = new Set<string>(Object.values(PERMISSIONS));
      const cleanPerms = customPermissions.filter((p) => validPerms.has(p));

      const [adminRole] = await db
        .select()
        .from(schema.roles)
        .where(eq(schema.roles.code, "ADMIN"))
        .limit(1);

      if (adminRole) {
        await db
          .update(schema.userRoles)
          .set({ customPermissions: cleanPerms })
          .where(
            and(
              eq(schema.userRoles.userId, userId),
              eq(schema.userRoles.roleId, adminRole.id)
            )
          );
      }
    }

    await db.insert(schema.adminActions).values({
      adminUserId: user!.id,
      targetUserId: userId,
      actionType: "USER_ACCOUNT_UPDATED",
      beforeState: { isActive: target.isActive },
      afterState: {
        isActive: isActive ?? target.isActive,
        customPermissions: customPermissions ?? undefined,
        passwordReset: Boolean(newPassword),
      },
    });

    await recordAuditLog({
      actorUserId: user!.id,
      action: "USER_ACCOUNT_UPDATED",
      resourceType: "user",
      resourceId: userId,
      metadata: {
        email: target.email,
        isActive: isActive ?? target.isActive,
        customPermissionsUpdated: customPermissions !== undefined,
        passwordReset: Boolean(newPassword),
      },
    });

    return NextResponse.json({ updated: true, userId });
  } catch (err) {
    console.error("PATCH /api/admin/users error:", err);
    return NextResponse.json({ error: "Failed to update user account." }, { status: 500 });
  }
}
