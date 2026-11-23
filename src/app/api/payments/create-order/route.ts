import { NextRequest, NextResponse } from "next/server";
import { and, eq } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, recordAuditLog } from "@/lib/auth";

const createOrderSchema = z.object({
  registrationId: z.string().uuid("Invalid registration ID."),
});

export async function POST(req: NextRequest) {
  try {
    const keyId = process.env.RAZORPAY_KEY_ID?.trim();
    const keySecret = process.env.RAZORPAY_KEY_SECRET?.trim();
    const webhookSecret = process.env.RAZORPAY_WEBHOOK_SECRET?.trim();

    if (!keyId || !keySecret || !webhookSecret) {
      return NextResponse.json(
        {
          error:
            "Payment gateway is currently disabled pending organizer configuration.",
          gatewayConfigured: false,
        },
        { status: 503 }
      );
    }

    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const body = await req.json().catch(() => null);
    const parsed = createOrderSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json(
        { error: parsed.error.issues[0]?.message || "Invalid request." },
        { status: 400 }
      );
    }

    const [reg] = await db
      .select()
      .from(schema.registrations)
      .where(
        and(
          eq(schema.registrations.id, parsed.data.registrationId),
          eq(schema.registrations.userId, user.id)
        )
      )
      .limit(1);

    if (!reg) {
      return NextResponse.json({ error: "Registration not found." }, { status: 404 });
    }

    if (reg.paymentStatus !== "PENDING") {
      return NextResponse.json(
        { error: `Payment is not pending for this registration (${reg.paymentStatus}).` },
        { status: 400 }
      );
    }

    const [category] = await db
      .select()
      .from(schema.registrationCategories)
      .where(eq(schema.registrationCategories.id, reg.categoryId))
      .limit(1);

    const baseAmountInr = category?.feeAmountInr ?? 0;

    const redemptions = await db
      .select()
      .from(schema.couponRedemptions)
      .where(eq(schema.couponRedemptions.registrationId, reg.id));

    const discountInr = redemptions.reduce(
      (sum, r) => sum + (r.discountAppliedInr || 0),
      0
    );
    const netAmountInr = Math.max(0, baseAmountInr - discountInr);

    if (netAmountInr <= 0) {
      return NextResponse.json(
        { error: "No payment amount is due for this registration." },
        { status: 400 }
      );
    }

    const receiptNumber = `PFG-${reg.registrationCode}`.slice(0, 40);
    const authHeader = Buffer.from(`${keyId}:${keySecret}`).toString("base64");

    const rpRes = await fetch("https://api.razorpay.com/v1/orders", {
      method: "POST",
      headers: {
        Authorization: `Basic ${authHeader}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        amount: netAmountInr * 100, // Razorpay expects paise
        currency: "INR",
        receipt: receiptNumber,
        notes: {
          registrationId: reg.id,
          registrationCode: reg.registrationCode,
          userId: user.id,
        },
      }),
    });

    const rpData = (await rpRes.json().catch(() => ({}))) as {
      id?: string;
      error?: { description?: string };
    };

    if (!rpRes.ok || !rpData.id) {
      return NextResponse.json(
        {
          error:
            rpData.error?.description ||
            "Failed to create Razorpay order with payment gateway.",
        },
        { status: 502 }
      );
    }

    const [order] = await db
      .insert(schema.paymentOrders)
      .values({
        registrationId: reg.id,
        userId: user.id,
        provider: "RAZORPAY",
        providerOrderId: rpData.id,
        amountInr: baseAmountInr,
        discountInr,
        netAmountInr,
        currency: "INR",
        status: "PENDING",
        receiptNumber,
      })
      .returning();

    await recordAuditLog({
      actorUserId: user.id,
      action: "PAYMENT_ORDER_CREATED",
      resourceType: "payment_order",
      resourceId: order.id,
      metadata: {
        providerOrderId: rpData.id,
        registrationId: reg.id,
        netAmountInr,
      },
    });

    return NextResponse.json({
      orderId: order.id,
      providerOrderId: rpData.id,
      amountPaise: netAmountInr * 100,
      netAmountInr,
      currency: "INR",
      keyId,
    });
  } catch (err) {
    console.error("POST /api/payments/create-order error:", err);
    return NextResponse.json(
      { error: "Unable to initiate payment order." },
      { status: 500 }
    );
  }
}
