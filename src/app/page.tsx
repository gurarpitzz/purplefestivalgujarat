import React from "react";
import Link from "next/link";
import { asc, eq, and } from "drizzle-orm";
import {
  ArrowRight,
  Calendar,
  MapPin,
  Music,
  Palette,
  GraduationCap,
  Cpu,
  Trophy,
  MessageSquare,
  Accessibility,
  ShieldCheck,
  Mail,
  Phone,
  Clock,
  CheckCircle2,
} from "lucide-react";
import { db, schema } from "@/db";
import { RPWD_21_DISABILITIES } from "@/lib/constants";
import { Preloader } from "@/components/Preloader";
import { SiteHeader, PurpleFestEmblemSvg } from "@/components/SiteHeader";
import { SiteFooter } from "@/components/SiteFooter";
import { DisabilitiesExplorer, type DisabilityItem } from "@/components/DisabilitiesExplorer";
import { HomeFaqAccordion, type FaqItemView } from "@/components/HomeFaqAccordion";
import { GujaratHeroBackground } from "@/components/hero/GujaratHeroBackground";

export const dynamic = "force-dynamic";

const FESTIVAL_HIGHLIGHTS = [
  {
    title: "Cultural Performances",
    category: "Stage & Music",
    description:
      "Inclusive folk heritage, contemporary dance, sign-language music ensembles, and audio-described stage showcases celebrating Gujarat's cultural spirit.",
    icon: Music,
  },
  {
    title: "Disability Arts & Cinema",
    category: "Creative Expression",
    description:
      "Curated inclusive film screenings with closed captions and audio description, fine-arts pavilions, and tactile exhibitions by artists with disabilities.",
    icon: Palette,
  },
  {
    title: "Inclusive Education",
    category: "NEP 2020 & UDL",
    description:
      "Dialogues with educators, researchers, and students on Universal Design for Learning, teacher preparedness, and accessible school & university ecosystems.",
    icon: GraduationCap,
  },
  {
    title: "Assistive Technology Expo",
    category: "Innovation Pavilion",
    description:
      "Hands-on demonstrations of screen readers, Braille displays, AAC communication tools, adaptive mobility solutions, and Indian assistive startups.",
    icon: Cpu,
  },
  {
    title: "Para Sports Championships",
    category: "25 Adaptive Disciplines",
    description:
      "Unified athletic events, wheelchair basketball, boccia, blind cricket, and inclusive youth sports hosted at Sama Indoor Stadium and university grounds.",
    icon: Trophy,
  },
  {
    title: "Workshops & Policy Panels",
    category: "Rights & Livelihoods",
    description:
      "Action-oriented sessions on the RPwD Act 2016, barrier-free urban infrastructure, employer inclusion conclaves, and digital accessibility compliance.",
    icon: MessageSquare,
  },
] as const;

const FALLBACK_GALLERY = [
  {
    id: "g-1",
    url: "/assets/gallery/venue-01.jpg",
    altText:
      "Wide view of the Vadodara sports stadium and athletic ground prepared for inclusive para-sports",
    caption: "Adaptive Athletics & Outdoor Arena",
  },
  {
    id: "g-2",
    url: "/assets/gallery/venue-05.jpg",
    altText:
      "Indoor pavilion and accessible court facility in Vadodara for Purple Festival Gujarat 2027",
    caption: "Indoor Court & Para-Sports Pavilion",
  },
  {
    id: "g-3",
    url: "/assets/gallery/venue-10.jpg",
    altText:
      "Outdoor sports field and pavilion lawn at The Maharaja Sayajirao University of Baroda",
    caption: "University Sports Field & Lawn",
  },
  {
    id: "g-4",
    url: "/assets/gallery/venue-15.jpg",
    altText: "Accessible campus walkway and entrance corridor at Vadodara festival venue",
    caption: "Campus Access Corridor",
  },
  {
    id: "g-5",
    url: "/assets/gallery/venue-20.jpg",
    altText:
      "Sports complex seating and shaded pavilion area for spectators and para-athletes",
    caption: "Shaded Spectator & Athlete Pavilion",
  },
  {
    id: "g-6",
    url: "/assets/gallery/venue-25.jpg",
    altText:
      "Campus assembly area and shaded courtyard for workshops and community exhibitions",
    caption: "Community Courtyard & Exhibition Grounds",
  },
];

async function getHomePageData() {
  try {
    const [homePage] = await db
      .select()
      .from(schema.cmsPages)
      .where(eq(schema.cmsPages.slug, "home"))
      .limit(1);

    const sections = homePage
      ? await db
          .select()
          .from(schema.cmsSections)
          .where(
            and(
              eq(schema.cmsSections.pageId, homePage.id),
              eq(schema.cmsSections.status, "PUBLISHED")
            )
          )
          .orderBy(asc(schema.cmsSections.displayOrder))
      : [];

    const publishedPartners = await db
      .select({
        partnerId: schema.partners.id,
        category: schema.partners.category,
        displayOrder: schema.partners.displayOrder,
        orgName: schema.organizations.name,
        shortName: schema.organizations.shortName,
        logoUrl: schema.organizations.logoUrl,
        logoAltText: schema.organizations.logoAltText,
        description: schema.organizations.description,
        websiteUrl: schema.organizations.websiteUrl,
      })
      .from(schema.partners)
      .innerJoin(
        schema.organizations,
        eq(schema.partners.organizationId, schema.organizations.id)
      )
      .where(eq(schema.partners.status, "PUBLISHED"))
      .orderBy(asc(schema.partners.displayOrder));

    const programme = await db
      .select()
      .from(schema.programmeItems)
      .where(eq(schema.programmeItems.status, "PUBLISHED"))
      .orderBy(asc(schema.programmeItems.dayNumber), asc(schema.programmeItems.displayOrder))
      .limit(6);

    const disabilities = await db
      .select()
      .from(schema.disabilityCategories)
      .orderBy(asc(schema.disabilityCategories.displayOrder));

    const faqs = await db
      .select()
      .from(schema.faqItems)
      .where(eq(schema.faqItems.status, "PUBLISHED"))
      .orderBy(asc(schema.faqItems.displayOrder));

    const gallery = await db
      .select()
      .from(schema.mediaAssets)
      .where(eq(schema.mediaAssets.category, "GALLERY"))
      .limit(6);

    return {
      sections,
      publishedPartners,
      programme,
      disabilities,
      faqs,
      gallery,
    };
  } catch {
    return {
      sections: [],
      publishedPartners: [],
      programme: [],
      disabilities: [],
      faqs: [],
      gallery: [],
    };
  }
}

export default async function HomePage() {
  const { sections, publishedPartners, programme, disabilities, faqs, gallery } =
    await getHomePageData();

  const sectionByKey = new Map(sections.map((s) => [s.sectionKey, s]));
  const heroSection = sectionByKey.get("hero");
  const aboutSection = sectionByKey.get("about");
  const viksitSection = sectionByKey.get("viksit-bharat");
  const contactSection = sectionByKey.get("contact");

  const aboutData = (aboutSection?.data as {
    stats?: Array<{ label: string; value: string }>;
  } | null) ?? null;

  const stats =
    aboutData?.stats && aboutData.stats.length > 0
      ? aboutData.stats
      : [
          { label: "Expected Attendees", value: "5,000+" },
          { label: "Benchmark Disabilities", value: "21" },
          { label: "Para Sports Disciplines", value: "25" },
          { label: "Days of Celebration", value: "3" },
        ];

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

  const faqList: FaqItemView[] =
    faqs.length > 0
      ? faqs.map((f) => ({
          id: f.id,
          category: f.category,
          question: f.question,
          answer: f.answer,
        }))
      : [
          {
            id: "faq-1",
            category: "Registration",
            question: "Do I need to disclose a disability to attend Purple Festival Gujarat 2027?",
            answer:
              "No. Disability disclosure is completely optional and never restricts which category you may apply for. Persons with disabilities can register as Attendees, Delegates, Volunteers, or SLI Professionals.",
          },
          {
            id: "faq-2",
            category: "Accessibility",
            question:
              "How do I request Indian Sign Language (ISL), Braille, or wheelchair accommodations?",
            answer:
              "You can select your accommodation requirements during registration or update them anytime in your Participant Dashboard. Your requests are visible only to authorized accessibility coordinators.",
          },
        ];

  const galleryItems =
    gallery.length > 0
      ? gallery.map((m) => ({
          id: m.id,
          url: m.url,
          altText: m.altText,
          caption: m.sourceAttribution || "Purple Festival Gujarat 2027 Venue",
        }))
      : FALLBACK_GALLERY;

  const viksitData = (viksitSection?.data as { disclaimer?: string } | null) ?? null;
  const contactData = (contactSection?.data as {
    email?: string;
    phones?: Array<{ name: string; number: string }>;
    city?: string;
  } | null) ?? null;

  const contactEmail = contactData?.email || "purplefestivalvadodara@gmail.com";
  const contactPhones =
    contactData?.phones && contactData.phones.length > 0
      ? contactData.phones
      : [
          { name: "Radha Tandon", number: "+91 99743 83920" },
          { name: "Satish Kapoor", number: "+91 92117 60202" },
        ];

  return (
    <>
      <Preloader />
      <SiteHeader />

      <main id="main-content" tabIndex={-1} className="focus:outline-none">
        {/* ============================================================
            1. AUTHENTIC LAVENDER-PERIWINKLE HERO (With Gujarat Architectural Line-Art Background)
            ============================================================ */}
        <section
          aria-labelledby="hero-heading"
          className="relative flex min-h-[calc(100svh-76px)] flex-col items-center justify-between overflow-hidden bg-[#F2E9FE] px-6 pb-8 pt-10 text-center text-[#202D59] sm:px-10 md:pt-14"
        >
          <GujaratHeroBackground />

          <div className="relative z-10 mx-auto flex w-full max-w-[1040px] flex-1 flex-col items-center justify-center">
            {/* Monumental Reference-Matched Headline (~104px desktop, DM Sans Bold) */}
            <h1
              id="hero-heading"
              className="text-[clamp(3.1rem,8vw,6.5rem)] font-bold leading-[1.03] tracking-[-0.03em] text-[#202D59]"
            >
              Inclusion, celebrated.
            </h1>

            {/* Reference-Matched Large Subtitle (~30px desktop, regular weight) */}
            <p className="mt-5 max-w-[920px] text-[clamp(1.2rem,2.2vw,1.95rem)] font-normal leading-[1.35] tracking-[-0.01em] text-[#202D59]">
              {heroSection?.body ||
                "India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities (RPwD Act 2016)."}
            </p>

            {/* Single Prominent Centered #A459FF Apply Now CTA (exact reference scale) */}
            <div className="mt-10 flex items-center justify-center">
              <Link
                href="/apply"
                className="btn-primary-pfg touch-target inline-flex items-center justify-center bg-[#A459FF] px-9 py-3.5 text-[20px] font-semibold tracking-[-0.01em] text-white transition-colors hover:bg-[#8D3BF2]"
              >
                Apply Now
              </Link>
            </div>

            {/* Clean Static Flat Authorized Purple Festival Emblem (NO unwanted 3D animation) */}
            <div className="mt-10 flex flex-col items-center">
              <PurpleFestEmblemSvg className="h-28 w-auto text-[#202D59]/85 sm:h-32" />
              <p className="mt-2.5 text-sm font-medium tracking-[0.08em] uppercase text-[#202D59]/75">
                Purple Festival Gujarat 2027 · Vadodara, Gujarat
              </p>
            </div>
          </div>

          {/* Bottom-Left Info Pill (matching reference corner badge) */}
          <div className="relative z-10 mt-6 flex w-full max-w-[1440px] justify-start">
            <div className="inline-flex items-center gap-2.5 bg-white px-5 py-3 text-[15px] font-medium text-[#202D59] shadow-xs">
              <span>Purple Festival Gujarat</span>
              <span aria-hidden="true" className="text-[#099F9F]">
                ▼
              </span>
              <span className="font-bold text-[#202D59]">21–23 Jan 2027</span>
            </div>
          </div>
        </section>

        {/* ============================================================
            2. ABOUT THE FESTIVAL & KEY BROCHURE STATS
            ============================================================ */}
        <section
          id="about"
          aria-labelledby="about-heading"
          className="border-b border-[#202D59]/10 bg-white py-20 sm:py-24"
        >
          <div className="mx-auto max-w-7xl px-5 sm:px-8">
            <div className="grid gap-12 lg:grid-cols-12 lg:items-start">
              <div className="space-y-6 lg:col-span-7">
                <p className="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
                  {aboutSection?.subtitle || "First Edition in Gujarat — Vadodara, January 2027"}
                </p>
                <h2
                  id="about-heading"
                  className="text-3xl font-extrabold leading-tight tracking-tight text-[#202D59] sm:text-5xl"
                >
                  {aboutSection?.title ||
                    "Inclusion Is Not Charity. It Is Culture, Innovation, and Right."}
                </h2>
                <p className="text-base leading-relaxed text-[#46537C] sm:text-lg">
                  {aboutSection?.body ||
                    "Purple Festival Gujarat 2027 brings together 5,000+ attendees, 500+ participants with and without disabilities, educators, para-athletes, artists, technologists, and policy leaders for three days of barrier-free celebration across Vadodara."}
                </p>
                <p className="text-base leading-relaxed text-[#46537C]">
                  Built around dignity, representation, and universal design, the festival elevates
                  persons with disabilities as leaders, athletes, creators, and decision-makers —
                  never as objects of sympathy.
                </p>
              </div>

              <div className="grid grid-cols-2 gap-4 lg:col-span-5">
                {stats.map((st) => (
                  <div
                    key={st.label}
                    className="rounded-xl border border-[#202D59]/12 bg-[#DDDDF9]/55 p-6"
                  >
                    <p className="text-3xl font-extrabold text-[#202D59] sm:text-4xl">
                      {st.value}
                    </p>
                    <p className="mt-2 text-sm font-semibold text-[#46537C]">{st.label}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* ============================================================
            3. VIKSIT BHARAT@2047
            ============================================================ */}
        <section
          aria-labelledby="viksit-heading"
          className="bg-[#202D59] py-20 text-white sm:py-24"
          data-surface="dark"
        >
          <div className="mx-auto max-w-7xl px-5 sm:px-8">
            <div className="grid gap-10 lg:grid-cols-12 lg:items-center">
              <div className="space-y-5 lg:col-span-8">
                <p className="text-xs font-bold tracking-[0.2em] uppercase text-[#DDDDF9]">
                  {viksitSection?.subtitle || "Aligned with India's Vision for 2047"}
                </p>
                <h2
                  id="viksit-heading"
                  className="text-3xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl"
                >
                  {viksitSection?.title || "A Viksit Bharat Must Be an Inclusive Bharat."}
                </h2>
                <p className="max-w-3xl text-base leading-relaxed text-[#DDDDF9] sm:text-lg">
                  {viksitSection?.body ||
                    "India's vision for Viksit Bharat@2047 is about progress that reaches every citizen. Purple Festival Gujarat 2027 advances accessible education, dignified livelihoods, assistive technology, and equal cultural participation as pillars of an inclusive nation."}
                </p>
                <p className="text-xs text-[#DDDDF9]/75">
                  {viksitData?.disclaimer ||
                    "Purple Festival Gujarat 2027 contributes to the wider national vision for inclusive development under Viksit Bharat@2047."}
                </p>
              </div>

              <div className="grid gap-3 sm:grid-cols-2 lg:col-span-4 lg:grid-cols-1">
                {[
                  "Inclusive Education & NEP 2020",
                  "Assistive & Digital Public Innovation",
                  "Dignified Livelihoods & Equal Opportunity",
                  "Barrier-Free Cultural & Civic Participation",
                ].map((pillar) => (
                  <div
                    key={pillar}
                    className="flex items-center gap-3 rounded-lg border border-white/15 bg-white/10 px-4 py-3.5 text-sm font-semibold text-white"
                  >
                    <CheckCircle2 className="h-5 w-5 shrink-0 text-[#A459FF]" aria-hidden="true" />
                    <span>{pillar}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* ============================================================
            4. FESTIVAL HIGHLIGHTS
            ============================================================ */}
        <section
          id="highlights"
          aria-labelledby="highlights-heading"
          className="border-b border-[#202D59]/10 bg-[#EFEFEF]/65 py-20 sm:py-24"
        >
          <div className="mx-auto max-w-7xl px-5 sm:px-8">
            <div className="max-w-2xl space-y-3">
              <p className="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
                The Festival Experience
              </p>
              <h2
                id="highlights-heading"
                className="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
              >
                Six Pillars of Celebration
              </h2>
            </div>

            <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {FESTIVAL_HIGHLIGHTS.map((item) => {
                const Icon = item.icon;
                return (
                  <article
                    key={item.title}
                    className="flex flex-col rounded-xl border border-[#202D59]/10 bg-white p-7 shadow-xs transition-shadow hover:shadow-md"
                  >
                    <div className="flex items-center justify-between">
                      <span className="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#DDDDF9] text-[#202D59]">
                        <Icon className="h-6 w-6" aria-hidden="true" />
                      </span>
                      <span className="text-xs font-bold tracking-wider uppercase text-[#099F9F]">
                        {item.category}
                      </span>
                    </div>
                    <h3 className="mt-5 text-xl font-bold text-[#202D59]">{item.title}</h3>
                    <p className="mt-2.5 text-sm leading-relaxed text-[#46537C]">
                      {item.description}
                    </p>
                  </article>
                );
              })}
            </div>
          </div>
        </section>

        {/* ============================================================
            5. UNDERSTANDING THE 21 DISABILITIES (RPwD Act, 2016)
            ============================================================ */}
        <section
          aria-labelledby="disabilities-heading"
          className="border-b border-[#202D59]/10 bg-white py-20 sm:py-24"
        >
          <div className="mx-auto max-w-7xl px-5 sm:px-8">
            <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
              <div className="max-w-3xl space-y-3">
                <p className="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
                  Rights of Persons with Disabilities Act, 2016
                </p>
                <h2
                  id="disabilities-heading"
                  className="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
                >
                  Understanding the 21 Specified Disabilities
                </h2>
                <p className="text-base text-[#46537C]">
                  Every individual&apos;s access needs are unique. These categories are an
                  educational guide — never a restrictive gate for festival participation.
                </p>
              </div>
              <Link
                href="/accessibility"
                className="touch-target inline-flex shrink-0 items-center gap-2 border-2 border-[#202D59] px-5 py-2.5 text-sm font-bold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white"
              >
                <Accessibility className="h-4 w-4" aria-hidden="true" />
                <span>Full Accessibility Guide</span>
              </Link>
            </div>

            <div className="mt-10">
              <DisabilitiesExplorer disabilities={disabilityList} />
            </div>
          </div>
        </section>

        {/* ============================================================
            6. PROGRAMME PREVIEW
            ============================================================ */}
        <section
          aria-labelledby="programme-preview-heading"
          className="border-b border-[#202D59]/10 bg-[#DDDDF9]/50 py-20 sm:py-24"
        >
          <div className="mx-auto max-w-7xl px-5 sm:px-8">
            <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
              <div className="space-y-3">
                <p className="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
                  21–23 January 2027 · Vadodara
                </p>
                <h2
                  id="programme-preview-heading"
                  className="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
                >
                  Programme Highlights
                </h2>
              </div>
              <Link
                href="/programme"
                className="btn-primary-pfg touch-target inline-flex items-center gap-2 bg-[#A459FF] px-6 py-3 text-sm font-bold text-white hover:bg-[#8D3BF2]"
              >
                <span>Explore Full 3-Day Schedule</span>
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Link>
            </div>

            <div className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
              {programme.map((item) => (
                <article
                  key={item.id}
                  className="flex flex-col justify-between rounded-xl border border-[#202D59]/12 bg-white p-6 shadow-xs"
                >
                  <div>
                    <div className="flex items-center justify-between gap-2 text-xs font-bold uppercase tracking-wider">
                      <span className="rounded bg-[#DDDDF9] px-2.5 py-1 text-[#202D59]">
                        Day {item.dayNumber}
                      </span>
                      <span className="text-[#099F9F]">{item.category}</span>
                    </div>
                    <h3 className="mt-4 text-lg font-bold text-[#202D59]">{item.title}</h3>
                    {item.description && (
                      <p className="mt-2 text-sm text-[#46537C]">{item.description}</p>
                    )}
                  </div>
                  <div className="mt-5 space-y-1.5 border-t border-[#202D59]/10 pt-4 text-xs font-medium text-[#46537C]">
                    <p className="flex items-center gap-1.5">
                      <Clock className="h-3.5 w-3.5 text-[#A459FF]" aria-hidden="true" />
                      <span>{item.timeSlot}</span>
                    </p>
                    {item.venueName && (
                      <p className="flex items-center gap-1.5">
                        <MapPin className="h-3.5 w-3.5 text-[#099F9F]" aria-hidden="true" />
                        <span>{item.venueName}</span>
                      </p>
                    )}
                  </div>
                </article>
              ))}
            </div>
          </div>
        </section>

        {/* ============================================================
            7. PARTNERS & ORGANISERS (Consent-Verified Published Only)
            ============================================================ */}
        <section
          id="partners"
          aria-labelledby="partners-heading"
          className="border-b border-[#202D59]/10 bg-white py-20 sm:py-24"
        >
          <div className="mx-auto max-w-7xl px-5 sm:px-8">
            <div className="max-w-2xl space-y-3">
              <p className="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
                Confirmed Collaborations
              </p>
              <h2
                id="partners-heading"
                className="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
              >
                Organisers &amp; Partners
              </h2>
              <p className="text-sm text-[#46537C]">
                Only organisations with verified publication consent are displayed publicly; other
                invited institutions remain in CMS draft until confirmed.
              </p>
            </div>

            <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {publishedPartners.map((partner) => (
                <article
                  key={partner.partnerId}
                  className="flex flex-col justify-between rounded-xl border border-[#202D59]/12 bg-[#EFEFEF]/45 p-6"
                >
                  <div>
                    <span className="inline-block rounded bg-[#DDDDF9] px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-[#202D59]">
                      {partner.category}
                    </span>
                    <div className="mt-4 flex h-20 items-center justify-start rounded-lg bg-white p-3">
                      {partner.logoUrl ? (
                        /* eslint-disable-next-line @next/next/no-img-element */
                        <img
                          src={partner.logoUrl}
                          alt={partner.logoAltText || `${partner.orgName} logo`}
                          className="max-h-14 w-auto object-contain"
                        />
                      ) : (
                        <span className="text-base font-extrabold text-[#202D59]">
                          {partner.shortName || partner.orgName}
                        </span>
                      )}
                    </div>
                    <h3 className="mt-4 text-lg font-bold text-[#202D59]">{partner.orgName}</h3>
                    {partner.description && (
                      <p className="mt-2 text-sm text-[#46537C]">{partner.description}</p>
                    )}
                  </div>
                </article>
              ))}
            </div>
          </div>
        </section>

        {/* ============================================================
            8. VENUE & FESTIVAL GALLERY
            ============================================================ */}
        <section
          id="gallery"
          aria-labelledby="gallery-heading"
          className="border-b border-[#202D59]/10 bg-[#EFEFEF]/65 py-20 sm:py-24"
        >
          <div className="mx-auto max-w-7xl px-5 sm:px-8">
            <div className="max-w-2xl space-y-3">
              <p className="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
                Vadodara Venues
              </p>
              <h2
                id="gallery-heading"
                className="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-5xl"
              >
                Festival Grounds &amp; Facilities
              </h2>
            </div>

            <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {galleryItems.map((img) => (
                <figure
                  key={img.id}
                  className="overflow-hidden rounded-xl border border-[#202D59]/12 bg-white shadow-xs"
                >
                  <div className="aspect-[16/10] w-full overflow-hidden bg-[#DDDDF9]">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={img.url}
                      alt={img.altText}
                      loading="lazy"
                      className="h-full w-full object-cover"
                    />
                  </div>
                  <figcaption className="px-4 py-3 text-xs font-semibold text-[#46537C]">
                    {img.caption}
                  </figcaption>
                </figure>
              ))}
            </div>
          </div>
        </section>

        {/* ============================================================
            9. FAQs & CONTACT
            ============================================================ */}
        <section
          id="contact"
          aria-labelledby="faq-contact-heading"
          className="bg-white py-20 sm:py-24"
        >
          <div className="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-12">
            <div className="space-y-6 lg:col-span-7">
              <p className="text-xs font-bold tracking-[0.2em] uppercase text-[#A459FF]">
                Questions &amp; Guidance
              </p>
              <h2
                id="faq-contact-heading"
                className="text-3xl font-extrabold tracking-tight text-[#202D59] sm:text-4xl"
              >
                Frequently Asked Questions
              </h2>
              <HomeFaqAccordion items={faqList} />
            </div>

            <div className="lg:col-span-5">
              <div className="rounded-2xl border border-[#202D59]/12 bg-[#DDDDF9] p-8 text-[#202D59]">
                <div className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#202D59]">
                  <ShieldCheck className="h-4 w-4 text-[#099F9F]" aria-hidden="true" />
                  <span>Festival Secretariat</span>
                </div>
                <h3 className="mt-4 text-2xl font-extrabold text-[#202D59]">
                  {contactSection?.title || "Connect with the Secretariat"}
                </h3>
                <p className="mt-2 text-sm text-[#202D59]/85">
                  {contactSection?.body ||
                    "Reach our team for accessibility accommodations, institutional delegations, volunteer coordination, or media inquiries."}
                </p>

                <div className="mt-6 space-y-4 text-sm">
                  <a
                    href={`mailto:${contactEmail}`}
                    className="flex items-center gap-3 rounded-lg bg-white px-4 py-3 font-semibold text-[#202D59] hover:bg-[#202D59] hover:text-white"
                  >
                    <Mail className="h-4 w-4 shrink-0 text-[#A459FF]" aria-hidden="true" />
                    <span className="break-all">{contactEmail}</span>
                  </a>
                  {contactPhones.map((p) => (
                    <a
                      key={p.number}
                      href={`tel:${p.number.replace(/\s+/g, "")}`}
                      className="flex items-center justify-between rounded-lg bg-white px-4 py-3 font-semibold text-[#202D59] hover:bg-[#202D59] hover:text-white"
                    >
                      <span className="flex items-center gap-2.5">
                        <Phone className="h-4 w-4 shrink-0 text-[#099F9F]" aria-hidden="true" />
                        <span>{p.name}</span>
                      </span>
                      <span>{p.number}</span>
                    </a>
                  ))}
                </div>

                <div className="mt-6 flex flex-wrap gap-3">
                  <Link
                    href="/apply"
                    className="btn-primary-pfg touch-target inline-flex flex-1 items-center justify-center bg-[#A459FF] px-5 py-3 text-sm font-bold text-white hover:bg-[#8D3BF2]"
                  >
                    Apply Now
                  </Link>
                  <Link
                    href="/login"
                    className="touch-target inline-flex flex-1 items-center justify-center border-2 border-[#202D59] bg-transparent px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#202D59] hover:text-white"
                  >
                    Login
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      <SiteFooter />
    </>
  );
}
