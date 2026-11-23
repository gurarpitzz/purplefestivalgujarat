import { NextRequest, NextResponse } from "next/server";
import { and, asc, desc, eq, ilike, inArray, isNull, or } from "drizzle-orm";
import * as XLSX from "xlsx";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission, hasRole, recordAuditLog } from "@/lib/auth";
import { PERMISSIONS } from "@/lib/constants";

function escapeCsvCell(value: unknown): string {
  const str = value == null ? "" : String(value);
  if (/[",\r\n]/.test(str)) {
    return `"${str.replace(/"/g, '""')}"`;
  }
  return str;
}

export async function GET(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasRole(user, ["SUPERADMIN", "ADMIN"]) && !hasPermission(user, PERMISSIONS.REGISTRATIONS_READ_ALL)) {
      return NextResponse.json({ error: "Admin access required." }, { status: 403 });
    }

    const { searchParams } = new URL(req.url);
    const search = searchParams.get("search")?.trim() || "";
    const category = searchParams.get("category")?.trim().toUpperCase() || "";
    const statusParam = searchParams.get("status")?.trim().toUpperCase() || "";
    const paymentStatus = searchParams.get("paymentStatus")?.trim().toUpperCase() || "";
    const country = searchParams.get("country")?.trim() || "";
    const checkinStatus = searchParams.get("checkinStatus")?.trim().toUpperCase() || "";
    const hasAccommodations = searchParams.get("hasAccommodations")?.trim().toLowerCase() || "";
    const sortBy = searchParams.get("sortBy")?.trim() || "createdAt";
    const sortOrder = searchParams.get("sortOrder")?.trim().toLowerCase() === "asc" ? "asc" : "desc";
    const format = searchParams.get("format")?.trim().toLowerCase() || "";
    const includeAccommodations = searchParams.get("includeAccommodations") === "true";

    const page = Math.max(1, Number(searchParams.get("page") || "1") || 1);
    const pageSize = Math.min(100, Math.max(1, Number(searchParams.get("pageSize") || "25") || 25));

    if (format === "csv" || format === "xlsx") {
      if (!hasPermission(user, PERMISSIONS.REGISTRATIONS_EXPORT)) {
        return NextResponse.json(
          { error: "Export permission required." },
          { status: 403 }
        );
      }
      if (includeAccommodations && !hasPermission(user, PERMISSIONS.ACCOMMODATIONS_READ)) {
        return NextResponse.json(
          { error: "Accommodations read permission required for accommodation export." },
          { status: 403 }
        );
      }
    }

    const conditions = [isNull(schema.registrations.deletedAt)];

    if (search) {
      const pattern = `%${search}%`;
      conditions.push(
        or(
          ilike(schema.registrations.fullName, pattern),
          ilike(schema.registrations.email, pattern),
          ilike(schema.registrations.registrationCode, pattern),
          ilike(schema.registrations.phone, pattern),
          ilike(schema.registrations.organizationName, pattern)
        )!
      );
    }

    if (category) {
      conditions.push(eq(schema.registrations.categoryCode, category));
    }

    if (statusParam) {
      const statuses = statusParam
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean) as Array<typeof schema.registrations.$inferSelect.status>;
      if (statuses.length === 1) {
        conditions.push(eq(schema.registrations.status, statuses[0]));
      } else if (statuses.length > 1) {
        conditions.push(inArray(schema.registrations.status, statuses));
      }
    }

    if (paymentStatus) {
      conditions.push(
        eq(
          schema.registrations.paymentStatus,
          paymentStatus as typeof schema.registrations.$inferSelect.paymentStatus
        )
      );
    }

    if (country) {
      conditions.push(ilike(schema.registrations.country, `%${country}%`));
    }

    const orderColumn =
      sortBy === "fullName"
        ? schema.registrations.fullName
        : sortBy === "registrationCode"
          ? schema.registrations.registrationCode
          : sortBy === "status"
            ? schema.registrations.status
            : sortBy === "categoryCode"
              ? schema.registrations.categoryCode
              : schema.registrations.createdAt;

    const orderExpr = sortOrder === "asc" ? asc(orderColumn) : desc(orderColumn);

    const rows = await db
      .select()
      .from(schema.registrations)
      .where(and(...conditions))
      .orderBy(orderExpr);

    const regIds = rows.map((r) => r.id);

    // Fetch related check-ins, credentials, accommodations, volunteer & SLI records in bulk
    const checkinsByReg = new Map<
      string,
      { count: number; lastAt: string; lastVenue: string }
    >();
    const credByReg = new Map<string, { status: string; code: string }>();
    const accommByReg = new Map<
      string,
      { status: string; selectedTypes: string[]; freeTextDetails: string | null }
    >();
    const volByReg = new Map<string, typeof schema.volunteerApplications.$inferSelect>();
    const sliByReg = new Map<string, typeof schema.sliApplications.$inferSelect>();

    if (regIds.length > 0) {
      const [checkinRows, credRows, accommRows, volRows, sliRows] = await Promise.all([
        db
          .select({
            registrationId: schema.checkins.registrationId,
            checkedInAt: schema.checkins.checkedInAt,
            venueLabel: schema.checkins.venueLabel,
          })
          .from(schema.checkins)
          .where(inArray(schema.checkins.registrationId, regIds))
          .orderBy(desc(schema.checkins.checkedInAt)),
        db
          .select({
            registrationId: schema.qrCredentials.registrationId,
            status: schema.qrCredentials.status,
            credentialCode: schema.qrCredentials.credentialCode,
            issuedAt: schema.qrCredentials.issuedAt,
          })
          .from(schema.qrCredentials)
          .where(inArray(schema.qrCredentials.registrationId, regIds))
          .orderBy(desc(schema.qrCredentials.issuedAt)),
        db
          .select({
            registrationId: schema.accommodationRequests.registrationId,
            status: schema.accommodationRequests.status,
            selectedTypes: schema.accommodationRequests.selectedTypes,
            freeTextDetails: schema.accommodationRequests.freeTextDetails,
          })
          .from(schema.accommodationRequests)
          .where(inArray(schema.accommodationRequests.registrationId, regIds)),
        db
          .select()
          .from(schema.volunteerApplications)
          .where(inArray(schema.volunteerApplications.registrationId, regIds)),
        db
          .select()
          .from(schema.sliApplications)
          .where(inArray(schema.sliApplications.registrationId, regIds)),
      ]);

      for (const c of checkinRows) {
        const existing = checkinsByReg.get(c.registrationId);
        if (!existing) {
          checkinsByReg.set(c.registrationId, {
            count: 1,
            lastAt: c.checkedInAt.toISOString(),
            lastVenue: c.venueLabel,
          });
        } else {
          existing.count += 1;
        }
      }

      for (const cr of credRows) {
        if (!credByReg.has(cr.registrationId)) {
          credByReg.set(cr.registrationId, {
            status: cr.status,
            code: cr.credentialCode,
          });
        }
      }

      for (const ac of accommRows) {
        if (!accommByReg.has(ac.registrationId)) {
          accommByReg.set(ac.registrationId, {
            status: ac.status,
            selectedTypes: Array.isArray(ac.selectedTypes) ? ac.selectedTypes : [],
            freeTextDetails: ac.freeTextDetails,
          });
        }
      }

      for (const v of volRows) volByReg.set(v.registrationId, v);
      for (const s of sliRows) sliByReg.set(s.registrationId, s);
    }

    const canReadAccomm = hasPermission(user, PERMISSIONS.ACCOMMODATIONS_READ);

    let enriched = rows.map((r) => {
      const ck = checkinsByReg.get(r.id);
      const cred = credByReg.get(r.id);
      const accomm = accommByReg.get(r.id);
      return {
        id: r.id,
        registrationCode: r.registrationCode,
        userId: r.userId,
        categoryCode: r.categoryCode,
        status: r.status,
        paymentStatus: r.paymentStatus,
        fullName: r.fullName,
        email: r.email,
        phone: r.phone,
        country: r.country,
        occupation: r.occupation,
        organizationName: r.organizationName,
        designation: r.designation,
        attendingDays: r.attendingDays ?? [],
        interestedTracks: r.interestedTracks ?? [],
        reviewNotes: r.reviewNotes,
        reviewedAt: r.reviewedAt,
        submittedAt: r.submittedAt,
        createdAt: r.createdAt,
        checkedIn: Boolean(ck),
        checkinCount: ck?.count ?? 0,
        lastCheckinAt: ck?.lastAt ?? null,
        lastCheckinVenue: ck?.lastVenue ?? null,
        credentialStatus: cred?.status ?? null,
        credentialCode: cred?.code ?? null,
        hasAccommodationRequest: Boolean(accomm),
        accommodationStatus: accomm?.status ?? null,
        // Only expose accommodation details if user has accommodations.read
        accommodationTypes: canReadAccomm ? (accomm?.selectedTypes ?? []) : undefined,
        accommodationFreeText: canReadAccomm ? (accomm?.freeTextDetails ?? null) : undefined,
        volunteerDetails: volByReg.get(r.id) ?? null,
        sliDetails: sliByReg.get(r.id) ?? null,
      };
    });

    if (checkinStatus === "CHECKED_IN") {
      enriched = enriched.filter((r) => r.checkedIn);
    } else if (checkinStatus === "NOT_CHECKED_IN") {
      enriched = enriched.filter((r) => !r.checkedIn);
    }

    if (hasAccommodations === "true") {
      enriched = enriched.filter((r) => r.hasAccommodationRequest);
    } else if (hasAccommodations === "false") {
      enriched = enriched.filter((r) => !r.hasAccommodationRequest);
    }

    // Export handling (CSV / XLSX) honoring data minimization & RBAC
    if (format === "csv" || format === "xlsx") {
      const exportRows = enriched.map((r) => {
        const base: Record<string, string | number> = {
          "Registration Code": r.registrationCode,
          "Full Name": r.fullName,
          Email: r.email,
          Phone: r.phone,
          Country: r.country,
          Category: r.categoryCode,
          Status: r.status,
          "Payment Status": r.paymentStatus,
          Occupation: r.occupation || "",
          Organization: r.organizationName || "",
          Designation: r.designation || "",
          "Attending Days": (r.attendingDays || []).join(", "),
          "Credential Code": r.credentialCode || "",
          "Credential Status": r.credentialStatus || "",
          "Checked In": r.checkedIn ? "Yes" : "No",
          "Check-in Count": r.checkinCount,
          "Last Check-in Venue": r.lastCheckinVenue || "",
          "Submitted At": r.submittedAt ? new Date(r.submittedAt).toISOString() : "",
        };

        // Sensitive accommodation details ONLY when explicitly requested AND authorized
        if (includeAccommodations && canReadAccomm) {
          base["Accommodation Requested"] = r.hasAccommodationRequest ? "Yes" : "No";
          base["Accommodation Status"] = r.accommodationStatus || "";
          base["Accommodation Types"] = (r.accommodationTypes || []).join("; ");
          base["Accommodation Details"] = r.accommodationFreeText || "";
        }

        return base;
      });

      await recordAuditLog({
        actorUserId: user.id,
        action: "REGISTRATIONS_EXPORTED",
        resourceType: "registration",
        metadata: {
          format,
          rowCount: exportRows.length,
          includeAccommodations: includeAccommodations && canReadAccomm,
          filters: { search, category, status: statusParam, paymentStatus, country, checkinStatus },
        },
      });

      const dateSlug = new Date().toISOString().slice(0, 10);

      if (format === "csv") {
        const headers =
          exportRows.length > 0
            ? Object.keys(exportRows[0])
            : ["Registration Code", "Full Name", "Email", "Category", "Status"];
        const lines = [
          headers.map(escapeCsvCell).join(","),
          ...exportRows.map((row) => headers.map((h) => escapeCsvCell(row[h])).join(",")),
        ];
        const csvContent = lines.join("\r\n");

        return new NextResponse(csvContent, {
          status: 200,
          headers: {
            "Content-Type": "text/csv; charset=utf-8",
            "Content-Disposition": `attachment; filename="pfg2027-registrations-${dateSlug}.csv"`,
          },
        });
      }

      // XLSX export
      const worksheet = XLSX.utils.json_to_sheet(
        exportRows.length > 0
          ? exportRows
          : [{ "Registration Code": "", "Full Name": "", Category: "", Status: "" }]
      );
      const workbook = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(workbook, worksheet, "Registrations");
      const xlsxBuffer = XLSX.write(workbook, { type: "buffer", bookType: "xlsx" }) as Buffer;

      return new NextResponse(new Uint8Array(xlsxBuffer), {
        status: 200,
        headers: {
          "Content-Type":
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
          "Content-Disposition": `attachment; filename="pfg2027-registrations-${dateSlug}.xlsx"`,
        },
      });
    }

    const total = enriched.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    const offset = (page - 1) * pageSize;
    const items = enriched.slice(offset, offset + pageSize);

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
    console.error("GET /api/admin/registrations error:", err);
    return NextResponse.json({ error: "Failed to load registrations." }, { status: 500 });
  }
}
