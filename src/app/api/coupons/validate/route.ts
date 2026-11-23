import { NextRequest, NextResponse } from "next/server";
import { and, eq } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser } from "@/lib/auth";
import { FESTIVAL_EDITION_SLUG } from "@/lib/constants";

const validateCouponSchema = z.object({
  code: z.string().trim().min(2, "Please enter a coupon code.").max(64),
  categoryCode: z.string().trim().min(2).max(64),
});

export async function POST(req: NextRequest) {
  try {
    const body = await req.json().catch(() => null);
    const parsed = validateCouponSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        {
          valid: false,
          error: parsed.error.issues[0]?.message || "Invalid coupon input.",
        },
        { status: 400 }
      );
    }

    const code = parsed.data.code.toUpperCase();
    const categoryCode = parsed.data.categoryCode.toUpperCase();

    const [edition] = await db
      .select()
      .from(schema.festivalEditions)
      .where(eq(schema.festivalEditions.slug, FESTIVAL_EDITION_SLUG))
      .limit(1);

    if (!edition) {
      return NextResponse.json(
        { valid: false, error: "Active festival edition not found." },
        { status: 404 }
      );
    }

    const [category] = await db
      .select()
      .from(schema.registrationCategories)
      .where(
        and(
          eq(schema.registrationCategories.editionId, edition.id),
          eq(schema.registrationCategories.code, categoryCode)
        )
      )
      .limit(1);

    if (!category) {
      return NextResponse.json(
        { valid: false, error: "Selected registration category not found." },
        { status: 400 }
      );
    }

    const [coupon] = await db
      .select()
      .from(schema.couponCodes)
      .where(
        and(
          eq(schema.couponCodes.editionId, edition.id),
          eq(schema.couponCodes.code, code)
        )
      )
      .limit(1);

    if (!coupon || !coupon.isActive) {
      return NextResponse.json(
        { valid: false, error: "Coupon code is invalid or no longer active." },
        { status: 404 }
      );
    }

    const now = new Date();
    if (coupon.validFrom && coupon.validFrom > now) {
      return NextResponse.json(
        { valid: false, error: "This coupon code is not active yet." },
        { status: 400 }
      );
    }

    if (coupon.validUntil && coupon.validUntil < now) {
      return NextResponse.json(
        { valid: false, error: "This coupon code has expired." },
        { status: 400 }
      );
    }

    if (coupon.maxUses !== null && coupon.usedCount >= coupon.maxUses) {
      return NextResponse.json(
        { valid: false, error: "This coupon code has reached its redemption limit." },
        { status: 400 }
      );
    }

    const eligible = coupon.eligibleCategories || [];
    if (eligible.length > 0 && !eligible.includes(categoryCode)) {
      return NextResponse.json(
        {
          valid: false,
          error: `Coupon ${code} does not apply to the ${category.name} category.`,
        },
        { status: 400 }
      );
    }

    const user = await getCurrentUser();
    if (user) {
      const prior = await db
        .select({ id: schema.couponRedemptions.id })
        .from(schema.couponRedemptions)
        .where(
          and(
            eq(schema.couponRedemptions.couponId, coupon.id),
            eq(schema.couponRedemptions.userId, user.id)
          )
        );
      if (prior.length >= coupon.maxUsesPerUser) {
        return NextResponse.json(
          { valid: false, error: "You have already redeemed this coupon code." },
          { status: 400 }
        );
      }
    }

    const baseAmountInr = category.feeAmountInr ?? 0;
    let discountInr = 0;
    if (coupon.discountType === "FULL_WAIVER") {
      discountInr = baseAmountInr;
    } else if (coupon.discountType === "PERCENTAGE") {
      discountInr = Math.min(
        baseAmountInr,
        Math.round((baseAmountInr * coupon.discountValue) / 100)
      );
    } else {
      discountInr = Math.min(baseAmountInr, coupon.discountValue);
    }

    const netAmountInr = Math.max(0, baseAmountInr - discountInr);

    return NextResponse.json({
      valid: true,
      coupon: {
        code: coupon.code,
        description: coupon.description,
        discountType: coupon.discountType,
        discountValue: coupon.discountValue,
      },
      pricing: {
        baseAmountInr,
        discountInr,
        netAmountInr,
      },
    });
  } catch (err) {
    console.error("POST /api/coupons/validate error:", err);
    return NextResponse.json(
      { valid: false, error: "Unable to validate coupon right now." },
      { status: 500 }
    );
  }
}
