import { NextRequest, NextResponse } from "next/server";
import { asc, desc, eq, isNull } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission } from "@/lib/auth";
import {
  formatIstTimestamp,
  getIstDateString,
  PERMISSIONS,
} from "@/lib/constants";

/**
 * GET /api/checkins
 * - RGST (ATTENDANCE_READ_OWN only): returns ONLY their own recent check-ins,
 *   available checkinStations, and their high-level today's count.
 * - ADMIN / SUPERADMIN (ATTENDANCE_READ_FULL): returns full attendance analytics
 *   (total registrations, confirmed, checked-in, not-yet-arrived, today's arrivals in IST,
 *   breakdown by category, by venue, by day, plus searchable recent check-ins list).
 * NEVER exposes disability or medical data.
 */
export async function GET(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const canReadFull = hasPermission(user, PERMISSIONS.ATTENDANCE_READ_FULL);
    const canReadOwn = hasPermission(user, PERMISSIONS.ATTENDANCE_READ_OWN);

    if (!canReadFull && !canReadOwn) {
      return NextResponse.json(
        { error: "Access denied. Attendance data is restricted to authorized staff." },
        { status: 403 }
      );
    }

    const todayIst = getIstDateString();

    const stations = await db
      .select({
        id: schema.checkinStations.id,
        name: schema.checkinStations.name,
        gateCode: schema.checkinStations.gateCode,
        isActive: schema.checkinStations.isActive,
      })
      .from(schema.checkinStations)
      .where(eq(schema.checkinStations.isActive, true))
      .orderBy(asc(schema.checkinStations.name));

    // RGST-scoped response: ONLY their own check-ins + stations + today's count
    if (!canReadFull) {
      const ownRows = await db
        .select({
          id: schema.checkins.id,
          venueLabel: schema.checkins.venueLabel,
          method: schema.checkins.method,
          checkinDateIst: schema.checkins.checkinDateIst,
          entitlementScope: schema.checkins.entitlementScope,
          checkedInAt: schema.checkins.checkedInAt,
          participantName: schema.registrations.fullName,
          registrationCode: schema.registrations.registrationCode,
          categoryCode: schema.registrations.categoryCode,
          credentialCode: schema.qrCredentials.credentialCode,
        })
        .from(schema.checkins)
        .innerJoin(
          schema.registrations,
          eq(schema.checkins.registrationId, schema.registrations.id)
        )
        .innerJoin(
          schema.qrCredentials,
          eq(schema.checkins.credentialId, schema.qrCredentials.id)
        )
        .where(eq(schema.checkins.operatorUserId, user.id))
        .orderBy(desc(schema.checkins.checkedInAt))
        .limit(100);

      const todayCount = ownRows.filter((r) => r.checkinDateIst === todayIst).length;

      return NextResponse.json(
        {
          scope: "OWN",
          todayIst,
          todayCount,
          totalOperatorCheckins: ownRows.length,
          stations,
          recentCheckins: ownRows.slice(0, 50).map((r) => ({
            id: r.id,
            participantName: r.participantName,
            registrationCode: r.registrationCode,
            credentialCode: r.credentialCode,
            categoryCode: r.categoryCode,
            venueLabel: r.venueLabel,
            method: r.method,
            checkinDateIst: r.checkinDateIst,
            entitlementScope: r.entitlementScope,
            checkedInAt: r.checkedInAt.toISOString(),
            checkedInAtIst: formatIstTimestamp(r.checkedInAt),
          })),
        },
        { headers: { "Cache-Control": "no-store" } }
      );
    }

    // ADMIN / SUPERADMIN full attendance analytics
    const { searchParams } = new URL(req.url);
    const q = searchParams.get("q")?.trim().toLowerCase() || "";
    const categoryFilter = searchParams.get("category")?.trim().toUpperCase() || "";
    const dateFilter = searchParams.get("date")?.trim() || "";
    const venueFilter = searchParams.get("venue")?.trim().toLowerCase() || "";
    const limitParam = Number(searchParams.get("limit") || "100");
    const limit = Number.isFinite(limitParam) ? Math.min(Math.max(limitParam, 1), 500) : 100;

    const allRegistrations = await db
      .select({
        id: schema.registrations.id,
        categoryCode: schema.registrations.categoryCode,
        status: schema.registrations.status,
      })
      .from(schema.registrations)
      .where(isNull(schema.registrations.deletedAt));

    const allCheckins = await db
      .select({
        id: schema.checkins.id,
        registrationId: schema.checkins.registrationId,
        operatorUserId: schema.checkins.operatorUserId,
        venueLabel: schema.checkins.venueLabel,
        method: schema.checkins.method,
        checkinDateIst: schema.checkins.checkinDateIst,
        entitlementScope: schema.checkins.entitlementScope,
        checkedInAt: schema.checkins.checkedInAt,
        participantName: schema.registrations.fullName,
        registrationCode: schema.registrations.registrationCode,
        categoryCode: schema.registrations.categoryCode,
        credentialCode: schema.qrCredentials.credentialCode,
        operatorName: schema.users.fullName,
      })
      .from(schema.checkins)
      .innerJoin(
        schema.registrations,
        eq(schema.checkins.registrationId, schema.registrations.id)
      )
      .innerJoin(
        schema.qrCredentials,
        eq(schema.checkins.credentialId, schema.qrCredentials.id)
      )
      .innerJoin(schema.users, eq(schema.checkins.operatorUserId, schema.users.id))
      .orderBy(desc(schema.checkins.checkedInAt));

    const totalRegistrations = allRegistrations.length;
    const confirmedRegs = allRegistrations.filter((r) =>
      ["CONFIRMED", "APPROVED"].includes(r.status)
    );
    const confirmedRegistrations = confirmedRegs.length;

    const checkedInRegIds = new Set(allCheckins.map((c) => c.registrationId));
    const uniqueCheckedIn = checkedInRegIds.size;
    const notYetArrived = Math.max(0, confirmedRegistrations - uniqueCheckedIn);
    const todayArrivals = allCheckins.filter((c) => c.checkinDateIst === todayIst).length;
    const myTodayCount = allCheckins.filter(
      (c) => c.operatorUserId === user.id && c.checkinDateIst === todayIst
    ).length;

    // Breakdown by category
    const categoryMap = new Map<
      string,
      { categoryCode: string; total: number; confirmed: number; checkedIn: number }
    >();
    for (const r of allRegistrations) {
      const entry = categoryMap.get(r.categoryCode) ?? {
        categoryCode: r.categoryCode,
        total: 0,
        confirmed: 0,
        checkedIn: 0,
      };
      entry.total += 1;
      if (["CONFIRMED", "APPROVED"].includes(r.status)) {
        entry.confirmed += 1;
      }
      if (checkedInRegIds.has(r.id)) {
        entry.checkedIn += 1;
      }
      categoryMap.set(r.categoryCode, entry);
    }

    // Breakdown by venue
    const venueMap = new Map<string, number>();
    for (const c of allCheckins) {
      venueMap.set(c.venueLabel, (venueMap.get(c.venueLabel) ?? 0) + 1);
    }

    // Breakdown by IST day
    const dayMap = new Map<string, number>();
    for (const c of allCheckins) {
      dayMap.set(c.checkinDateIst, (dayMap.get(c.checkinDateIst) ?? 0) + 1);
    }

    // Filter searchable recent check-ins
    const filteredCheckins = allCheckins.filter((c) => {
      if (q) {
        const hay = `${c.participantName} ${c.registrationCode} ${c.credentialCode} ${c.venueLabel} ${c.operatorName}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      if (categoryFilter && c.categoryCode.toUpperCase() !== categoryFilter) {
        return false;
      }
      if (dateFilter && c.checkinDateIst !== dateFilter) {
        return false;
      }
      if (venueFilter && !c.venueLabel.toLowerCase().includes(venueFilter)) {
        return false;
      }
      return true;
    });

    return NextResponse.json(
      {
        scope: "FULL",
        todayIst,
        todayCount: myTodayCount,
        stations,
        summary: {
          totalRegistrations,
          confirmedRegistrations,
          checkedIn: uniqueCheckedIn,
          totalCheckinEvents: allCheckins.length,
          notYetArrived,
          todayArrivals,
        },
        breakdownByCategory: Array.from(categoryMap.values()).sort(
          (a, b) => b.confirmed - a.confirmed
        ),
        breakdownByVenue: Array.from(venueMap.entries())
          .map(([venueLabel, count]) => ({ venueLabel, count }))
          .sort((a, b) => b.count - a.count),
        breakdownByDay: Array.from(dayMap.entries())
          .map(([dateIst, count]) => ({ dateIst, count }))
          .sort((a, b) => a.dateIst.localeCompare(b.dateIst)),
        recentCheckins: filteredCheckins.slice(0, limit).map((c) => ({
          id: c.id,
          participantName: c.participantName,
          registrationCode: c.registrationCode,
          credentialCode: c.credentialCode,
          categoryCode: c.categoryCode,
          venueLabel: c.venueLabel,
          operatorName: c.operatorName,
          method: c.method,
          checkinDateIst: c.checkinDateIst,
          entitlementScope: c.entitlementScope,
          checkedInAt: c.checkedInAt.toISOString(),
          checkedInAtIst: formatIstTimestamp(c.checkedInAt),
        })),
      },
      { headers: { "Cache-Control": "no-store" } }
    );
  } catch (err) {
    console.error("[GET /api/checkins] Error:", err);
    return NextResponse.json(
      { error: "Failed to load attendance data." },
      { status: 500 }
    );
  }
}
