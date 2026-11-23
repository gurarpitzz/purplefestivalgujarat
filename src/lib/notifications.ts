import { eq } from "drizzle-orm";
import { db, schema } from "@/db";

function renderTemplate(template: string, variables: Record<string, string>): string {
  return template.replace(/\{\{(\w+)\}\}/g, (_, key) => variables[key] ?? "");
}

/**
 * Queues a notification in PostgreSQL and attempts delivery if an approved email provider
 * (e.g., Resend) is configured. Never fabricates DELIVERED status when unconfigured.
 */
export async function queueNotification(params: {
  templateCode: string;
  recipientUserId?: string | null;
  recipientEmail: string;
  variables: Record<string, string>;
  fallbackSubject?: string;
}) {
  const [template] = await db
    .select()
    .from(schema.notificationTemplates)
    .where(eq(schema.notificationTemplates.code, params.templateCode))
    .limit(1);

  const subject = template
    ? renderTemplate(template.subject, params.variables)
    : params.fallbackSubject || "Purple Festival Gujarat 2027 Notification";

  const bodyHtml = template
    ? renderTemplate(template.bodyHtml, params.variables)
    : `<p>${subject}</p>`;

  const [job] = await db
    .insert(schema.notificationJobs)
    .values({
      templateCode: params.templateCode,
      recipientUserId: params.recipientUserId ?? null,
      recipientEmail: params.recipientEmail,
      subject,
      payload: params.variables,
      status: "QUEUED",
      attempts: 0,
    })
    .returning();

  const provider = process.env.EMAIL_PROVIDER?.trim().toLowerCase() || "none";
  const resendKey = process.env.RESEND_API_KEY?.trim();

  if (provider === "resend" && resendKey) {
    try {
      const res = await fetch("https://api.resend.com/emails", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${resendKey}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          from:
            process.env.EMAIL_FROM ||
            "Purple Festival Gujarat 2027 <noreply@purplefestivalgujarat.com>",
          to: [params.recipientEmail],
          subject,
          html: bodyHtml,
        }),
      });

      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        await db
          .update(schema.notificationJobs)
          .set({ status: "SENT", attempts: 1, updatedAt: new Date() })
          .where(eq(schema.notificationJobs.id, job.id));

        await db.insert(schema.notificationDeliveryLogs).values({
          jobId: job.id,
          provider: "resend",
          providerMessageId: (data as { id?: string }).id || null,
          status: "SENT",
          responseDetails: data,
        });
      } else {
        await db
          .update(schema.notificationJobs)
          .set({
            status: "FAILED",
            attempts: 1,
            lastError: JSON.stringify(data),
            nextRetryAt: new Date(Date.now() + 1000 * 60 * 5),
            updatedAt: new Date(),
          })
          .where(eq(schema.notificationJobs.id, job.id));
      }
    } catch (err) {
      await db
        .update(schema.notificationJobs)
        .set({
          status: "FAILED",
          attempts: 1,
          lastError: err instanceof Error ? err.message : String(err),
          nextRetryAt: new Date(Date.now() + 1000 * 60 * 5),
          updatedAt: new Date(),
        })
        .where(eq(schema.notificationJobs.id, job.id));
    }
  } else {
    // Email provider not configured: record honestly as QUEUED (never claim SENT/DELIVERED)
    await db.insert(schema.notificationDeliveryLogs).values({
      jobId: job.id,
      provider: "unconfigured_queue",
      status: "QUEUED",
      responseDetails: {
        note: "Email provider not configured; notification queued for delivery once EMAIL_PROVIDER is set.",
      },
    });
  }

  return job;
}

