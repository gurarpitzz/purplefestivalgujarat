import { NextResponse } from "next/server";
import { desc, eq, isNull, sql } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser, hasRole } from "@/lib/auth";
import { getIstDateString } from "@/lib/constants";

export async function GET() {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasRole(user, ["SUPERADMIN", "ADMIN"])) {
      return NextResponse.json({ error: "Admin access required." }, { status: 403 });
    }

    const todayIst = getIstDateString();

    // 1. Fetch all active (non-deleted) registrations for exact, consistent aggregation
    const allRegs = await db
      .select({
        id: schema.registrations.id,
        userId: schema.registrations.userId,
        categoryCode: schema.registrations.categoryCode,
        status: schema.registrations.status,
        paymentStatus: schema.registrations.paymentStatus,
        createdAt: schema.registrations.createdAt,
      })
      .from(schema.registrations)
      .where(isNull(schema.registrations.deletedAt));

    const uniqueUserIds = new Set<string>();
    let totalApplications = 0;
    let confirmedRegistrations = 0;
    let delegates = 0;
    let volunteerApplications = 0;
    let approvedVolunteers = 0;
    let sliApplications = 0;
    let approvedSli = 0;
    let alliesSupporters = 0;
    let pendingApprovals = 0;

    const categoryMap = new Map<
      string,
      { categoryCode: string; total: number; confirmed: number; pending: number; checkedIn: number }
    >();
    const dailyTrendMap = new Map<string, number>();

    const volunteerPipeline = {
      total: 0,
      submitted: 0,
      underReview: 0,
      approved: 0,
      waitlisted: 0,
      rejected: 0,
    };
    const sliPipeline = {
      total: 0,
      submitted: 0,
      underReview: 0,
      approved: 0,
      waitlisted: 0,
      rejected: 0,
    };

    const regCategoryById = new Map<string, string>();

    for (const r of allRegs) {
      totalApplications += 1;
      uniqueUserIds.add(r.userId);
      regCategoryById.set(r.id, r.categoryCode);

      const isConfirmed = r.status === "CONFIRMED" || r.status === "APPROVED";
      const isPending =
        r.status === "SUBMITTED" ||
        r.status === "UNDER_REVIEW" ||
        r.status === "ADDITIONAL_INFO_REQUIRED";

      if (isConfirmed) confirmedRegistrations += 1;
      if (isPending) pendingApprovals += 1;

      if (r.categoryCode === "DELEGATE") delegates += 1;
      if (r.categoryCode === "ALLY") alliesSupporters += 1;

      if (r.categoryCode === "VOLUNTEER") {
        volunteerApplications += 1;
        volunteerPipeline.total += 1;
        if (isConfirmed) {
          approvedVolunteers += 1;
          volunteerPipeline.approved += 1;
        } else if (r.status === "SUBMITTED") {
          volunteerPipeline.submitted += 1;
        } else if (r.status === "UNDER_REVIEW" || r.status === "ADDITIONAL_INFO_REQUIRED") {
          volunteerPipeline.underReview += 1;
        } else if (r.status === "WAITLISTED") {
          volunteerPipeline.waitlisted += 1;
        } else if (r.status === "REJECTED") {
          volunteerPipeline.rejected += 1;
        }
      }

      if (r.categoryCode === "SLI") {
        sliApplications += 1;
        sliPipeline.total += 1;
        if (isConfirmed) {
          approvedSli += 1;
          sliPipeline.approved += 1;
        } else if (r.status === "SUBMITTED") {
          sliPipeline.submitted += 1;
        } else if (r.status === "UNDER_REVIEW" || r.status === "ADDITIONAL_INFO_REQUIRED") {
          sliPipeline.underReview += 1;
        } else if (r.status === "WAITLISTED") {
          sliPipeline.waitlisted += 1;
        } else if (r.status === "REJECTED") {
          sliPipeline.rejected += 1;
        }
      }

      const catEntry = categoryMap.get(r.categoryCode) ?? {
        categoryCode: r.categoryCode,
        total: 0,
        confirmed: 0,
        pending: 0,
        checkedIn: 0,
      };
      catEntry.total += 1;
      if (isConfirmed) catEntry.confirmed += 1;
      if (isPending) catEntry.pending += 1;
      categoryMap.set(r.categoryCode, catEntry);

      const dateKey = getIstDateString(new Date(r.createdAt));
      dailyTrendMap.set(dateKey, (dailyTrendMap.get(dateKey) ?? 0) + 1);
    }

    // 2. Accommodation requests count (unique registrations requesting accommodations)
    const accommRows = await db
      .select({
        id: schema.accommodationRequests.id,
        registrationId: schema.accommodationRequests.registrationId,
        status: schema.accommodationRequests.status,
      })
      .from(schema.accommodationRequests);

    const uniqueAccommRegIds = new Set(accommRows.map((a) => a.registrationId));
    const accommodationSummary = {
      totalRequests: accommRows.length,
      uniqueParticipants: uniqueAccommRegIds.size,
      requested: accommRows.filter((a) => a.status === "REQUESTED").length,
      inProgress: accommRows.filter((a) => a.status === "IN_PROGRESS").length,
      arranged: accommRows.filter((a) => a.status === "ARRANGED").length,
      declined: accommRows.filter((a) => a.status === "DECLINED").length,
    };

    // 3. Check-ins & Attendance breakdown
    const allCheckins = await db
      .select({
        id: schema.checkins.id,
        registrationId: schema.checkins.registrationId,
        venueLabel: schema.checkins.venueLabel,
        checkinDateIst: schema.checkins.checkinDateIst,
        method: schema.checkins.method,
        checkedInAt: schema.checkins.checkedInAt,
      })
      .from(schema.checkins)
      .orderBy(desc(schema.checkins.checkedInAt));

    const uniqueCheckedInRegIds = new Set<string>();
    const checkedInByCategorySet = new Map<string, Set<string>>();
    const attendanceByDayMap = new Map<string, number>();
    const attendanceByVenueMap = new Map<string, number>();
    let todayCheckins = 0;

    for (const c of allCheckins) {
      uniqueCheckedInRegIds.add(c.registrationId);
      if (c.checkinDateIst === todayIst) todayCheckins += 1;

      attendanceByDayMap.set(c.checkinDateIst, (attendanceByDayMap.get(c.checkinDateIst) ?? 0) + 1);
      attendanceByVenueMap.set(c.venueLabel, (attendanceByVenueMap.get(c.venueLabel) ?? 0) + 1);

      const cat = regCategoryById.get(c.registrationId);
      if (cat) {
        if (!checkedInByCategorySet.has(cat)) checkedInByCategorySet.set(cat, new Set());
        checkedInByCategorySet.get(cat)!.add(c.registrationId);
      }
    }

    for (const [cat, set] of checkedInByCategorySet.entries()) {
      const entry = categoryMap.get(cat);
      if (entry) entry.checkedIn = set.size;
    }

    // Recent checkins with participant info
    const recentCheckins = await db
      .select({
        id: schema.checkins.id,
        registrationId: schema.checkins.registrationId,
        registrationCode: schema.registrations.registrationCode,
        participantName: schema.registrations.fullName,
        categoryCode: schema.registrations.categoryCode,
        venueLabel: schema.checkins.venueLabel,
        checkinDateIst: schema.checkins.checkinDateIst,
        method: schema.checkins.method,
        checkedInAt: schema.checkins.checkedInAt,
      })
      .from(schema.checkins)
      .innerJoin(schema.registrations, eq(schema.checkins.registrationId, schema.registrations.id))
      .orderBy(desc(schema.checkins.checkedInAt))
      .limit(20);

    // 4. Payment reconciliation
    const paymentAgg = await db
      .select({
        status: schema.paymentOrders.status,
        count: sql<number>`count(*)::int`,
        grossInr: sql<number>`coalesce(sum(${schema.paymentOrders.amountInr}), 0)::int`,
        discountInr: sql<number>`coalesce(sum(${schema.paymentOrders.discountInr}), 0)::int`,
        netInr: sql<number>`coalesce(sum(${schema.paymentOrders.netAmountInr}), 0)::int`,
      })
      .from(schema.paymentOrders)
      .groupBy(schema.paymentOrders.status);

    const paymentReconciliation = {
      capturedOrders: 0,
      capturedRevenueInr: 0,
      pendingOrders: 0,
      pendingAmountInr: 0,
      refundedOrders: 0,
      refundedAmountInr: 0,
      totalDiscountInr: 0,
      byStatus: paymentAgg,
    };

    for (const row of paymentAgg) {
      paymentReconciliation.totalDiscountInr += Number(row.discountInr || 0);
      if (row.status === "CAPTURED") {
        paymentReconciliation.capturedOrders = Number(row.count || 0);
        paymentReconciliation.capturedRevenueInr = Number(row.netInr || 0);
      } else if (row.status === "PENDING" || row.status === "AUTHORIZED") {
        paymentReconciliation.pendingOrders += Number(row.count || 0);
        paymentReconciliation.pendingAmountInr += Number(row.netInr || 0);
      } else if (row.status === "REFUNDED" || row.status === "PARTIALLY_REFUNDED") {
        paymentReconciliation.refundedOrders += Number(row.count || 0);
        paymentReconciliation.refundedAmountInr += Number(row.netInr || 0);
      }
    }

    const dailyRegistrationTrends = Array.from(dailyTrendMap.entries())
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([date, count]) => ({ date, count }));

    const categoryDistribution = Array.from(categoryMap.values()).sort((a, b) => b.total - a.total);

    const attendanceByDay = Array.from(attendanceByDayMap.entries())
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([date, count]) => ({ date, count }));

    const attendanceByVenue = Array.from(attendanceByVenueMap.entries())
      .sort((a, b) => b[1] - a[1])
      .map(([venueLabel, count]) => ({ venueLabel, count }));

    const uniqueCheckedInParticipants = uniqueCheckedInRegIds.size;
    const notYetArrived = Math.max(0, confirmedRegistrations - uniqueCheckedInParticipants);

    return NextResponse.json({
      generatedAt: new Date().toISOString(),
      todayIst,
      metrics: {
        uniquePeople: uniqueUserIds.size,
        totalApplications,
        confirmedRegistrations,
        delegates,
        volunteerApplications,
        approvedVolunteers,
        sliApplications,
        approvedSli,
        alliesSupporters,
        participantsRequestingAccommodations: uniqueAccommRegIds.size,
        totalCheckins: allCheckins.length,
        uniqueCheckedInParticipants,
        notYetArrived,
        todayCheckins,
        pendingApprovals,
      },
      accommodationSummary,
      paymentReconciliation,
      dailyRegistrationTrends,
      categoryDistribution,
      attendanceByDay,
      attendanceByVenue,
      volunteerPipeline,
      sliPipeline,
      recentCheckins,
    });
  } catch (err) {
    console.error("GET /api/admin/overview error:", err);
    return NextResponse.json({ error: "Failed to load overview metrics." }, { status: 500 });
  }
}
