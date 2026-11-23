import React from "react";
import Link from "next/link";
import type { Metadata } from "next";
import { asc, eq } from "drizzle-orm";
import {
  Accessibility,
  CheckCircle2,
  ArrowRight,
  MapPin,
  ShieldCheck,
  Headphones,
  HandHeart,
  LayoutDashboard,
} from "lucide-react";
import { db, schema } from "@/db";
import { RPWD_21_DISABILITIES, ACCOMMODATION_TYPES_SEED } from "@/lib/constants";
import { SiteHeader } from "@/components/SiteHeader";
import { SiteFooter } from "@/components/SiteFooter";
import { DisabilitiesExplorer, type DisabilityItem } from "@/components/DisabilitiesExplorer";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Accessibility Guide & 21 RPwD Disabilities",
  description:
    "Complete venue accessibility guide, available accommodation services, and all 21 specified disabilities under India's RPwD Act 2016 for Purple Festival Gujarat 2027.",
};

async function getAccessibilityData() {
  try {
    const [disabilities, accommodations, venues] = await Promise.all([
      db
        .select()
        .from(schema.disabilityCategories)
        .orderBy(asc(schema.disabilityCategories.displayOrder)),
      db
        .select()
        .from(schema.accommodationTypes)
        .where(eq(schema.accommodationTypes.isActive, true))
        .orderBy(asc(schema.accommodationTypes.displayOrder)),
      db.select().from(schema.venues),
    ]);
    return { disabilities, accommodations, venues };
  } catch (err) {
    console.error("Failed to fetch accessibility guide data:", err);
    return { disabilities: [], accommodations: [], venues: [] };
  }
}

export default async function AccessibilityGuidePage() {
  const { disabilities, accommodations, venues } = await getAccessibilityData();

  const disabilityList: DisabilityItem[] =
    disabilities.length > 0
      ? disabilities.map((d) => ({
          id: d.id,
          code: d.code,
          name: d.name,
          rpwdGroup: d.rpwdGroup,
          description: d.description,
          accommodationExamples: Array.isArray(d.accommodationExamples)
            ? d.accommodationExamples
            : [],
          displayOrder: d.displayOrder,
        }))
      : RPWD_21_DISABILITIES.map((d, idx) => ({
          id: d.code,
          code: d.code,
          name: d.name,
          rpwdGroup: d.rpwdGroup,
          description: d.description,
          accommodationExamples: [...d.accommodationExamples],
          displayOrder: idx + 1,
        }));

  const accommodationList =
    accommodations.length > 0
      ? accommodations.map((a) => ({ code: a.code, label: a.label, description: a.description }))
      : ACCOMMODATION_TYPES_SEED.map((a) => ({ code: a.code, label: a.label, description: null }));

  return (
    <>
      <SiteHeader />
      <main id="main-content" tabIndex={-1} className="min-h-screen bg-[#F6F1F8] focus:outline-none">
        {/* Hero */}
        <section
          aria-labelledby="accessibility-page-title"
          className="editorial-hero-bg border-b border-[#383242] py-16 text-[#F9F7FC] sm:py-20"
          data-surface="dark"
        >
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <p className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.22em] uppercase text-[#D5B77B]">
              <Accessibility className="h-4 w-4" aria-hidden="true" />
              Barrier-Free Festival Architecture
            </p>
            <h1
              id="accessibility-page-title"
              className="font-display mt-4 text-4xl font-bold text-[#F9F7FC] sm:text-5xl"
            >
              Accessibility &amp; Reasonable Accommodations
            </h1>
            <p className="mt-4 max-w-3xl text-base leading-relaxed text-[#E8DFF0] sm:text-lg">
              Purple Festival Gujarat 2027 is engineered for dignified, independent participation
              across physical, sensory, intellectual, neurodevelopmental, psychosocial, and
              communication access needs.
            </p>

            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                href="/apply"
                className="btn-primary-pfg touch-target inline-flex items-center gap-2 rounded-full bg-[#D5B77B] px-6 py-3 text-sm font-bold text-[#17151C] hover:bg-[#E6CA93]"
              >
                Register &amp; Request Accommodations
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Link>
              <Link
                href="/dashboard"
                className="touch-target inline-flex items-center gap-2 rounded-full border border-[#B6A1C8]/50 bg-[#25212B] px-6 py-3 text-sm font-semibold text-[#F9F7FC] hover:border-[#D5B77B] hover:text-[#D5B77B]"
              >
                <LayoutDashboard className="h-4 w-4 text-[#D5B77B]" aria-hidden="true" />
                Update Request in Dashboard
              </Link>
            </div>
          </div>
        </section>

        {/* Available Festival Accommodation Services */}
        <section aria-labelledby="services-heading" className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="max-w-3xl space-y-2">
            <p className="text-xs font-bold tracking-[0.22em] uppercase text-[#59436D]">
              On-Site &amp; Digital Support
            </p>
            <h2 id="services-heading" className="font-display text-3xl font-bold text-[#211B29]">
              Requestable Accommodation Services
            </h2>
            <p className="text-sm leading-relaxed text-[#4B4257]">
              Select any of the following accommodations during registration or update your
              preferences anytime in your Participant Dashboard. Your disclosure and support
              requests are strictly confidential and accessible only to authorized accessibility
              coordinators.
            </p>
          </div>

          <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {accommodationList.map((item) => (
              <div
                key={item.code}
                className="surface-card flex items-start gap-3 rounded-2xl border border-[#59436D]/20 bg-white p-5 shadow-xs"
              >
                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-[#19734B]" aria-hidden="true" />
                <div>
                  <h3 className="text-sm font-bold text-[#211B29]">{item.label}</h3>
                  {item.description && (
                    <p className="mt-1 text-xs text-[#4B4257]">{item.description}</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Venue Accessibility Status */}
        {venues.length > 0 && (
          <section
            aria-labelledby="venues-heading"
            className="border-y border-[#59436D]/15 bg-white py-16"
          >
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
              <div className="max-w-3xl space-y-2">
                <p className="text-xs font-bold tracking-[0.22em] uppercase text-[#59436D]">
                  Vadodara Venues
                </p>
                <h2 id="venues-heading" className="font-display text-3xl font-bold text-[#211B29]">
                  Venue Accessibility &amp; Confirmation Status
                </h2>
              </div>

              <div className="mt-8 grid gap-6 md:grid-cols-3">
                {venues.map((v) => {
                  const features = Array.isArray(v.accessibilityFeatures)
                    ? v.accessibilityFeatures
                    : [];
                  return (
                    <article
                      key={v.id}
                      className="surface-card flex flex-col justify-between rounded-2xl border border-[#59436D]/20 bg-[#F6F1F8]/60 p-6"
                    >
                      <div>
                        <span className="inline-block rounded-full bg-[#E8DFF0] px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#432F56]">
                          Status: {v.confirmationStatus}
                        </span>
                        <h3 className="font-display mt-3 text-lg font-bold text-[#211B29]">
                          {v.name}
                        </h3>
                        {v.address && (
                          <p className="mt-1 flex items-center gap-1.5 text-xs text-[#4B4257]">
                            <MapPin className="h-3.5 w-3.5 shrink-0 text-[#59436D]" aria-hidden="true" />
                            {v.address}
                          </p>
                        )}
                        {features.length > 0 && (
                          <ul className="mt-4 space-y-1.5 text-xs text-[#211B29]">
                            {features.map((f, idx) => (
                              <li key={idx} className="flex items-start gap-2">
                                <ShieldCheck
                                  className="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#19734B]"
                                  aria-hidden="true"
                                />
                                <span>{f}</span>
                              </li>
                            ))}
                          </ul>
                        )}
                      </div>
                    </article>
                  );
                })}
              </div>
            </div>
          </section>
        )}

        {/* All 21 Specified Disabilities under RPwD Act 2016 */}
        <section
          aria-labelledby="rpwd-heading"
          className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8"
        >
          <div className="mb-8 max-w-3xl space-y-2">
            <p className="text-xs font-bold tracking-[0.22em] uppercase text-[#59436D]">
              Rights of Persons with Disabilities (RPwD) Act, 2016
            </p>
            <h2 id="rpwd-heading" className="font-display text-3xl font-bold text-[#211B29]">
              All 21 Benchmark Disabilities &amp; Tailored Supports
            </h2>
            <p className="text-sm text-[#4B4257]">
              Disability disclosure is always voluntary at Purple Festival Gujarat 2027. Persons
              with disabilities are welcome in every role — attendee, delegate, speaker, artist,
              athlete, volunteer, or organizer.
            </p>
          </div>

          <DisabilitiesExplorer disabilities={disabilityList} initialExpandedCount={3} />
        </section>

        {/* Bottom CTA */}
        <section className="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
          <div
            className="surface-card rounded-3xl border border-[#383242] bg-[#17151C] p-8 text-[#F9F7FC] sm:p-10"
            data-surface="dark"
          >
            <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
              <div className="space-y-2">
                <p className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] uppercase text-[#D5B77B]">
                  <HandHeart className="h-4 w-4" aria-hidden="true" />
                  Dedicated Accessibility Desk
                </p>
                <h2 className="font-display text-2xl font-bold text-[#F9F7FC] sm:text-3xl">
                  Need Custom Support Before You Arrive?
                </h2>
                <p className="max-w-2xl text-sm text-[#D7CCE2]">
                  Submit your accommodation needs during registration, manage them in your
                  Participant Dashboard, or write directly to{" "}
                  <a
                    href="mailto:purplefestivalvadodara@gmail.com"
                    className="font-semibold text-[#D5B77B] underline underline-offset-4"
                  >
                    purplefestivalvadodara@gmail.com
                  </a>
                  .
                </p>
              </div>
              <div className="flex flex-wrap gap-3">
                <Link
                  href="/apply"
                  className="btn-primary-pfg touch-target inline-flex items-center gap-2 rounded-full bg-[#D5B77B] px-6 py-3 text-sm font-bold text-[#17151C] hover:bg-[#E6CA93]"
                >
                  Apply Now
                  <ArrowRight className="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                  href="/legal/accessibility-statement"
                  className="touch-target inline-flex items-center gap-2 rounded-full border border-[#4D445C] bg-[#25212B] px-5 py-3 text-sm font-semibold text-[#F9F7FC] hover:border-[#D5B77B]"
                >
                  <Headphones className="h-4 w-4 text-[#D5B77B]" aria-hidden="true" />
                  WCAG 2.2 Statement
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>
      <SiteFooter />
    </>
  );
}
