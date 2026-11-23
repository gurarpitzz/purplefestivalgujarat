import React from "react";
import { and, asc, eq } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser } from "@/lib/auth";
import { FESTIVAL_EDITION_SLUG } from "@/lib/constants";
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

  const [edition] = await db
    .select()
    .from(schema.festivalEditions)
    .where(eq(schema.festivalEditions.slug, FESTIVAL_EDITION_SLUG))
    .limit(1);

  const [categories, disabilityCategories, accommodationTypes, user] =
    await Promise.all([
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
