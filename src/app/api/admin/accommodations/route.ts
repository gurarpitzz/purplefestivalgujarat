import { NextRequest, NextResponse } from "next/server";
import { desc, eq, inArray } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission, recordAuditLog } from "@/lib/auth";
import { PERMISSIONS } from "@/lib/constants";

const patchAccommodationSchema = z.object({
  requestId: z.string().uuid("Valid requestId is required."),
  status: z.enum(["REQUESTED", "IN_PROGRESS", "ARRANGED", "DECLINED"]),
  coordinatorNotes: z.string().max(2000).optional(),
});

export async function GET(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.ACCOMMODATIONS_READ)) {
      return NextResponse.json(
        { error: "Permission accommodations.read is required." },
        { status: 403 }
      );
    }

    const { searchParams } = new URL(req.url);
    const statusFilter = searchParams.get("status")?.trim().toUpperCase() || "";
    const search = searchParams.get("search")?.trim().toLowerCase() || "";

    const rows = await db
      .select({
        id: schema.accommodationRequests.id,
        registrationId: schema.accommodationRequests.registrationId,
        userId: schema.accommodationRequests.userId,
        selectedTypes: schema.accommodationRequests.selectedTypes,
        freeTextDetails: schema.accommodationRequests.freeTextDetails,
        status: schema.accommodationRequests.status,
        createdAt: schema.accommodationRequests.createdAt,
        updatedAt: schema.accommodationRequests.updatedAt,
        registrationCode: schema.registrations.registrationCode,
        fullName: schema.registrations.fullName,
        email: schema.registrations.email,
        phone: schema.registrations.phone,
        categoryCode: schema.registrations.categoryCode,
        registrationStatus: schema.registrations.status,
        attendingDays: schema.registrations.attendingDays,
      })
      .from(schema.accommodationRequests)
      .innerJoin(
        schema.registrations,
        eq(schema.accommodationRequests.registrationId, schema.registrations.id)
      )
      .orderBy(desc(schema.accommodationRequests.updatedAt));

    const reqIds = rows.map((r) => r.id);
    const assignmentsMap = new Map<
      string,
      { coordinatorNotes: string | null; assignedToUserId: string | null; updatedAt: Date }
    >();

    if (reqIds.length > 0) {
      const assignmentRows = await db
        .select()
        .from(schema.accommodationAssignments)
        .where(inArray(schema.accommodationAssignments.requestId, reqIds))
        .orderBy(desc(schema.accommodationAssignments.updatedAt));

      for (const a of assignmentRows) {
        if (!assignmentsMap.has(a.requestId)) {
          assignmentsMap.set(a.requestId, {
            coordinatorNotes: a.coordinatorNotes,
            assignedToUserId: a.assignedToUserId,
            updatedAt: a.updatedAt,
          });
        }
      }
    }

    const typesList = await db
      .select()
      .from(schema.accommodationTypes)
      .orderBy(schema.accommodationTypes.displayOrder);
    const typeLabelMap = new Map(typesList.map((t) => [t.code, t.label]));

    let items = rows.map((r) => {
      const assign = assignmentsMap.get(r.id);
      const selectedCodes = Array.isArray(r.selectedTypes) ? r.selectedTypes : [];
      return {
        ...r,
        selectedTypes: selectedCodes,
        selectedTypeLabels: selectedCodes.map((code) => typeLabelMap.get(code) || code),
        coordinatorNotes: assign?.coordinatorNotes ?? "",
        assignedToUserId: assign?.assignedToUserId ?? null,
      };
    });

    if (statusFilter) {
      items = items.filter((i) => i.status === statusFilter);
    }
    if (search) {
      items = items.filter(
        (i) =>
          i.fullName.toLowerCase().includes(search) ||
          i.email.toLowerCase().includes(search) ||
          i.registrationCode.toLowerCase().includes(search) ||
          i.selectedTypeLabels.some((l) => l.toLowerCase().includes(search)) ||
          (i.freeTextDetails || "").toLowerCase().includes(search)
      );
    }

    return NextResponse.json({
      items,
      total: items.length,
    });
  } catch (err) {
    console.error("GET /api/admin/accommodations error:", err);
    return NextResponse.json({ error: "Failed to load accommodation requests." }, { status: 500 });
  }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.ACCOMMODATIONS_MANAGE)) {
      return NextResponse.json(
        { error: "Permission accommodations.manage is required." },
        { status: 403 }
      );
    }

    const body = await req.json().catch(() => null);
    const parsed = patchAccommodationSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: "Invalid accommodation update.", details: parsed.error.flatten() },
        { status: 400 }
      );
    }

    const { requestId, status, coordinatorNotes } = parsed.data;

    const [existing] = await db
      .select()
      .from(schema.accommodationRequests)
      .where(eq(schema.accommodationRequests.id, requestId))
      .limit(1);

    if (!existing) {
      return NextResponse.json({ error: "Accommodation request not found." }, { status: 404 });
    }

    const [updated] = await db
      .update(schema.accommodationRequests)
      .set({
        status,
        updatedAt: new Date(),
      })
      .where(eq(schema.accommodationRequests.id, requestId))
      .returning();

    const [existingAssign] = await db
      .select()
      .from(schema.accommodationAssignments)
      .where(eq(schema.accommodationAssignments.requestId, requestId))
      .limit(1);

    if (existingAssign) {
      await db
        .update(schema.accommodationAssignments)
        .set({
          assignedToUserId: user.id,
          coordinatorNotes: coordinatorNotes ?? existingAssign.coordinatorNotes,
          status,
          updatedAt: new Date(),
        })
        .where(eq(schema.accommodationAssignments.id, existingAssign.id));
    } else {
      await db.insert(schema.accommodationAssignments).values({
        requestId,
        assignedToUserId: user.id,
        coordinatorNotes: coordinatorNotes ?? null,
        status,
      });
    }

    await db.insert(schema.adminActions).values({
      adminUserId: user.id,
      targetUserId: existing.userId,
      actionType: `ACCOMMODATION_${status}`,
      reason: coordinatorNotes ?? null,
      beforeState: { status: existing.status },
      afterState: { status, coordinatorNotes: coordinatorNotes ?? null },
    });

    await recordAuditLog({
      actorUserId: user.id,
      action: "ACCOMMODATION_UPDATED",
      resourceType: "accommodation_request",
      resourceId: requestId,
      metadata: { previousStatus: existing.status, newStatus: status },
    });

    return NextResponse.json({
      request: updated,
      coordinatorNotes: coordinatorNotes ?? existingAssign?.coordinatorNotes ?? "",
    });
  } catch (err) {
    console.error("PATCH /api/admin/accommodations error:", err);
    return NextResponse.json({ error: "Failed to update accommodation request." }, { status: 500 });
  }
}
