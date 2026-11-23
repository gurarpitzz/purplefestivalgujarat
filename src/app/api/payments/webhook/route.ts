import crypto from "crypto";
import { NextRequest, NextResponse } from "next/server";
import { eq } from "drizzle-orm";
import { db, schema } from "@/db";
import { recordAuditLog } from "@/lib/auth";
import { queueNotification } from "@/lib/notifications";
import { issueCredentialForRegistration } from "@/lib/qr";

export async function POST(req: NextRequest) {
  try {
    const webhookSecret = process.env.RAZORPAY_WEBHOOK_SECRET?.trim();
    if (!webhookSecret) {
      return NextResponse.json(
        {
          error:
            "Payment gateway is currently disabled pending organizer configuration.",
        },
        { status: 503 }
      );
    }

    const signature = req.headers.get("x-razorpay-signature");
    if (!signature) {
      return NextResponse.json(
        { error: "Missing webhook signature." },
        { status: 401 }
      );
    }

    const rawBody = await req.text();
    const expectedSignature = crypto
      .createHmac("sha256", webhookSecret)
      .update(rawBody)
      .digest("hex");

    const sigBuf = Buffer.from(signature);
    const expBuf = Buffer.from(expectedSignature);
    if (sigBuf.length !== expBuf.length || !crypto.timingSafeEqual(sigBuf, expBuf)) {
      return NextResponse.json(
        { error: "Invalid webhook signature." },
        { status: 401 }
      );
    }

    const payload = JSON.parse(rawBody) as {
      event?: string;
      payload?: {
        payment?: {
          entity?: {
            id?: string;
            order_id?: string;
            amount?: number;
            status?: string;
          };
        };
      };
    };

    const eventType = payload.event;
    const paymentEntity = payload.payload?.payment?.entity;
    const providerPaymentId = paymentEntity?.id;
    const providerOrderId = paymentEntity?.order_id;

    if (!providerPaymentId || !providerOrderId) {
      return NextResponse.json({ received: true, ignored: true });
    }

    // Idempotency check
    const [existingTx] = await db
      .select()
      .from(schema.paymentTransactions)
      .where(eq(schema.paymentTransactions.providerPaymentId, providerPaymentId))
      .limit(1);

    if (existingTx) {
      return NextResponse.json({ received: true, idempotent: true });
    }

    const [order] = await db
      .select()
      .from(schema.paymentOrders)
      .where(eq(schema.paymentOrders.providerOrderId, providerOrderId))
      .limit(1);

    if (!order) {
      return NextResponse.json(
        { error: "Matching payment order not found." },
        { status: 404 }
      );
    }

    const amountInr = Math.round((paymentEntity.amount ?? order.netAmountInr * 100) / 100);
    const webhookEventId = req.headers.get("x-razorpay-event-id") || null;

    if (eventType === "payment.captured") {
      await db.insert(schema.paymentTransactions).values({
        orderId: order.id,
        providerPaymentId,
        webhookEventId,
        status: "CAPTURED",
        amountInr,
        rawPayload: payload,
        verifiedAt: new Date(),
      });

      await db
        .update(schema.paymentOrders)
        .set({ status: "CAPTURED", updatedAt: new Date() })
        .where(eq(schema.paymentOrders.id, order.id));

      const [reg] = await db
        .select()
        .from(schema.registrations)
        .where(eq(schema.registrations.id, order.registrationId))
        .limit(1);

      if (reg) {
        const [cat] = await db
          .select()
          .from(schema.registrationCategories)
          .where(eq(schema.registrationCategories.id, reg.categoryId))
          .limit(1);

        const nextStatus = cat?.requiresApproval ? reg.status : "CONFIRMED";

        await db
          .update(schema.registrations)
          .set({
            paymentStatus: "CAPTURED",
            status: nextStatus,
            updatedAt: new Date(),
          })
          .where(eq(schema.registrations.id, reg.id));

        if (nextStatus === "CONFIRMED" || nextStatus === "APPROVED") {
          await issueCredentialForRegistration(reg.id, null);
          await queueNotification({
            templateCode: "REGISTRATION_CONFIRMED",
            recipientUserId: reg.userId,
            recipientEmail: reg.email,
            variables: {
              fullName: reg.fullName,
              registrationCode: reg.registrationCode,
              category: cat?.name || reg.categoryCode,
            },
            fallbackSubject: `Confirmed: Purple Festival Gujarat 2027 (${reg.registrationCode})`,
          });
        }

        await recordAuditLog({
          actorUserId: reg.userId,
          action: "PAYMENT_CAPTURED",
          resourceType: "registration",
          resourceId: reg.id,
          metadata: { providerOrderId, providerPaymentId, amountInr },
        });
      }
    } else if (eventType === "payment.failed") {
      await db.insert(schema.paymentTransactions).values({
        orderId: order.id,
        providerPaymentId,
        webhookEventId,
        status: "FAILED",
        amountInr,
        rawPayload: payload,
        verifiedAt: new Date(),
      });

      await db
        .update(schema.paymentOrders)
        .set({ status: "FAILED", updatedAt: new Date() })
        .where(eq(schema.paymentOrders.id, order.id));

      await db
        .update(schema.registrations)
        .set({ paymentStatus: "FAILED", updatedAt: new Date() })
        .where(eq(schema.registrations.id, order.registrationId));
    }

    return NextResponse.json({ received: true });
  } catch (err) {
    console.error("POST /api/payments/webhook error:", err);
    return NextResponse.json(
      { error: "Unable to process payment webhook." },
      { status: 500 }
    );
  }
}
