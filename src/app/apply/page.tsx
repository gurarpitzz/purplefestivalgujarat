import React from "react";
import { and, asc, eq } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser } from "@/lib/auth";
import {
  ACCOMMODATION_TYPES_SEED,
  FESTIVAL_EDITION_SLUG,
  REGISTRATION_CATEGORIES_SEED,
  RPWD_21_DISABILITIES,
} from "@/lib/constants";
import RegistrationWizard from "@/components/RegistrationWizard";

export const dynamic = "force-dynamic";

export const metadata = {
  title: "Register & Apply | Purple Festival Gujarat 2027",
  description:
    "Accessible registration for Attendees, Delegates, Volunteers, and Indian Sign Language Interpreters for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara).",
};

export default async function ApplyPage({
  searchParams,
}: {
  searchParams: Promise<{ category?: string; pathway?: string }>;
}) {
  const params = await searchParams;

  let categories: Array<{
    id: string;
    code: string;
    name: string;
    description: string | null;
    requiresPayment: boolean;
    feeInr: number;
    requiresApproval: boolean;
    requiresDocumentUpload: boolean;
  }> = [];
  let disabilityCategories: Array<{
    id: string;
    code: string;
    name: string;
    rpwdGroup: string;
  }> = [];
  let accommodationTypes: Array<{
    id: string;
    code: string;
    label: string;
    description: string | null;
  }> = [];
  let user: Awaited<ReturnType<typeof getCurrentUser>> = null;

  try {
    const [edition] = await db
      .select()
      .from(schema.festivalEditions)
      .where(eq(schema.festivalEditions.slug, FESTIVAL_EDITION_SLUG))
      .limit(1);

    const [catRows, disRows, accRows, currentUser] = await Promise.all([
      edition
        ? db
            .select()
            .from(schema.registrationCategories)
            .where(
              and(
                eq(schema.registrationCategories.editionId, edition.id),
                eq(schema.registrationCategories.isActive, true)
              )
            )
            .orderBy(asc(schema.registrationCategories.displayOrder))
        : Promise.resolve([]),
      db
        .select()
        .from(schema.disabilityCategories)
        .orderBy(asc(schema.disabilityCategories.displayOrder)),
      db
        .select()
        .from(schema.accommodationTypes)
        .where(eq(schema.accommodationTypes.isActive, true))
        .orderBy(asc(schema.accommodationTypes.displayOrder)),
      getCurrentUser(),
    ]);

    categories = catRows;
    disabilityCategories = disRows;
    accommodationTypes = accRows;
    user = currentUser;
  } catch (err) {
    console.error("ApplyPage database load error:", err);
  }

  if (categories.length === 0) {
    categories = REGISTRATION_CATEGORIES_SEED.map((c) => ({
      id: c.code,
      code: c.code,
      name: c.name,
      description: c.description,
      requiresPayment: c.requiresPayment,
      feeInr: c.feeInr,
      requiresApproval: c.requiresApproval,
      requiresDocumentUpload: c.requiresDocumentUpload,
    }));
  }

  if (disabilityCategories.length === 0) {
    disabilityCategories = RPWD_21_DISABILITIES.map((d) => ({
      id: d.code,
      code: d.code,
      name: d.name,
      rpwdGroup: d.rpwdGroup,
    }));
  }

  if (accommodationTypes.length === 0) {
    accommodationTypes = ACCOMMODATION_TYPES_SEED.map((a) => ({
      id: a.code,
      code: a.code,
      label: a.label,
      description: null,
    }));
  }

  return (
    <RegistrationWizard
      initialCategories={categories}
      disabilityCategories={disabilityCategories}
      accommodationTypes={accommodationTypes}
      currentUser={
        user
          ? {
              id: user.id,
              fullName: user.fullName,
              email: user.email,
              phone: user.phone,
              country: user.country,
            }
          : null
      }
      initialCategoryParam={params.category || params.pathway || null}
    />
  );
}
