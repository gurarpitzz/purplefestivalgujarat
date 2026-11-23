import React from "react";
import Link from "next/link";
import type { Metadata } from "next";
import { asc, eq } from "drizzle-orm";
import { Calendar, MapPin, ArrowRight, Accessibility } from "lucide-react";
import { db, schema } from "@/db";
import { SiteHeader } from "@/components/SiteHeader";
import { SiteFooter } from "@/components/SiteFooter";
import { ProgrammeExplorer, type ProgrammeItemView } from "@/components/ProgrammeExplorer";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "3-Day Programme Schedule",
  description:
    "Explore the 3-day schedule for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara) with ISL interpretation, live captioning, para-sports, and calendar downloads.",
};

async function getPublishedProgramme(): Promise<ProgrammeItemView[]> {
  try {
    const rows = await db
      .select()
      .from(schema.programmeItems)
      .where(eq(schema.programmeItems.status, "PUBLISHED"))
      .orderBy(asc(schema.programmeItems.dayNumber), asc(schema.programmeItems.displayOrder));

    return rows.map((r) => ({
      id: r.id,
      dayNumber: r.dayNumber,
      timeSlot: r.timeSlot,
      title: r.title,
      description: r.description,
      category: r.category,
      venueName: r.venueName,
      speakers: Array.isArray(r.speakers) ? r.speakers : [],
      accessibilityServices: Array.isArray(r.accessibilityServices)
        ? r.accessibilityServices
        : [],
      displayOrder: r.displayOrder,
    }));
  } catch (err) {
    console.error("Failed to fetch programme items:", err);
    return [];
  }
}

export default async function ProgrammePage() {
  const items = await getPublishedProgramme();

  return (
    <>
      <SiteHeader />
      <main id="main-content" tabIndex={-1} className="min-h-screen bg-[#F6F1F8] focus:outline-none">
        {/* Hero Banner */}
        <section
          aria-labelledby="programme-page-title"
          className="editorial-hero-bg border-b border-[#383242] py-16 text-[#F9F7FC] sm:py-20"
          data-surface="dark"
        >
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="flex flex-wrap items-center gap-3 text-xs font-bold tracking-[0.2em] uppercase text-[#D5B77B]">
              <span className="inline-flex items-center gap-1.5">
                <Calendar className="h-4 w-4" aria-hidden="true" />
                21–23 January 2027
              </span>
              <span aria-hidden="true">•</span>
              <span className="inline-flex items-center gap-1.5 text-[#E8DFF0]">
                <MapPin className="h-4 w-4 text-[#D5B77B]" aria-hidden="true" />
                Vadodara, Gujarat
              </span>
            </div>

            <h1
              id="programme-page-title"
              className="font-display mt-4 text-4xl font-bold text-[#F9F7FC] sm:text-5xl"
            >
              3-Day Festival Programme
            </h1>
            <p className="mt-4 max-w-2xl text-base leading-relaxed text-[#E8DFF0] sm:text-lg">
              Filter sessions by day, category, or accessibility support, and download individual
              sessions or the complete schedule directly to your calendar (.ics).
            </p>

            <div className="mt-6 flex flex-wrap gap-3">
              <Link
                href="/apply"
                className="btn-primary-pfg touch-target inline-flex items-center gap-2 rounded-full bg-[#D5B77B] px-6 py-3 text-sm font-bold text-[#17151C] hover:bg-[#E6CA93]"
              >
                Register to Attend
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Link>
              <Link
                href="/accessibility"
                className="touch-target inline-flex items-center gap-2 rounded-full border border-[#B6A1C8]/50 bg-[#25212B] px-5 py-3 text-sm font-semibold text-[#F9F7FC] hover:border-[#D5B77B] hover:text-[#D5B77B]"
              >
                <Accessibility className="h-4 w-4 text-[#D5B77B]" aria-hidden="true" />
                Venue Accessibility Services
              </Link>
            </div>
          </div>
        </section>

        {/* Interactive Schedule Explorer */}
        <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
          <ProgrammeExplorer items={items} />
        </section>
      </main>
      <SiteFooter />
    </>
  );
}
