import React from "react";
import Link from "next/link";
import type { Metadata } from "next";
import { ShieldCheck, ArrowLeft } from "lucide-react";
import { SiteHeader } from "@/components/SiteHeader";
import { SiteFooter } from "@/components/SiteFooter";

export const metadata: Metadata = {
  title: "Privacy Policy (DPDP Act Compliant)",
  description:
    "Digital Personal Data Protection (DPDP) Act 2023 compliant privacy notice for Purple Festival Gujarat 2027.",
};

export default function PrivacyPolicyPage() {
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
                <ShieldCheck className="h-4 w-4" aria-hidden="true" />
                Digital Personal Data Protection (DPDP) Act, 2023
              </p>
              <h1 className="font-display mt-3 text-3xl font-bold text-[#211B29] sm:text-4xl">
                Privacy Policy &amp; Data Protection Notice
              </h1>
              <p className="mt-2 text-sm text-[#4B4257]">
                Effective for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara, Gujarat)
              </p>
            </div>

            <div className="space-y-8 text-sm leading-relaxed text-[#211B29]">
              <section className="space-y-2">
                <h2 className="font-display text-xl font-bold text-[#211B29]">
                  1. Purpose &amp; Lawful Basis of Processing
                </h2>
                <p className="text-[#4B4257]">
                  Purple Festival Gujarat 2027 collects personal data strictly with your explicit,
                  informed consent in accordance with India&apos;s Digital Personal Data Protection
                  (DPDP) Act, 2023. We process your information solely to manage festival
                  registrations, review Volunteer and Indian Sign Language Interpreter (SLI)
                  applications, arrange requested accessibility accommodations, issue tamper-resistant
                  QR entry credentials, and communicate official festival updates.
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="font-display text-xl font-bold text-[#211B29]">
                  2. Voluntary Disability Disclosure &amp; Sensitive Access Needs
                </h2>
                <p className="text-[#4B4257]">
                  Disclosing a disability is <strong>100% optional</strong> and is never required to
                  participate in any category. When you voluntarily share disability information or
                  request accommodations (such as ISL interpretation, Braille schedules, wheelchair
                  seating, or sensory sanctuary access), that data is stored separately, protected by
                  strict role-based access control (RBAC), and visible{" "}
                  <strong>only to authorized Accessibility Coordinators and Administrators</strong>.
                  Reception desk (RGST) check-in operators never see your private disability
                  disclosure during QR scanning.
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="font-display text-xl font-bold text-[#211B29]">
                  3. QR Credentials &amp; Security Controls
                </h2>
                <p className="text-[#4B4257]">
                  Digital QR passes encode opaque, cryptographically signed tokens rather than raw
                  personal or medical details. All authentication sessions use secure HTTP-only
                  cookies, password hashes use strong salted cryptography, and administrative access
                  to participant records is logged in immutable audit trails.
                </p>
              </section>

              <section className="space-y-2">
                <h2 className="font-display text-xl font-bold text-[#211B29]">
                  4. Your Rights Under the DPDP Act
                </h2>
                <ul className="list-disc space-y-1.5 pl-5 text-[#4B4257]">
                  <li>
                    <strong>Right to Access:</strong> View your registration details, consent
                    records, and accommodation requests anytime via your Participant Dashboard.
                  </li>
                  <li>
                    <strong>Right to Correction &amp; Updating:</strong> Update your profile or
                    accessibility needs before the festival.
                  </li>
                  <li>
                    <strong>Right to Erasure &amp; Consent Withdrawal:</strong> Request deletion of
                    your personal data or withdraw consent at any time, subject to legal retention
                    obligations.
                  </li>
                  <li>
                    <strong>Right to Grievance Redressal:</strong> Contact our Secretariat for prompt
                    resolution of any privacy concern.
                  </li>
                </ul>
              </section>

              <section className="space-y-2">
                <h2 className="font-display text-xl font-bold text-[#211B29]">
                  5. Data Retention &amp; Contact
                </h2>
                <p className="text-[#4B4257]">
                  Personal data is retained only for the duration necessary to conduct Purple
                  Festival Gujarat 2027, fulfill statutory reporting, and honor post-event
                  certification. We never sell or rent participant data to third parties. To exercise
                  your privacy rights, email{" "}
                  <a
                    href="mailto:purplefestivalvadodara@gmail.com"
                    className="font-semibold text-[#59436D] underline"
                  >
                    purplefestivalvadodara@gmail.com
                  </a>
                  .
                </p>
              </section>
            </div>
          </article>
        </div>
      </main>
      <SiteFooter />
    </>
  );
}
