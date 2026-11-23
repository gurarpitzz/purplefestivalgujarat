import { NextRequest, NextResponse } from "next/server";
import { asc, desc, eq, and } from "drizzle-orm";
import { z } from "zod";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission, recordAuditLog } from "@/lib/auth";
import { FESTIVAL_EDITION_SLUG, PERMISSIONS } from "@/lib/constants";

async function getActiveEdition() {
  const [edition] = await db
    .select()
    .from(schema.festivalEditions)
    .where(eq(schema.festivalEditions.slug, FESTIVAL_EDITION_SLUG))
    .limit(1);
  if (edition) return edition;
  const [anyEdition] = await db.select().from(schema.festivalEditions).limit(1);
  return anyEdition ?? null;
}

async function recordContentRevision(params: {
  entityType: string;
  entityId: string;
  snapshot: unknown;
  changedBy: string;
  changeSummary: string;
}) {
  const existing = await db
    .select({ versionNumber: schema.contentRevisions.versionNumber })
    .from(schema.contentRevisions)
    .where(
      and(
        eq(schema.contentRevisions.entityType, params.entityType),
        eq(schema.contentRevisions.entityId, params.entityId)
      )
    )
    .orderBy(desc(schema.contentRevisions.versionNumber))
    .limit(1);

  const nextVersion = (existing[0]?.versionNumber ?? 0) + 1;
  await db.insert(schema.contentRevisions).values({
    entityType: params.entityType,
    entityId: params.entityId,
    versionNumber: nextVersion,
    snapshot: params.snapshot as Record<string, unknown>,
    changedBy: params.changedBy,
    changeSummary: params.changeSummary,
  });
}

export async function GET() {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.CMS_MANAGE)) {
      return NextResponse.json(
        { error: "Permission cms.manage is required." },
        { status: 403 }
      );
    }

    const edition = await getActiveEdition();
    if (!edition) {
      return NextResponse.json({ error: "No festival edition configured." }, { status: 404 });
    }

    const [
      pages,
      programmeItems,
      partnerRows,
      faqItems,
      announcements,
      registrationCategories,
      recentRevisions,
    ] = await Promise.all([
      db
        .select()
        .from(schema.cmsPages)
        .where(eq(schema.cmsPages.editionId, edition.id)),
      db
        .select()
        .from(schema.programmeItems)
        .where(eq(schema.programmeItems.editionId, edition.id))
        .orderBy(asc(schema.programmeItems.dayNumber), asc(schema.programmeItems.displayOrder)),
      db
        .select({
          id: schema.partners.id,
          editionId: schema.partners.editionId,
          organizationId: schema.partners.organizationId,
          category: schema.partners.category,
          consentVerified: schema.partners.consentVerified,
          displayOrder: schema.partners.displayOrder,
          status: schema.partners.status,
          updatedAt: schema.partners.updatedAt,
          organizationName: schema.organizations.name,
          shortName: schema.organizations.shortName,
          websiteUrl: schema.organizations.websiteUrl,
          logoUrl: schema.organizations.logoUrl,
          logoAltText: schema.organizations.logoAltText,
          description: schema.organizations.description,
        })
        .from(schema.partners)
        .innerJoin(schema.organizations, eq(schema.partners.organizationId, schema.organizations.id))
        .where(eq(schema.partners.editionId, edition.id))
        .orderBy(asc(schema.partners.displayOrder)),
      db
        .select()
        .from(schema.faqItems)
        .where(eq(schema.faqItems.editionId, edition.id))
        .orderBy(asc(schema.faqItems.displayOrder)),
      db
        .select()
        .from(schema.announcements)
        .where(eq(schema.announcements.editionId, edition.id))
        .orderBy(desc(schema.announcements.createdAt)),
      db
        .select()
        .from(schema.registrationCategories)
        .where(eq(schema.registrationCategories.editionId, edition.id))
        .orderBy(asc(schema.registrationCategories.displayOrder)),
      db
        .select()
        .from(schema.contentRevisions)
        .orderBy(desc(schema.contentRevisions.createdAt))
        .limit(25),
    ]);

    const sections =
      pages.length > 0
        ? await db
            .select()
            .from(schema.cmsSections)
            .where(eq(schema.cmsSections.pageId, pages[0].id))
            .orderBy(asc(schema.cmsSections.displayOrder))
        : [];

    return NextResponse.json({
      edition,
      sections,
      programmeItems,
      partners: partnerRows,
      faqItems,
      announcements,
      registrationCategories,
      recentRevisions,
    });
  } catch (err) {
    console.error("GET /api/admin/cms error:", err);
    return NextResponse.json({ error: "Failed to load CMS content." }, { status: 500 });
  }
}

const createEntitySchema = z.object({
  entityType: z.enum(["programme", "partner", "faq", "announcement"]),
  payload: z.record(z.unknown()),
});

export async function POST(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.CMS_MANAGE)) {
      return NextResponse.json({ error: "Permission cms.manage is required." }, { status: 403 });
    }

    const edition = await getActiveEdition();
    if (!edition) {
      return NextResponse.json({ error: "No festival edition configured." }, { status: 404 });
    }

    const body = await req.json().catch(() => null);
    const parsed = createEntitySchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json({ error: "Invalid CMS create request." }, { status: 400 });
    }

    const { entityType, payload } = parsed.data;

    if (entityType === "programme") {
      const p = z
        .object({
          dayNumber: z.coerce.number().int().min(1).max(10),
          timeSlot: z.string().trim().min(2).max(80),
          title: z.string().trim().min(2).max(250),
          description: z.string().trim().max(3000).optional().default(""),
          category: z.string().trim().min(2).max(100),
          venueName: z.string().trim().max(200).optional().default(""),
          accessibilityServices: z.array(z.string()).optional().default([]),
          displayOrder: z.coerce.number().int().optional().default(0),
          status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional().default("PUBLISHED"),
        })
        .parse(payload);

      const [created] = await db
        .insert(schema.programmeItems)
        .values({
          editionId: edition.id,
          dayNumber: p.dayNumber,
          timeSlot: p.timeSlot,
          title: p.title,
          description: p.description || null,
          category: p.category,
          venueName: p.venueName || null,
          accessibilityServices: p.accessibilityServices,
          displayOrder: p.displayOrder,
          status: p.status,
        })
        .returning();

      await recordContentRevision({
        entityType: "programme_item",
        entityId: created.id,
        snapshot: created,
        changedBy: user.id,
        changeSummary: `Created programme item "${created.title}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_PROGRAMME_CREATED",
        resourceType: "programme_item",
        resourceId: created.id,
        metadata: { title: created.title },
      });

      return NextResponse.json({ item: created }, { status: 201 });
    }

    if (entityType === "partner") {
      const p = z
        .object({
          organizationName: z.string().trim().min(2).max(250),
          shortName: z.string().trim().max(100).optional().default(""),
          websiteUrl: z.string().trim().max(500).optional().default(""),
          logoUrl: z.string().trim().max(500).optional().default(""),
          logoAltText: z.string().trim().max(300).optional().default(""),
          description: z.string().trim().max(2000).optional().default(""),
          category: z.string().trim().min(2).max(100),
          consentVerified: z.boolean().optional().default(false),
          displayOrder: z.coerce.number().int().optional().default(0),
          status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional().default("DRAFT"),
        })
        .parse(payload);

      if (p.status === "PUBLISHED" && !p.consentVerified) {
        return NextResponse.json(
          { error: "Partner cannot be published without verified partner consent." },
          { status: 400 }
        );
      }

      const [org] = await db
        .insert(schema.organizations)
        .values({
          name: p.organizationName,
          shortName: p.shortName || null,
          websiteUrl: p.websiteUrl || null,
          logoUrl: p.logoUrl || null,
          logoAltText: p.logoAltText || `${p.organizationName} logo`,
          description: p.description || null,
        })
        .returning();

      const [partner] = await db
        .insert(schema.partners)
        .values({
          editionId: edition.id,
          organizationId: org.id,
          category: p.category,
          consentVerified: p.consentVerified,
          displayOrder: p.displayOrder,
          status: p.status,
        })
        .returning();

      await recordContentRevision({
        entityType: "partner",
        entityId: partner.id,
        snapshot: { partner, organization: org },
        changedBy: user.id,
        changeSummary: `Added partner "${org.name}" (${partner.status})`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_PARTNER_CREATED",
        resourceType: "partner",
        resourceId: partner.id,
        metadata: { organizationName: org.name, consentVerified: partner.consentVerified, status: partner.status },
      });

      return NextResponse.json({ item: { ...partner, organizationName: org.name } }, { status: 201 });
    }

    if (entityType === "faq") {
      const p = z
        .object({
          category: z.string().trim().min(2).max(80).default("General"),
          question: z.string().trim().min(4).max(1000),
          answer: z.string().trim().min(4).max(4000),
          displayOrder: z.coerce.number().int().optional().default(0),
          status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional().default("PUBLISHED"),
        })
        .parse(payload);

      const [created] = await db
        .insert(schema.faqItems)
        .values({
          editionId: edition.id,
          category: p.category,
          question: p.question,
          answer: p.answer,
          displayOrder: p.displayOrder,
          status: p.status,
        })
        .returning();

      await recordContentRevision({
        entityType: "faq_item",
        entityId: created.id,
        snapshot: created,
        changedBy: user.id,
        changeSummary: `Created FAQ "${created.question.slice(0, 80)}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_FAQ_CREATED",
        resourceType: "faq_item",
        resourceId: created.id,
      });

      return NextResponse.json({ item: created }, { status: 201 });
    }

    // announcement
    const p = z
      .object({
        title: z.string().trim().min(2).max(200),
        message: z.string().trim().min(4).max(3000),
        severity: z.enum(["INFO", "IMPORTANT", "ALERT"]).optional().default("INFO"),
        status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional().default("PUBLISHED"),
      })
      .parse(payload);

    const [created] = await db
      .insert(schema.announcements)
      .values({
        editionId: edition.id,
        title: p.title,
        message: p.message,
        severity: p.severity,
        status: p.status,
        createdBy: user.id,
      })
      .returning();

    await recordContentRevision({
      entityType: "announcement",
      entityId: created.id,
      snapshot: created,
      changedBy: user.id,
      changeSummary: `Created announcement "${created.title}"`,
    });
    await recordAuditLog({
      actorUserId: user.id,
      action: "CMS_ANNOUNCEMENT_CREATED",
      resourceType: "announcement",
      resourceId: created.id,
    });

    return NextResponse.json({ item: created }, { status: 201 });
  } catch (err) {
    console.error("POST /api/admin/cms error:", err);
    return NextResponse.json({ error: "Failed to create CMS record." }, { status: 500 });
  }
}

const patchEntitySchema = z.object({
  entityType: z.enum(["section", "programme", "partner", "faq", "announcement", "category"]),
  id: z.string().uuid(),
  payload: z.record(z.unknown()),
});

export async function PATCH(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.CMS_MANAGE)) {
      return NextResponse.json({ error: "Permission cms.manage is required." }, { status: 403 });
    }

    const body = await req.json().catch(() => null);
    const parsed = patchEntitySchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json({ error: "Invalid CMS update request." }, { status: 400 });
    }

    const { entityType, id, payload } = parsed.data;

    if (entityType === "section") {
      const p = z
        .object({
          title: z.string().max(250).optional(),
          subtitle: z.string().max(1000).optional(),
          body: z.string().max(10000).optional(),
          status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional(),
          data: z.record(z.unknown()).optional(),
        })
        .parse(payload);

      const [updated] = await db
        .update(schema.cmsSections)
        .set({
          ...(p.title !== undefined ? { title: p.title } : {}),
          ...(p.subtitle !== undefined ? { subtitle: p.subtitle } : {}),
          ...(p.body !== undefined ? { body: p.body } : {}),
          ...(p.status !== undefined ? { status: p.status } : {}),
          ...(p.data !== undefined ? { data: p.data } : {}),
          updatedBy: user.id,
          updatedAt: new Date(),
        })
        .where(eq(schema.cmsSections.id, id))
        .returning();

      if (!updated) return NextResponse.json({ error: "Section not found." }, { status: 404 });

      await recordContentRevision({
        entityType: "cms_section",
        entityId: updated.id,
        snapshot: updated,
        changedBy: user.id,
        changeSummary: `Updated section "${updated.sectionKey}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_SECTION_UPDATED",
        resourceType: "cms_section",
        resourceId: updated.id,
        metadata: { sectionKey: updated.sectionKey },
      });

      return NextResponse.json({ item: updated });
    }

    if (entityType === "programme") {
      const p = z
        .object({
          dayNumber: z.coerce.number().int().min(1).max(10).optional(),
          timeSlot: z.string().max(80).optional(),
          title: z.string().max(250).optional(),
          description: z.string().max(3000).optional(),
          category: z.string().max(100).optional(),
          venueName: z.string().max(200).optional(),
          status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional(),
          displayOrder: z.coerce.number().int().optional(),
        })
        .parse(payload);

      const [updated] = await db
        .update(schema.programmeItems)
        .set({
          ...p,
          updatedAt: new Date(),
        })
        .where(eq(schema.programmeItems.id, id))
        .returning();

      if (!updated) return NextResponse.json({ error: "Programme item not found." }, { status: 404 });

      await recordContentRevision({
        entityType: "programme_item",
        entityId: updated.id,
        snapshot: updated,
        changedBy: user.id,
        changeSummary: `Updated programme item "${updated.title}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_PROGRAMME_UPDATED",
        resourceType: "programme_item",
        resourceId: updated.id,
      });

      return NextResponse.json({ item: updated });
    }

    if (entityType === "partner") {
      const p = z
        .object({
          consentVerified: z.boolean().optional(),
          status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional(),
          category: z.string().max(100).optional(),
          displayOrder: z.coerce.number().int().optional(),
          organizationName: z.string().max(250).optional(),
          websiteUrl: z.string().max(500).optional(),
        })
        .parse(payload);

      const [existing] = await db
        .select()
        .from(schema.partners)
        .where(eq(schema.partners.id, id))
        .limit(1);

      if (!existing) return NextResponse.json({ error: "Partner not found." }, { status: 404 });

      const nextConsent = p.consentVerified ?? existing.consentVerified;
      const nextStatus = p.status ?? existing.status;

      if (nextStatus === "PUBLISHED" && !nextConsent) {
        return NextResponse.json(
          { error: "Partner consent must be verified before publishing." },
          { status: 400 }
        );
      }

      if (p.organizationName || p.websiteUrl !== undefined) {
        await db
          .update(schema.organizations)
          .set({
            ...(p.organizationName ? { name: p.organizationName } : {}),
            ...(p.websiteUrl !== undefined ? { websiteUrl: p.websiteUrl || null } : {}),
          })
          .where(eq(schema.organizations.id, existing.organizationId));
      }

      const [updated] = await db
        .update(schema.partners)
        .set({
          consentVerified: nextConsent,
          status: nextStatus,
          category: p.category ?? existing.category,
          displayOrder: p.displayOrder ?? existing.displayOrder,
          updatedAt: new Date(),
        })
        .where(eq(schema.partners.id, id))
        .returning();

      await recordContentRevision({
        entityType: "partner",
        entityId: updated.id,
        snapshot: updated,
        changedBy: user.id,
        changeSummary: `Updated partner status=${updated.status}, consentVerified=${updated.consentVerified}`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_PARTNER_UPDATED",
        resourceType: "partner",
        resourceId: updated.id,
        metadata: { status: updated.status, consentVerified: updated.consentVerified },
      });

      return NextResponse.json({ item: updated });
    }

    if (entityType === "faq") {
      const p = z
        .object({
          category: z.string().max(80).optional(),
          question: z.string().max(1000).optional(),
          answer: z.string().max(4000).optional(),
          displayOrder: z.coerce.number().int().optional(),
          status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional(),
        })
        .parse(payload);

      const [updated] = await db
        .update(schema.faqItems)
        .set({ ...p, updatedAt: new Date() })
        .where(eq(schema.faqItems.id, id))
        .returning();

      if (!updated) return NextResponse.json({ error: "FAQ not found." }, { status: 404 });

      await recordContentRevision({
        entityType: "faq_item",
        entityId: updated.id,
        snapshot: updated,
        changedBy: user.id,
        changeSummary: `Updated FAQ "${updated.question.slice(0, 80)}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_FAQ_UPDATED",
        resourceType: "faq_item",
        resourceId: updated.id,
      });

      return NextResponse.json({ item: updated });
    }

    if (entityType === "announcement") {
      const p = z
        .object({
          title: z.string().max(200).optional(),
          message: z.string().max(3000).optional(),
          severity: z.enum(["INFO", "IMPORTANT", "ALERT"]).optional(),
          status: z.enum(["DRAFT", "PUBLISHED", "ARCHIVED"]).optional(),
        })
        .parse(payload);

      const [updated] = await db
        .update(schema.announcements)
        .set(p)
        .where(eq(schema.announcements.id, id))
        .returning();

      if (!updated) return NextResponse.json({ error: "Announcement not found." }, { status: 404 });

      await recordContentRevision({
        entityType: "announcement",
        entityId: updated.id,
        snapshot: updated,
        changedBy: user.id,
        changeSummary: `Updated announcement "${updated.title}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_ANNOUNCEMENT_UPDATED",
        resourceType: "announcement",
        resourceId: updated.id,
      });

      return NextResponse.json({ item: updated });
    }

    // registrationCategory
    const p = z
      .object({
        isActive: z.boolean().optional(),
        feeAmountInr: z.coerce.number().int().min(0).max(500000).optional(),
        requiresPayment: z.boolean().optional(),
        requiresApproval: z.boolean().optional(),
        opensAt: z.string().nullable().optional(),
        closesAt: z.string().nullable().optional(),
        description: z.string().max(1500).optional(),
        confirmationMessage: z.string().max(1500).optional(),
      })
      .parse(payload);

    const [updated] = await db
      .update(schema.registrationCategories)
      .set({
        ...(p.isActive !== undefined ? { isActive: p.isActive } : {}),
        ...(p.feeAmountInr !== undefined ? { feeAmountInr: p.feeAmountInr } : {}),
        ...(p.requiresPayment !== undefined ? { requiresPayment: p.requiresPayment } : {}),
        ...(p.requiresApproval !== undefined ? { requiresApproval: p.requiresApproval } : {}),
        ...(p.opensAt !== undefined ? { opensAt: p.opensAt ? new Date(p.opensAt) : null } : {}),
        ...(p.closesAt !== undefined ? { closesAt: p.closesAt ? new Date(p.closesAt) : null } : {}),
        ...(p.description !== undefined ? { description: p.description } : {}),
        ...(p.confirmationMessage !== undefined ? { confirmationMessage: p.confirmationMessage } : {}),
      })
      .where(eq(schema.registrationCategories.id, id))
      .returning();

    if (!updated) return NextResponse.json({ error: "Category not found." }, { status: 404 });

    await recordContentRevision({
      entityType: "registration_category",
      entityId: updated.id,
      snapshot: updated,
      changedBy: user.id,
      changeSummary: `Updated category ${updated.code} (active=${updated.isActive}, fee=₹${updated.feeAmountInr})`,
    });
    await recordAuditLog({
      actorUserId: user.id,
      action: "CMS_CATEGORY_UPDATED",
      resourceType: "registration_category",
      resourceId: updated.id,
      metadata: { code: updated.code, isActive: updated.isActive, feeAmountInr: updated.feeAmountInr },
    });

    return NextResponse.json({ item: updated });
  } catch (err) {
    console.error("PATCH /api/admin/cms error:", err);
    return NextResponse.json({ error: "Failed to update CMS record." }, { status: 500 });
  }
}

export async function DELETE(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }
    if (!hasPermission(user, PERMISSIONS.CMS_MANAGE)) {
      return NextResponse.json({ error: "Permission cms.manage is required." }, { status: 403 });
    }

    const { searchParams } = new URL(req.url);
    const entityType = searchParams.get("entityType");
    const id = searchParams.get("id");

    if (!entityType || !id) {
      return NextResponse.json({ error: "entityType and id are required." }, { status: 400 });
    }

    if (entityType === "programme") {
      const [deleted] = await db
        .delete(schema.programmeItems)
        .where(eq(schema.programmeItems.id, id))
        .returning();
      if (!deleted) return NextResponse.json({ error: "Not found." }, { status: 404 });
      await recordContentRevision({
        entityType: "programme_item",
        entityId: id,
        snapshot: { deleted },
        changedBy: user.id,
        changeSummary: `Deleted programme item "${deleted.title}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_PROGRAMME_DELETED",
        resourceType: "programme_item",
        resourceId: id,
      });
      return NextResponse.json({ deleted: true });
    }

    if (entityType === "partner") {
      const [deleted] = await db
        .delete(schema.partners)
        .where(eq(schema.partners.id, id))
        .returning();
      if (!deleted) return NextResponse.json({ error: "Not found." }, { status: 404 });
      await recordContentRevision({
        entityType: "partner",
        entityId: id,
        snapshot: { deleted },
        changedBy: user.id,
        changeSummary: `Deleted partner ${id}`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_PARTNER_DELETED",
        resourceType: "partner",
        resourceId: id,
      });
      return NextResponse.json({ deleted: true });
    }

    if (entityType === "faq") {
      const [deleted] = await db
        .delete(schema.faqItems)
        .where(eq(schema.faqItems.id, id))
        .returning();
      if (!deleted) return NextResponse.json({ error: "Not found." }, { status: 404 });
      await recordContentRevision({
        entityType: "faq_item",
        entityId: id,
        snapshot: { deleted },
        changedBy: user.id,
        changeSummary: `Deleted FAQ "${deleted.question.slice(0, 80)}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_FAQ_DELETED",
        resourceType: "faq_item",
        resourceId: id,
      });
      return NextResponse.json({ deleted: true });
    }

    if (entityType === "announcement") {
      const [deleted] = await db
        .delete(schema.announcements)
        .where(eq(schema.announcements.id, id))
        .returning();
      if (!deleted) return NextResponse.json({ error: "Not found." }, { status: 404 });
      await recordContentRevision({
        entityType: "announcement",
        entityId: id,
        snapshot: { deleted },
        changedBy: user.id,
        changeSummary: `Deleted announcement "${deleted.title}"`,
      });
      await recordAuditLog({
        actorUserId: user.id,
        action: "CMS_ANNOUNCEMENT_DELETED",
        resourceType: "announcement",
        resourceId: id,
      });
      return NextResponse.json({ deleted: true });
    }

    return NextResponse.json({ error: "Unsupported entityType for deletion." }, { status: 400 });
  } catch (err) {
    console.error("DELETE /api/admin/cms error:", err);
    return NextResponse.json({ error: "Failed to delete CMS record." }, { status: 500 });
  }
}
