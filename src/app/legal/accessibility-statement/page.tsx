import React from "react";
import Link from "next/link";
import type { Metadata } from "next";
import { Accessibility, ArrowLeft, CheckCircle2 } from "lucide-react";
import { SiteHeader } from "@/components/SiteHeader";
import { SiteFooter } from "@/components/SiteFooter";

export const metadata: Metadata = {
  title: "Accessibility Statement (WCAG 2.2 AA)",
  description:
    "WCAG 2.2 Level AA digital accessibility commitment and compliance statement for Purple Festival Gujarat 2027.",
};

export default function AccessibilityStatementPage() {
  return (
    <>
      <SiteHeader />
      <main id="main-content" tabIndex={-1} className="min-h-screen bg-[#F6F1F8] focus:outline-none">
        <div className="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
          <Link
            href="/"
            className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-[#59436D] hover:underline"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            Back to Home
          </Link>

          <article className="surface-card rounded-3xl border border-[#59436D]/20 bg-white p-8 shadow-xs sm:p-12">
            <div className="mb-8 border-b border-[#59436D]/15 pb-6">
              <p className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] uppercase text-[#59436D]">
                <Accessibility className="h-4 w-4" aria-hidden="true" />
                WCAG 2.2 Level AA Commitment
              </p>
              <h1 className="font-display mt-3 text-3xl font-bold text-[#211B29] sm:text-4xl">
                Digital Accessibility Statement
              </h1>
              <p className="mt-2 text-sm text-[#4B4257]">
                Purple Festival Gujarat 2027 Official Digital Platform
              </p>
            </div>

            <div className="space-y-8 text-sm leading-relaxed text-[#211B29]">
              <section className="space-y-2">
                <h2 className="font-display text-xl font-bold text-[#211B29]">
                  1. Conformance Target
                </h2>
                <p className="text-[#4B4257]">
                  Purple Festival Gujarat 2027 is committed to ensuring digital accessibility for
                  people of all abilities. This website and registration ecosystem are designed and
                  tested to conform to the{" "}
                  <strong>Web Content Accessibility Guidelines (WCAG) 2.2 Level AA</strong> and align
                  with the Rights of Persons with Disabilities (RPwD) Act, 2016 and Guidelines for
                  Indian Government Websites (GIGW).
                </p>
              </section>

              <section className="space-y-3">
                <h2 className="font-display text-xl font-bold text-[#211B29]">
                  2. Built-In Accessibility Features
                </h2>
                <ul className="space-y-2 text-[#4B4257]">
                  {[
                    "Persistent Accessibility Preferences Panel: Text scaling from 90% to 200%, Default / High Contrast / Yellow-on-Black modes, increased line & letter spacing, link highlighting, reading guide band, and reduced-motion toggle.",
                    "Keyboard-First Navigation: Full Tab/Shift+Tab/Escape support, visible high-contrast focus rings, skip-to-main-content link, and minimum 44×44px interactive touch targets.",
                    "Motion Safety: Automatic respect for OS prefers-reduced-motion settings plus an immediate keyboard-focusable Skip Intro button on the introductory preloader.",
                    "Screen Reader Semantics: Logical heading hierarchy, ARIA landmarks, live status announcements, descriptive alternative text for photography and logos, and properly associated form labels and error summaries.",
                    "Accessible Documents: Downloadable calendar (.ics) files and clean, structured digital QR pass PDFs.",
                  ].map((item) => (
                    <li key={item} className="flex items-start gap-2.5">
                      <CheckCircle2
                        className="mt-0.5 h-4 w-4 shrink-0 text-[#19734B]"
                        aria-hidden="true"
                      />
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>
              </section>

              <section className="space-y-2">
                <h2 className="font-display text-xl font-bold text-[#211B29]">
                  3. Feedback &amp; Reporting Barriers
                </h2>
                <p className="text-[#4B4257]">
                  We continuously audit and improve our platform. If you encounter any accessibility
                  barrier on this site or require information in an alternative format (such as
                  Braille, large print, or accessible PDF), please contact us immediately:
                </p>
                <div className="mt-3 rounded-2xl bg-[#F6F1F8] p-4 text-[#211B29]">
                  <p>
                    <strong>Email:</strong>{" "}
                    <a
                      href="mailto:purplefestivalvadodara@gmail.com"
                      className="font-semibold text-[#59436D] underline"
                    >
                      purplefestivalvadodara@gmail.com
                    </a>
                  </p>
                  <p className="mt-1">
                    <strong>Phone:</strong> Radha Tandon (+91 99743 83920) · Satish Kapoor (+91
                    92117 60202)
                  </p>
                </div>
              </section>
            </div>
          </article>
        </div>
      </main>
      <SiteFooter />
    </>
  );
}
