import { NextRequest, NextResponse } from "next/server";
import { and, asc, desc, eq } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, recordAuditLog } from "@/lib/auth";

const createAccommodationSchema = z.object({
  registrationId: z.string().uuid("Please select a registration."),
  selectedTypes: z.array(z.string().trim().min(1)).default([]),
  freeTextDetails: z.string().trim().max(1000).optional().or(z.literal("")),
});

export async function GET() {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const [requests, accommodationTypes] = await Promise.all([
      db
        .select({
          id: schema.accommodationRequests.id,
          registrationId: schema.accommodationRequests.registrationId,
          selectedTypes: schema.accommodationRequests.selectedTypes,
          freeTextDetails: schema.accommodationRequests.freeTextDetails,
          status: schema.accommodationRequests.status,
          createdAt: schema.accommodationRequests.createdAt,
          updatedAt: schema.accommodationRequests.updatedAt,
          registrationCode: schema.registrations.registrationCode,
          categoryCode: schema.registrations.categoryCode,
        })
        .from(schema.accommodationRequests)
        .innerJoin(
          schema.registrations,
          eq(schema.accommodationRequests.registrationId, schema.registrations.id)
        )
        .where(eq(schema.accommodationRequests.userId, user.id))
        .orderBy(desc(schema.accommodationRequests.createdAt)),
      db
        .select()
        .from(schema.accommodationTypes)
        .where(eq(schema.accommodationTypes.isActive, true))
        .orderBy(asc(schema.accommodationTypes.displayOrder)),
    ]);

    return NextResponse.json({ requests, accommodationTypes });
  } catch (err) {
    console.error("GET /api/account/accommodations error:", err);
    return NextResponse.json(
      { error: "Unable to load accommodation requests." },
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
    const parsed = createAccommodationSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        {
          error:
            parsed.error.issues[0]?.message ||
            "Please provide accommodation details.",
        },
        { status: 400 }
      );
    }

    const { registrationId, selectedTypes, freeTextDetails } = parsed.data;
    if (selectedTypes.length === 0 && (!freeTextDetails || !freeTextDetails.trim())) {
      return NextResponse.json(
        {
          error:
            "Please select at least one accommodation type or describe your requirement.",
        },
        { status: 400 }
      );
    }

    const [reg] = await db
      .select()
      .from(schema.registrations)
      .where(
        and(
          eq(schema.registrations.id, registrationId),
          eq(schema.registrations.userId, user.id)
        )
      )
      .limit(1);

    if (!reg) {
      return NextResponse.json(
        { error: "Registration not found for your account." },
        { status: 404 }
      );
    }

    const [created] = await db
      .insert(schema.accommodationRequests)
      .values({
        registrationId: reg.id,
        userId: user.id,
        selectedTypes,
        freeTextDetails: freeTextDetails || null,
        status: "REQUESTED",
      })
      .returning();

    const ipAddress =
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
      req.headers.get("x-real-ip") ||
      null;

    await recordAuditLog({
      actorUserId: user.id,
      action: "ACCOMMODATION_REQUEST_SUBMITTED",
      resourceType: "accommodation_request",
      resourceId: created.id,
      ipAddress,
      userAgent: req.headers.get("user-agent") || null,
      metadata: {
        registrationId: reg.id,
        selectedCount: selectedTypes.length,
      },
    });

    return NextResponse.json(
      {
        request: {
          ...created,
          registrationCode: reg.registrationCode,
          categoryCode: reg.categoryCode,
        },
        message:
          "Your accessibility accommodation request has been submitted to our coordinators.",
      },
      { status: 201 }
    );
  } catch (err) {
    console.error("POST /api/account/accommodations error:", err);
    return NextResponse.json(
      { error: "Unable to submit accommodation request." },
      { status: 500 }
    );
  }
}
