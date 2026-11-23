import crypto from "crypto";
import bcrypt from "bcryptjs";
import { cookies } from "next/headers";
import { eq, and, isNull, gt } from "drizzle-orm";
import { authenticator } from "otplib";
import { db, schema } from "@/db";
import { DEFAULT_ROLE_PERMISSIONS, type PermissionCode } from "./constants";

export const SESSION_COOKIE_NAME = "pfg_session";
const SESSION_DURATION_MS = 1000 * 60 * 60 * 24 * 7; // 7 days

export type RoleCode = "SUPERADMIN" | "ADMIN" | "RGST" | "PARTICIPANT";

export interface AuthenticatedUser {
  id: string;
  email: string;
  fullName: string;
  phone: string | null;
  country: string;
  roles: RoleCode[];
  permissions: PermissionCode[];
  mfaEnabled: boolean;
  mfaVerified: boolean;
  sessionId: string;
}

export function hashToken(raw: string): string {
  const secret = process.env.AUTH_SECRET || "dev-fallback-secret-change-in-prod";
  return crypto.createHmac("sha256", secret).update(raw).digest("hex");
}

export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, 12);
}

export async function verifyPassword(password: string, hash: string): Promise<boolean> {
  return bcrypt.compare(password, hash);
}

export function generateMfaSecret(email: string): { secret: string; otpauthUrl: string } {
  const secret = authenticator.generateSecret();
  const otpauthUrl = authenticator.keyuri(email, "Purple Festival Gujarat 2027", secret);
  return { secret, otpauthUrl };
}

export function verifyMfaToken(token: string, secret: string): boolean {
  return authenticator.verify({ token, secret });
}

export async function createSession(
  userId: string,
  options?: { ipAddress?: string; userAgent?: string; mfaVerified?: boolean }
): Promise<{ rawToken: string; expiresAt: Date; sessionId: string }> {
  const rawToken = crypto.randomBytes(32).toString("base64url");
  const tokenHash = hashToken(rawToken);
  const expiresAt = new Date(Date.now() + SESSION_DURATION_MS);

  const [session] = await db
    .insert(schema.sessions)
    .values({
      userId,
      tokenHash,
      ipAddress: options?.ipAddress || null,
      userAgent: options?.userAgent || null,
      mfaVerified: options?.mfaVerified ?? false,
      expiresAt,
    })
    .returning();

  const cookieStore = await cookies();
  const isHttpsApp = (process.env.NEXT_PUBLIC_APP_URL || "").startsWith("https://");
  cookieStore.set(SESSION_COOKIE_NAME, rawToken, {
    httpOnly: true,
    sameSite: "lax",
    secure: isHttpsApp,
    path: "/",
    expires: expiresAt,
  });

  return { rawToken, expiresAt, sessionId: session.id };
}

export async function destroyCurrentSession(): Promise<void> {
  const cookieStore = await cookies();
  const rawToken = cookieStore.get(SESSION_COOKIE_NAME)?.value;
  if (rawToken) {
    const tokenHash = hashToken(rawToken);
    await db
      .update(schema.sessions)
      .set({ revokedAt: new Date() })
      .where(eq(schema.sessions.tokenHash, tokenHash));
  }
  cookieStore.delete(SESSION_COOKIE_NAME);
}

export async function getUserRolesAndPermissions(userId: string): Promise<{
  roles: RoleCode[];
  permissions: PermissionCode[];
}> {
  const assigned = await db
    .select({
      roleCode: schema.roles.code,
      customPermissions: schema.userRoles.customPermissions,
    })
    .from(schema.userRoles)
    .innerJoin(schema.roles, eq(schema.userRoles.roleId, schema.roles.id))
    .where(eq(schema.userRoles.userId, userId));

  const roles: RoleCode[] =
    assigned.length > 0 ? assigned.map((a) => a.roleCode as RoleCode) : ["PARTICIPANT"];

  const permSet = new Set<PermissionCode>();
  for (const row of assigned) {
    const basePerms = DEFAULT_ROLE_PERMISSIONS[row.roleCode as RoleCode] || [];
    for (const p of basePerms) permSet.add(p);
    if (Array.isArray(row.customPermissions)) {
      for (const cp of row.customPermissions) {
        permSet.add(cp as PermissionCode);
      }
    }
  }
  if (assigned.length === 0) {
    for (const p of DEFAULT_ROLE_PERMISSIONS.PARTICIPANT) permSet.add(p);
  }

  return { roles, permissions: Array.from(permSet) };
}

export async function getCurrentUser(): Promise<AuthenticatedUser | null> {
  const cookieStore = await cookies();
  const rawToken = cookieStore.get(SESSION_COOKIE_NAME)?.value;
  if (!rawToken) return null;

  const tokenHash = hashToken(rawToken);
  const now = new Date();

  const [sessionRow] = await db
    .select({
      sessionId: schema.sessions.id,
      mfaVerified: schema.sessions.mfaVerified,
      userId: schema.users.id,
      email: schema.users.email,
      fullName: schema.users.fullName,
      phone: schema.users.phone,
      country: schema.users.country,
      isActive: schema.users.isActive,
      mfaEnabled: schema.users.mfaEnabled,
      deletedAt: schema.users.deletedAt,
    })
    .from(schema.sessions)
    .innerJoin(schema.users, eq(schema.sessions.userId, schema.users.id))
    .where(
      and(
        eq(schema.sessions.tokenHash, tokenHash),
        isNull(schema.sessions.revokedAt),
        gt(schema.sessions.expiresAt, now)
      )
    )
    .limit(1);

  if (!sessionRow || !sessionRow.isActive || sessionRow.deletedAt) {
    return null;
  }

  const { roles, permissions } = await getUserRolesAndPermissions(sessionRow.userId);

  return {
    id: sessionRow.userId,
    email: sessionRow.email,
    fullName: sessionRow.fullName,
    phone: sessionRow.phone,
    country: sessionRow.country,
    roles,
    permissions,
    mfaEnabled: sessionRow.mfaEnabled,
    mfaVerified: sessionRow.mfaVerified,
    sessionId: sessionRow.sessionId,
  };
}

export function hasRole(user: AuthenticatedUser | null, allowed: RoleCode[]): boolean {
  if (!user) return false;
  return user.roles.some((r) => allowed.includes(r));
}

export function hasPermission(user: AuthenticatedUser | null, perm: PermissionCode): boolean {
  if (!user) return false;
  if (user.roles.includes("SUPERADMIN")) return true;
  return user.permissions.includes(perm);
}

export function getPortalPathForRoles(roles: RoleCode[]): string {
  if (roles.includes("SUPERADMIN") || roles.includes("ADMIN")) return "/admin";
  if (roles.includes("RGST")) return "/rgst";
  return "/dashboard";
}

export async function recordAuditLog(params: {
  actorUserId?: string | null;
  action: string;
  resourceType: string;
  resourceId?: string | null;
  ipAddress?: string | null;
  userAgent?: string | null;
  metadata?: Record<string, unknown>;
}) {
  await db.insert(schema.auditLogs).values({
    actorUserId: params.actorUserId ?? null,
    action: params.action,
    resourceType: params.resourceType,
    resourceId: params.resourceId ?? null,
    ipAddress: params.ipAddress ?? null,
    userAgent: params.userAgent ?? null,
    metadata: params.metadata ?? null,
  });
}

