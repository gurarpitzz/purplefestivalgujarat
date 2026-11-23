import { NextRequest, NextResponse } from "next/server";
import { and, desc, eq, ilike, or } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission } from "@/lib/auth";
import { PERMISSIONS } from "@/lib/constants";

export async function GET(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.AUDIT_LOGS_READ)) {
      return NextResponse.json(
        { error: "Permission audit_logs.read is required." },
        { status: 403 }
      );
    }

    const { searchParams } = new URL(req.url);
    const page = Math.max(1, Number(searchParams.get("page") || "1") || 1);
    const pageSize = Math.min(100, Math.max(1, Number(searchParams.get("pageSize") || "30") || 30));
    const actionFilter = searchParams.get("action")?.trim() || "";
    const resourceTypeFilter = searchParams.get("resourceType")?.trim() || "";
    const search = searchParams.get("search")?.trim() || "";

    const conditions = [];
    if (actionFilter) {
      conditions.push(ilike(schema.auditLogs.action, `%${actionFilter}%`));
    }
    if (resourceTypeFilter) {
      conditions.push(eq(schema.auditLogs.resourceType, resourceTypeFilter));
    }
    if (search) {
      const pattern = `%${search}%`;
      conditions.push(
        or(
          ilike(schema.auditLogs.action, pattern),
          ilike(schema.auditLogs.resourceType, pattern),
          ilike(schema.auditLogs.resourceId, pattern),
          ilike(schema.users.email, pattern),
          ilike(schema.users.fullName, pattern)
        )!
      );
    }

    const rows = await db
      .select({
        id: schema.auditLogs.id,
        action: schema.auditLogs.action,
        resourceType: schema.auditLogs.resourceType,
        resourceId: schema.auditLogs.resourceId,
        ipAddress: schema.auditLogs.ipAddress,
        metadata: schema.auditLogs.metadata,
        createdAt: schema.auditLogs.createdAt,
        actorUserId: schema.auditLogs.actorUserId,
        actorName: schema.users.fullName,
        actorEmail: schema.users.email,
      })
      .from(schema.auditLogs)
      .leftJoin(schema.users, eq(schema.auditLogs.actorUserId, schema.users.id))
      .where(conditions.length > 0 ? and(...conditions) : undefined)
      .orderBy(desc(schema.auditLogs.createdAt));

    const total = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    const offset = (page - 1) * pageSize;
    const items = rows.slice(offset, offset + pageSize);

    return NextResponse.json({
      items,
      pagination: {
        page,
        pageSize,
        total,
        totalPages,
      },
    });
  } catch (err) {
    console.error("GET /api/admin/audit-logs error:", err);
    return NextResponse.json({ error: "Failed to load audit logs." }, { status: 500 });
  }
}
