import React from "react";
import { and, asc, eq } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser } from "@/lib/auth";
import {
  ACCOMMODATION_TYPES_SEED,
  FESTIVAL_EDITION_SLUG,
  RPWD_21_DISABILITIES,
} from "@/lib/constants";
import RegistrationWizard, {
  type AccommodationOption,
  type CategoryOption,
  type DisabilityOption,
} from "@/components/RegistrationWizard";

export const dynamic = "force-dynamic";

export const metadata = {
  title: "Register & Apply | Purple Festival Gujarat 2027",
  description:
    "Accessible registration for Attendees, Delegates, Volunteers, and Indian Sign Language Interpreters for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara).",
};

const FALLBACK_CATEGORIES: CategoryOption[] = [
  {
    id: "ATTENDEE",
    code: "ATTENDEE",
    name: "General Attendee / Visitor",
    description: "Free entry to cultural stages, exhibitions, para-sports & festival zones.",
    requiresApproval: false,
    requiresPayment: false,
    feeAmountInr: 0,
  },
  {
    id: "DELEGATE",
    code: "DELEGATE",
    name: "Conference Delegate",
    description: "Full access to policy panels, NEP 2020 & UDL symposiums, and delegate kit.",
    requiresApproval: false,
    requiresPayment: false,
    feeAmountInr: 0,
  },
  {
    id: "VOLUNTEER",
    code: "VOLUNTEER",
    name: "Festival Volunteer",
    description: "Join the accessibility & hospitality volunteer corps (requires approval).",
    requiresApproval: true,
    requiresPayment: false,
    feeAmountInr: 0,
  },
  {
    id: "SLI",
    code: "SLI",
    name: "Sign Language Interpreter (SLI)",
    description: "Certified Indian Sign Language interpreter supporting festival stages & panels.",
    requiresApproval: true,
    requiresPayment: false,
    feeAmountInr: 0,
  },
];

export default async function ApplyPage({
  searchParams,
}: {
  searchParams: Promise<{ category?: string; pathway?: string }>;
}) {
  const params = await searchParams;

  let categories: CategoryOption[] = [];
  let disabilityCategories: DisabilityOption[] = [];
  let accommodationTypes: AccommodationOption[] = [];
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
    categories = FALLBACK_CATEGORIES;
  }

  if (disabilityCategories.length === 0) {
    disabilityCategories = RPWD_21_DISABILITIES.map((d) => ({
      id: d.code,
      code: d.code,
      name: d.name,
      rpwdGroup: d.rpwdGroup,
      description: d.description,
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
