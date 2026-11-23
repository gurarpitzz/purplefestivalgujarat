import { NextRequest, NextResponse } from "next/server";
import { desc, eq, sql } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission, recordAuditLog } from "@/lib/auth";
import { FESTIVAL_EDITION_SLUG, PERMISSIONS } from "@/lib/constants";

const createCouponSchema = z.object({
  code: z
    .string()
    .trim()
    .min(3, "Coupon code must be at least 3 characters.")
    .max(64)
    .transform((v) => v.toUpperCase().replace(/[^A-Z0-9_-]/g, "")),
  description: z.string().trim().max(500).optional().default(""),
  discountType: z.enum(["PERCENTAGE", "FIXED_AMOUNT", "FULL_WAIVER"]),
  discountValue: z.coerce.number().int().min(0).max(100000),
  eligibleCategories: z.array(z.string()).optional().default([]),
  maxUses: z.coerce.number().int().min(1).nullable().optional(),
  maxUsesPerUser: z.coerce.number().int().min(1).max(20).optional().default(1),
  validFrom: z.string().nullable().optional(),
  validUntil: z.string().nullable().optional(),
  isActive: z.boolean().optional().default(true),
});

const patchCouponSchema = z.object({
  id: z.string().uuid(),
  isActive: z.boolean().optional(),
  description: z.string().trim().max(500).optional(),
  maxUses: z.coerce.number().int().min(1).nullable().optional(),
  validUntil: z.string().nullable().optional(),
});

export async function GET() {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.COUPONS_MANAGE)) {
      return NextResponse.json(
        { error: "Permission coupons.manage is required." },
        { status: 403 }
      );
    }

    const coupons = await db
      .select()
      .from(schema.couponCodes)
      .orderBy(desc(schema.couponCodes.createdAt));

    const redemptionAgg = await db
      .select({
        couponId: schema.couponRedemptions.couponId,
        redemptionCount: sql<number>`count(*)::int`,
        totalDiscountInr: sql<number>`coalesce(sum(${schema.couponRedemptions.discountAppliedInr}), 0)::int`,
      })
      .from(schema.couponRedemptions)
      .groupBy(schema.couponRedemptions.couponId);

    const aggMap = new Map(redemptionAgg.map((r) => [r.couponId, r]));

    const items = coupons.map((c) => {
      const agg = aggMap.get(c.id);
      return {
        ...c,
        redemptionCount: Number(agg?.redemptionCount ?? c.usedCount ?? 0),
        totalDiscountInr: Number(agg?.totalDiscountInr ?? 0),
      };
    });

    return NextResponse.json({ items });
  } catch (err) {
    console.error("GET /api/admin/coupons error:", err);
    return NextResponse.json({ error: "Failed to load coupons." }, { status: 500 });
  }
}

export async function POST(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.COUPONS_MANAGE)) {
      return NextResponse.json(
        { error: "Permission coupons.manage is required." },
        { status: 403 }
      );
    }

    const body = await req.json().catch(() => null);
    const parsed = createCouponSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: "Invalid coupon payload.", details: parsed.error.flatten() },
        { status: 400 }
      );
    }

    const [edition] = await db
      .select()
      .from(schema.festivalEditions)
      .where(eq(schema.festivalEditions.slug, FESTIVAL_EDITION_SLUG))
      .limit(1);

    if (!edition) {
      return NextResponse.json({ error: "Active festival edition not found." }, { status: 404 });
    }

    const d = parsed.data;
    if (d.discountType === "PERCENTAGE" && d.discountValue > 100) {
      return NextResponse.json(
        { error: "Percentage discount cannot exceed 100%." },
        { status: 400 }
      );
    }

    const [created] = await db
      .insert(schema.couponCodes)
      .values({
        editionId: edition.id,
        code: d.code,
        description: d.description || null,
        discountType: d.discountType,
        discountValue: d.discountType === "FULL_WAIVER" ? 100 : d.discountValue,
        eligibleCategories: d.eligibleCategories,
        maxUses: d.maxUses ?? null,
        maxUsesPerUser: d.maxUsesPerUser,
        validFrom: d.validFrom ? new Date(d.validFrom) : null,
        validUntil: d.validUntil ? new Date(d.validUntil) : null,
        isActive: d.isActive,
        createdBy: user.id,
      })
      .returning();

    await recordAuditLog({
      actorUserId: user.id,
      action: "COUPON_CREATED",
      resourceType: "coupon_code",
      resourceId: created.id,
      metadata: { code: created.code, discountType: created.discountType, discountValue: created.discountValue },
    });

    return NextResponse.json({ item: created }, { status: 201 });
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : String(err);
    if (msg.toLowerCase().includes("unique") || msg.toLowerCase().includes("duplicate")) {
      return NextResponse.json({ error: "A coupon with this code already exists." }, { status: 409 });
    }
    console.error("POST /api/admin/coupons error:", err);
    return NextResponse.json({ error: "Failed to create coupon." }, { status: 500 });
  }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.COUPONS_MANAGE)) {
      return NextResponse.json(
        { error: "Permission coupons.manage is required." },
        { status: 403 }
      );
    }

    const body = await req.json().catch(() => null);
    const parsed = patchCouponSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json({ error: "Invalid coupon update." }, { status: 400 });
    }

    const { id, isActive, description, maxUses, validUntil } = parsed.data;

    const [updated] = await db
      .update(schema.couponCodes)
      .set({
        ...(isActive !== undefined ? { isActive } : {}),
        ...(description !== undefined ? { description } : {}),
        ...(maxUses !== undefined ? { maxUses } : {}),
        ...(validUntil !== undefined
          ? { validUntil: validUntil ? new Date(validUntil) : null }
          : {}),
      })
      .where(eq(schema.couponCodes.id, id))
      .returning();

    if (!updated) {
      return NextResponse.json({ error: "Coupon not found." }, { status: 404 });
    }

    await recordAuditLog({
      actorUserId: user.id,
      action: "COUPON_UPDATED",
      resourceType: "coupon_code",
      resourceId: updated.id,
      metadata: { code: updated.code, isActive: updated.isActive },
    });

    return NextResponse.json({ item: updated });
  } catch (err) {
    console.error("PATCH /api/admin/coupons error:", err);
    return NextResponse.json({ error: "Failed to update coupon." }, { status: 500 });
  }
}
