import { eq } from "drizzle-orm";
import { db, schema } from "./index";
import {
  FESTIVAL_EDITION_SLUG,
  FESTIVAL_NAME,
  FESTIVAL_TAGLINE,
  PERMISSIONS,
  DEFAULT_ROLE_PERMISSIONS,
  RPWD_21_DISABILITIES,
  ACCOMMODATION_TYPES_SEED,
} from "../lib/constants";

export async function seedDatabase() {
  // 1. Roles & Permissions
  const roleDefs: Array<{ code: "SUPERADMIN" | "ADMIN" | "RGST" | "PARTICIPANT"; name: string; description: string }> = [
    { code: "SUPERADMIN", name: "Superadmin", description: "Full system, security, CMS, user, and analytics authority." },
    { code: "ADMIN", name: "Festival Admin", description: "Day-to-day registration, volunteer/SLI review, attendance, and reporting." },
    { code: "RGST", name: "Registration Desk Staff", description: "Restricted reception-desk QR scanning and entry confirmation." },
    { code: "PARTICIPANT", name: "Participant", description: "Attendee, delegate, volunteer, or SLI professional account." },
  ];

  const roleMap = new Map<string, string>();
  for (const r of roleDefs) {
    const [existing] = await db.select().from(schema.roles).where(eq(schema.roles.code, r.code)).limit(1);
    if (existing) {
      roleMap.set(r.code, existing.id);
    } else {
      const [inserted] = await db.insert(schema.roles).values(r).returning();
      roleMap.set(r.code, inserted.id);
    }
  }

  const permMap = new Map<string, string>();
  for (const code of Object.values(PERMISSIONS)) {
    const [existing] = await db.select().from(schema.permissions).where(eq(schema.permissions.code, code)).limit(1);
    if (existing) {
      permMap.set(code, existing.id);
    } else {
      const [inserted] = await db.insert(schema.permissions).values({ code, description: code }).returning();
      permMap.set(code, inserted.id);
    }
  }

  for (const [roleCode, permCodes] of Object.entries(DEFAULT_ROLE_PERMISSIONS)) {
    const roleId = roleMap.get(roleCode);
    if (!roleId) continue;
    for (const pCode of permCodes) {
      const permId = permMap.get(pCode);
      if (!permId) continue;
      await db.insert(schema.rolePermissions).values({ roleId, permissionId: permId }).onConflictDoNothing();
    }
  }

  // 2. Festival Edition
  let [edition] = await db
    .select()
    .from(schema.festivalEditions)
    .where(eq(schema.festivalEditions.slug, FESTIVAL_EDITION_SLUG))
    .limit(1);

  if (!edition) {
    [edition] = await db
      .insert(schema.festivalEditions)
      .values({
        slug: FESTIVAL_EDITION_SLUG,
        name: FESTIVAL_NAME,
        tagline: FESTIVAL_TAGLINE,
        city: "Vadodara",
        state: "Gujarat",
        startDate: new Date("2027-01-21T03:30:00.000Z"), // 09:00 IST
        endDate: new Date("2027-01-23T15:00:00.000Z"), // 20:30 IST
        registrationOpensAt: new Date("2026-11-01T00:00:00.000Z"),
        registrationClosesAt: new Date("2027-01-20T18:29:59.000Z"),
        attendancePolicy: "ONCE_PER_DAY",
        isActive: true,
      })
      .returning();
  }

  // 3. Venues (strictly grounded in Brochure & Action Plan)
  const existingVenues = await db.select().from(schema.venues).where(eq(schema.venues.editionId, edition.id));
  let samaVenueId: string | undefined = existingVenues.find((v) => v.name.includes("Sama"))?.id;
  if (existingVenues.length === 0) {
    const insertedVenues = await db
      .insert(schema.venues)
      .values([
        {
          editionId: edition.id,
          name: "Sama Indoor Stadium",
          address: "Sama, Vadodara, Gujarat",
          confirmationStatus: "CONFIRMED",
          accessibilityFeatures: ["Step-free entry", "Wheelchair-accessible courts", "Accessible restrooms", "Para-sports ready"],
          capacity: 2500,
        },
        {
          editionId: edition.id,
          name: "The Maharaja Sayajirao University of Baroda (FSW & Dept. of Physical Education)",
          address: "Pratapgunj, Vadodara, Gujarat",
          confirmationStatus: "COMMITTED",
          accessibilityFeatures: ["Campus access audit in progress", "Accessible seminar halls", "Sports pavilion"],
          capacity: 3000,
        },
        {
          editionId: edition.id,
          name: "GMERS Medical College, Gotri",
          address: "Gotri, Vadodara, Gujarat",
          confirmationStatus: "PENDING",
          accessibilityFeatures: ["Pending final venue confirmation"],
          capacity: 1000,
        },
      ])
      .returning();
    samaVenueId = insertedVenues[0]?.id;
  }

  // 4. Check-in Stations
  const existingStations = await db.select().from(schema.checkinStations).where(eq(schema.checkinStations.editionId, edition.id));
  if (existingStations.length === 0) {
    await db.insert(schema.checkinStations).values([
      { editionId: edition.id, venueId: samaVenueId, name: "Sama Indoor Stadium — Main Gate A", gateCode: "SAMA-A" },
      { editionId: edition.id, venueId: samaVenueId, name: "Sama Indoor Stadium — Accessible Priority Desk", gateCode: "SAMA-ACC" },
      { editionId: edition.id, name: "MSU Baroda — Central Registration Desk", gateCode: "MSU-MAIN" },
    ]);
  }

  // 5. All 21 RPwD Act 2016 Disability Categories
  for (let i = 0; i < RPWD_21_DISABILITIES.length; i++) {
    const d = RPWD_21_DISABILITIES[i];
    await db
      .insert(schema.disabilityCategories)
      .values({
        code: d.code,
        name: d.name,
        rpwdGroup: d.rpwdGroup,
        description: d.description,
        accommodationExamples: [...d.accommodationExamples],
        displayOrder: i + 1,
      })
      .onConflictDoNothing();
  }

  // 6. Accommodation Types
  for (let i = 0; i < ACCOMMODATION_TYPES_SEED.length; i++) {
    const a = ACCOMMODATION_TYPES_SEED[i];
    await db
      .insert(schema.accommodationTypes)
      .values({
        code: a.code,
        label: a.label,
        displayOrder: i + 1,
        isActive: true,
      })
      .onConflictDoNothing();
  }

  // 7. Registration Categories (Fees configurable via CMS; Delegate fee 0 until organizer confirms ₹1,500/₹2,200)
  const categories = [
    {
      code: "ATTENDEE",
      name: "General Attendee",
      description: "Open festival access to cultural performances, exhibitions, para-sports, and inclusive sessions.",
      requiresApproval: false,
      requiresPayment: false,
      feeAmountInr: 0,
      displayOrder: 1,
    },
    {
      code: "DELEGATE",
      name: "Delegate (3-Day Conference & All-Access)",
      description: "Full access to conferences, workshops, panels, and networking. Delegate fee policy is managed by organizers in CMS.",
      requiresApproval: false,
      requiresPayment: false, // Enabled in Admin Settings once organizer confirms final fee & Razorpay credentials
      feeAmountInr: 0,
      displayOrder: 2,
    },
    {
      code: "VOLUNTEER",
      name: "Festival Volunteer",
      description: "Support accessibility desks, stage anchoring, para-sports, and participant hospitality. Requires organizer review.",
      requiresApproval: true,
      requiresPayment: false,
      feeAmountInr: 0,
      displayOrder: 3,
    },
    {
      code: "SLI",
      name: "SLI / Indian Sign Language Professional",
      description: "Qualified Indian Sign Language interpreters supporting plenaries, panels, workshops, and cultural stages. Requires verification.",
      requiresApproval: true,
      requiresPayment: false,
      feeAmountInr: 0,
      displayOrder: 4,
    },
    {
      code: "STUDENT",
      name: "Student Participant",
      description: "School, college, and university students participating in inclusive education, workshops, and youth tracks.",
      requiresApproval: false,
      requiresPayment: false,
      feeAmountInr: 0,
      displayOrder: 5,
    },
    {
      code: "PROFESSIONAL",
      name: "Professional / Educator / Practitioner",
      description: "Educators, healthcare professionals, accessibility specialists, and industry practitioners.",
      requiresApproval: false,
      requiresPayment: false,
      feeAmountInr: 0,
      displayOrder: 6,
    },
    {
      code: "INSTITUTIONAL",
      name: "Institutional Representative",
      description: "Representatives from DPOs, NGOs, universities, and civic institutions.",
      requiresApproval: false,
      requiresPayment: false,
      feeAmountInr: 0,
      displayOrder: 7,
    },
    {
      code: "ALLY",
      name: "Ally / Supporter",
      description: "Family members, caregivers, community allies, and inclusion advocates.",
      requiresApproval: false,
      requiresPayment: false,
      feeAmountInr: 0,
      displayOrder: 8,
    },
  ];

  for (const c of categories) {
    await db
      .insert(schema.registrationCategories)
      .values({ ...c, editionId: edition.id })
      .onConflictDoNothing();
  }

  // 8. Organizations & Partners (Strictly respecting Action Plan written-consent rule)
  const existingPartners = await db.select().from(schema.partners).where(eq(schema.partners.editionId, edition.id));
  if (existingPartners.length === 0) {
    const partnerDefs = [
      {
        name: "Brotherhood",
        shortName: "Brotherhood",
        logoUrl: "/assets/logos/brotherhood-transparent.png",
        logoAltText: "Brotherhood official logo",
        description: "Co-organiser advancing disability inclusion, film festivals, and cultural participation across India.",
        category: "Organizers",
        consentVerified: true,
        status: "PUBLISHED" as const,
        order: 1,
      },
      {
        name: "Global Inclusive Education Network (GIEN)",
        shortName: "GIEN",
        logoUrl: "/assets/logos/gien.jpg",
        logoAltText: "Global Inclusive Education Network (GIEN) official logo",
        description: "Co-organiser driving inclusive education policy, teacher training, and universal design for learning.",
        category: "Organizers",
        consentVerified: true,
        status: "PUBLISHED" as const,
        order: 2,
      },
      {
        name: "The Maharaja Sayajirao University of Baroda",
        shortName: "MSU Baroda",
        logoUrl: null,
        logoAltText: "The Maharaja Sayajirao University of Baroda",
        description: "Committed academic and venue host via Faculty of Social Work and Department of Physical Education.",
        category: "Venue & Academic Partners",
        consentVerified: true,
        status: "PUBLISHED" as const,
        order: 3,
      },
      {
        name: "Praangan",
        shortName: "Praangan",
        logoUrl: "/assets/logos/praangan.jpg",
        logoAltText: "Praangan Foundation logo",
        description: "Listed in brochure as co-organiser; stored in CMS draft pending written logo publicity consent.",
        category: "Organizers",
        consentVerified: false,
        status: "DRAFT" as const,
        order: 4,
      },
      {
        name: "Centre for Disability Studies and Action, TISS",
        shortName: "CDSA-TISS",
        logoUrl: "/assets/logos/cdsa-tiss.jpg",
        logoAltText: "Centre for Disability Studies and Action, TISS logo",
        description: "Academic Partner in brochure; stored in CMS draft pending written publicity consent.",
        category: "Knowledge Partners",
        consentVerified: false,
        status: "DRAFT" as const,
        order: 5,
      },
      {
        name: "Office of the State Commissioner for Persons with Disabilities, Goa",
        shortName: "SCPwD Goa",
        logoUrl: null,
        logoAltText: "State Commissioner for Persons with Disabilities, Goa",
        description: "Knowledge Partner in brochure; stored in CMS draft pending written publicity consent.",
        category: "Knowledge Partners",
        consentVerified: false,
        status: "DRAFT" as const,
        order: 6,
      },
      {
        name: "Gujarat National Law University",
        shortName: "GNLU",
        logoUrl: null,
        logoAltText: "Gujarat National Law University",
        description: "Legal Partner in brochure; stored in CMS draft pending written publicity consent.",
        category: "Knowledge Partners",
        consentVerified: false,
        status: "DRAFT" as const,
        order: 7,
      },
      {
        name: "GMERS Medical College, Gotri",
        shortName: "GMERS Gotri",
        logoUrl: null,
        logoAltText: "GMERS Medical College, Gotri",
        description: "Listed in brochure; stored in CMS draft pending written venue/partner confirmation.",
        category: "Supporting Organizations",
        consentVerified: false,
        status: "DRAFT" as const,
        order: 8,
      },
    ];

    for (const p of partnerDefs) {
      const [org] = await db
        .insert(schema.organizations)
        .values({
          name: p.name,
          shortName: p.shortName,
          logoUrl: p.logoUrl,
          logoAltText: p.logoAltText,
          description: p.description,
        })
        .returning();

      await db.insert(schema.partners).values({
        editionId: edition.id,
        organizationId: org.id,
        category: p.category,
        consentVerified: p.consentVerified,
        displayOrder: p.order,
        status: p.status,
      });
    }
  }

  // 9. Programme Items (Grounded in Purple_Festival_Gujarat_2027_Programme_Grid.xlsx)
  const existingProg = await db.select().from(schema.programmeItems).where(eq(schema.programmeItems.editionId, edition.id));
  if (existingProg.length === 0) {
    const items = [
      // Day 1 — 21 Jan 2027
      { dayNumber: 1, timeSlot: "08:30 – 09:30 IST", title: "Participant Check-in, Accessible Welcome & Registration Desk", category: "Registration", venueName: "Sama Indoor Stadium & MSU Baroda", description: "QR pass verification, Braille & large-print programme distribution, ISL helpdesk, and wheelchair shuttle orientation.", accessibilityServices: ["ISL Interpretation", "Braille Programme", "Step-Free Access", "Sighted Guides"], displayOrder: 1 },
      { dayNumber: 1, timeSlot: "09:30 – 11:00 IST", title: "Inaugural Ceremony: Celebrating Accessibility, Dignity & Diversity", category: "Plenary", venueName: "Main Auditorium", description: "Opening keynote addresses celebrating Gujarat's inaugural Purple Festival edition and India's inclusive future under Viksit Bharat@2047.", accessibilityServices: ["ISL Interpretation", "Live Captioning (CART)", "Audio Description", "Wheelchair Seating"], displayOrder: 2 },
      { dayNumber: 1, timeSlot: "11:30 – 13:00 IST", title: "Panel 1: Universal Design & Accessible Infrastructure for 2047", category: "Panel Discussion", venueName: "Hall A", description: "Architects, urban planners, and disability rights leaders examine barrier-free cities, public transit, and digital public infrastructure.", accessibilityServices: ["ISL Interpretation", "Live Captioning (CART)", "Wheelchair Seating"], displayOrder: 3 },
      { dayNumber: 1, timeSlot: "14:00 – 16:30 IST", title: "Para Sports Championships — Opening Heats (25 Adaptive Disciplines)", category: "Inclusive Sports", venueName: "Sama Indoor Stadium", description: "Adaptive athletics, wheelchair basketball, boccia, blind cricket demonstrations, and inclusive youth sports.", accessibilityServices: ["Step-Free Courts", "Wheelchair Charging Bay", "Medical Support", "ISL Volunteers"], displayOrder: 4 },
      { dayNumber: 1, timeSlot: "14:30 – 17:00 IST", title: "Assistive Technology & Inclusive Innovation Expo", category: "Exhibition", venueName: "Innovation Pavilion", description: "Hands-on demonstrations of screen readers, tactile graphics, mobility aids, AAC devices, and Indian assistive startups.", accessibilityServices: ["Tactile Demos", "ISL Interpretation", "Accessible Counters"], displayOrder: 5 },
      { dayNumber: 1, timeSlot: "18:30 – 20:30 IST", title: "Evening Cultural Showcase: Disability Arts & Inclusive Theatre", category: "Cultural Performance", venueName: "Open-Air Stage", description: "Performances by artists with and without disabilities celebrating Gujarat's folk heritage, contemporary dance, and sign-language music.", accessibilityServices: ["ISL Interpretation", "Audio Description", "Seizure-Safe Lighting", "Sensory Sanctuary Nearby"], displayOrder: 6 },

      // Day 2 — 22 Jan 2027
      { dayNumber: 2, timeSlot: "09:00 – 11:00 IST", title: "Panel 2: Inclusive Education & NEP 2020 Implementation", category: "Inclusive Education", venueName: "MSU Baroda — Hall A", description: "Educators, researchers, and students discuss Universal Design for Learning (UDL), teacher preparedness, and accessible classrooms.", accessibilityServices: ["ISL Interpretation", "Live Captioning (CART)", "Large-Print Handouts"], displayOrder: 7 },
      { dayNumber: 2, timeSlot: "11:30 – 13:00 IST", title: "Panel 3: Rights, Legal Empowerment & the RPwD Act 2016", category: "Panel Discussion", venueName: "Hall B", description: "Legal scholars and DPO representatives examine reasonable accommodation, employment equity, and digital accessibility compliance.", accessibilityServices: ["ISL Interpretation", "Live Captioning (CART)", "Braille Summary"], displayOrder: 8 },
      { dayNumber: 2, timeSlot: "14:00 – 16:00 IST", title: "Inclusive Film Festival & Filmmaker Dialogues", category: "Disability Arts", venueName: "Screening Hall", description: "Curated short films and documentaries on disability leadership with closed captions and audio description.", accessibilityServices: ["Closed Captions", "Audio Description", "ISL Q&A"], displayOrder: 9 },
      { dayNumber: 2, timeSlot: "16:30 – 18:30 IST", title: "Sitaare Zameen Par — Inclusive Celebrity Exhibition Match", category: "Inclusive Sports", venueName: "Sama Indoor Stadium", description: "Flagship unified exhibition match bringing together para-athletes, youth players, and guest personalities at Sama Indoor Stadium.", accessibilityServices: ["Wheelchair Seating", "ISL Interpretation", "Audio Commentary", "Accessible Restrooms"], displayOrder: 10 },

      // Day 3 — 23 Jan 2027
      { dayNumber: 3, timeSlot: "09:30 – 11:30 IST", title: "Inclusive Livelihoods, Entrepreneurship & Employer Conclave", category: "Networking", venueName: "Hall A", description: "Connecting talented persons with disabilities with inclusive employers, startups, and skill-development ecosystems.", accessibilityServices: ["ISL Interpretation", "Live Captioning (CART)", "Quiet Interview Pods"], displayOrder: 11 },
      { dayNumber: 3, timeSlot: "12:00 – 14:00 IST", title: "Para Sports Finals & Medal Ceremonies", category: "Inclusive Sports", venueName: "Sama Indoor Stadium", description: "Championship finals across adaptive disciplines celebrating athletic excellence and inclusive sportsmanship.", accessibilityServices: ["Step-Free Podium", "ISL Interpretation", "Medical Support"], displayOrder: 12 },
      { dayNumber: 3, timeSlot: "15:30 – 17:30 IST", title: "Valedictory Ceremony & Vadodara Declaration on Inclusion", category: "Plenary", venueName: "Main Auditorium", description: "Closing keynote, volunteer and SLI recognition, and adoption of the Purple Festival Gujarat 2027 Inclusion Charter.", accessibilityServices: ["ISL Interpretation", "Live Captioning (CART)", "Braille Charter", "Wheelchair Seating"], displayOrder: 13 },
    ];

    await db.insert(schema.programmeItems).values(
      items.map((item) => ({
        ...item,
        editionId: edition.id,
        status: "PUBLISHED" as const,
      }))
    );
  }

  // 10. CMS Home Page & Sections
  let [homePage] = await db
    .select()
    .from(schema.cmsPages)
    .where(eq(schema.cmsPages.slug, "home"))
    .limit(1);

  if (!homePage) {
    [homePage] = await db
      .insert(schema.cmsPages)
      .values({
        editionId: edition.id,
        slug: "home",
        title: "Purple Festival Gujarat 2027",
        metaDescription:
          "Celebrating Accessibility, Inclusion, Dignity and Diversity — 21–23 January 2027 in Vadodara, Gujarat.",
        status: "PUBLISHED",
      })
      .returning();
  }

  const defaultSections = [
    {
      sectionKey: "hero",
      title: "PURPLE FESTIVAL GUJARAT 2027",
      subtitle: "Where inclusion becomes a celebration.",
      body: "India's landmark festival of accessibility, human dignity, assistive innovation, para-sports, and universal design across all 21 benchmark disabilities under the RPwD Act, 2016.",
      data: {
        dates: "21–23 January 2027",
        location: "Vadodara, Gujarat",
        primaryCtaLabel: "Apply Now",
        primaryCtaHref: "/apply",
      },
      displayOrder: 1,
    },
    {
      sectionKey: "about",
      title: "Inclusion Is Not Charity. It Is Culture, Innovation, and Right.",
      subtitle: "First Edition in Gujarat — Vadodara, January 2027",
      body: "Purple Festival Gujarat 2027 brings together 5,000+ attendees, 500+ participants with and without disabilities, educators, para-athletes, artists, technologists, and policy leaders for three days of barrier-free celebration across Vadodara.",
      data: {
        stats: [
          { label: "Expected Attendees", value: "5,000+" },
          { label: "Benchmark Disabilities", value: "21" },
          { label: "Para Sports Disciplines", value: "25" },
          { label: "Days of Celebration", value: "3" },
        ],
      },
      displayOrder: 2,
    },
    {
      sectionKey: "viksit-bharat",
      title: "A Viksit Bharat Must Be an Inclusive Bharat.",
      subtitle: "Aligned with India's Vision for 2047",
      body: "India's vision for Viksit Bharat@2047 is about progress that reaches every citizen. Purple Festival Gujarat 2027 advances accessible education, dignified livelihoods, assistive technology, and equal cultural participation as pillars of an inclusive nation.",
      data: {
        disclaimer:
          "Purple Festival Gujarat 2027 contributes to the national conversation around inclusive development under Viksit Bharat@2047.",
      },
      displayOrder: 3,
    },
    {
      sectionKey: "contact",
      title: "Connect with the Festival Secretariat",
      subtitle: "Accessibility, Registration, and Partnership Inquiries",
      body: "Reach our team for accommodation requests, institutional delegations, volunteer coordination, or media inquiries.",
      data: {
        email: "purplefestivalvadodara@gmail.com",
        phones: [
          { name: "Radha Tandon", number: "+91 99743 83920" },
          { name: "Satish Kapoor", number: "+91 92117 60202" },
        ],
        city: "Vadodara, Gujarat, India",
      },
      displayOrder: 4,
    },
  ];

  for (const s of defaultSections) {
    await db
      .insert(schema.cmsSections)
      .values({
        pageId: homePage.id,
        sectionKey: s.sectionKey,
        title: s.title,
        subtitle: s.subtitle,
        body: s.body,
        data: s.data,
        displayOrder: s.displayOrder,
        status: "PUBLISHED",
      })
      .onConflictDoNothing();
  }

  // 11. FAQs
  const existingFaqs = await db.select().from(schema.faqItems).where(eq(schema.faqItems.editionId, edition.id));
  if (existingFaqs.length === 0) {
    await db.insert(schema.faqItems).values([
      {
        editionId: edition.id,
        category: "Registration",
        question: "Do I need to disclose a disability to attend Purple Festival Gujarat 2027?",
        answer: "No. Disability disclosure is completely optional and never restricts which category you may apply for. Persons with disabilities can register as Attendees, Delegates, Volunteers, or SLI Professionals.",
        displayOrder: 1,
      },
      {
        editionId: edition.id,
        category: "Accessibility",
        question: "How do I request Indian Sign Language (ISL), Braille, or wheelchair accommodations?",
        answer: "You can select your accommodation requirements during registration or update them anytime in your Participant Dashboard. Your requests are visible only to authorized accessibility coordinators.",
        displayOrder: 2,
      },
      {
        editionId: edition.id,
        category: "QR Pass & Check-in",
        question: "How will I receive my digital QR entry pass?",
        answer: "Once your registration is confirmed (or your Volunteer/SLI application is approved), your unique digital QR pass is issued automatically in your Participant Dashboard and can be downloaded as an accessible PDF.",
        displayOrder: 3,
      },
      {
        editionId: edition.id,
        category: "Volunteers & SLI",
        question: "Does submitting a Volunteer or SLI application mean automatic approval?",
        answer: "No. Volunteer and Indian Sign Language Interpreter applications undergo review by festival coordinators. Once approved, you will receive your role assignment and official digital credential.",
        displayOrder: 4,
      },
    ]);
  }

  // 12. Notification Templates
  const templates = [
    {
      code: "REGISTRATION_CONFIRMED",
      subject: "Confirmed: Your Purple Festival Gujarat 2027 Registration ({{registrationCode}})",
      bodyHtml: "<p>Dear {{fullName}},</p><p>Your registration ({{registrationCode}}) for Purple Festival Gujarat 2027 is confirmed. Log in to your Participant Dashboard to download your accessible QR pass.</p>",
      bodyText: "Dear {{fullName}}, Your registration ({{registrationCode}}) for Purple Festival Gujarat 2027 is confirmed. Log in to your Participant Dashboard to download your accessible QR pass.",
    },
    {
      code: "APPLICATION_SUBMITTED",
      subject: "Application Received: Purple Festival Gujarat 2027 ({{registrationCode}})",
      bodyHtml: "<p>Dear {{fullName}},</p><p>We have received your {{category}} application ({{registrationCode}}). Our review team will notify you once reviewed.</p>",
      bodyText: "Dear {{fullName}}, We have received your {{category}} application ({{registrationCode}}). Our review team will notify you once reviewed.",
    },
    {
      code: "APPLICATION_APPROVED",
      subject: "Approved: Welcome to Purple Festival Gujarat 2027 ({{registrationCode}})",
      bodyHtml: "<p>Dear {{fullName}},</p><p>Congratulations! Your {{category}} application ({{registrationCode}}) has been approved. Your digital QR credential and duty assignment are now available in your dashboard.</p>",
      bodyText: "Dear {{fullName}}, Your {{category}} application ({{registrationCode}}) has been approved. Your digital QR credential and duty assignment are now available in your dashboard.",
    },
  ];

  for (const t of templates) {
    await db.insert(schema.notificationTemplates).values(t).onConflictDoNothing();
  }

  // 13. System Settings
  const defaultSettings = [
    {
      key: "payments.enabled",
      value: { enabled: false, reason: "Awaiting organizer fee confirmation and Razorpay production keys." },
      description: "Master toggle for paid registrations.",
    },
    {
      key: "sli.require_crr",
      value: { required: false, gujaratPriority: true },
      description: "Configurable RCI CRR requirement for SLI applicants.",
    },
  ];

  for (const st of defaultSettings) {
    await db.insert(schema.systemSettings).values(st).onConflictDoNothing();
  }

  // 14. Media Assets (Official Logos + Curated Venue Photos)
  const existingMedia = await db.select().from(schema.mediaAssets).limit(1);
  if (existingMedia.length === 0) {
    await db.insert(schema.mediaAssets).values([
      {
        editionId: edition.id,
        fileName: "purple-festival.jpg",
        url: "/assets/logos/purple-festival.jpg",
        mimeType: "image/jpeg",
        altText: "Purple Festival Gujarat 2027 official emblem showing three inclusive figures beneath hearts",
        category: "LOGO",
        sourceAttribution: "Official Purple Festival Organizer Asset",
        licenseInfo: "Authorized Festival Trademark",
      },
      {
        editionId: edition.id,
        fileName: "venue-01.jpg",
        url: "/assets/gallery/venue-01.jpg",
        mimeType: "image/jpeg",
        altText: "Wide view of the Vadodara sports stadium and athletic ground prepared for inclusive para-sports",
        category: "GALLERY",
        sourceAttribution: "Organizer Venue Reconnaissance — MSU Baroda",
        licenseInfo: "Organizer Supplied",
      },
      {
        editionId: edition.id,
        fileName: "venue-05.jpg",
        url: "/assets/gallery/venue-05.jpg",
        mimeType: "image/jpeg",
        altText: "Indoor pavilion and accessible court facility in Vadodara for Purple Festival Gujarat 2027",
        category: "GALLERY",
        sourceAttribution: "Organizer Venue Reconnaissance — MSU Baroda",
        licenseInfo: "Organizer Supplied",
      },
      {
        editionId: edition.id,
        fileName: "venue-10.jpg",
        url: "/assets/gallery/venue-10.jpg",
        mimeType: "image/jpeg",
        altText: "Outdoor sports field and pavilion lawn at The Maharaja Sayajirao University of Baroda",
        category: "GALLERY",
        sourceAttribution: "Organizer Venue Reconnaissance — MSU Baroda",
        licenseInfo: "Organizer Supplied",
      },
      {
        editionId: edition.id,
        fileName: "venue-15.jpg",
        url: "/assets/gallery/venue-15.jpg",
        mimeType: "image/jpeg",
        altText: "Accessible campus walkway and entrance corridor at Vadodara festival venue",
        category: "GALLERY",
        sourceAttribution: "Organizer Venue Reconnaissance — MSU Baroda",
        licenseInfo: "Organizer Supplied",
      },
      {
        editionId: edition.id,
        fileName: "venue-20.jpg",
        url: "/assets/gallery/venue-20.jpg",
        mimeType: "image/jpeg",
        altText: "Sports complex seating and shaded pavilion area for spectators and para-athletes",
        category: "GALLERY",
        sourceAttribution: "Organizer Venue Reconnaissance — MSU Baroda",
        licenseInfo: "Organizer Supplied",
      },
      {
        editionId: edition.id,
        fileName: "venue-25.jpg",
        url: "/assets/gallery/venue-25.jpg",
        mimeType: "image/jpeg",
        altText: "Campus assembly area and shaded courtyard for workshops and community exhibitions",
        category: "GALLERY",
        sourceAttribution: "Organizer Venue Reconnaissance — MSU Baroda",
        licenseInfo: "Organizer Supplied",
      },
    ]);
  }

  return { editionId: edition.id };
}

if (require.main === module) {
  seedDatabase()
    .then(() => {
      console.log("Database seeded successfully.");
      process.exit(0);
    })
    .catch((err) => {
      console.error("Database seed failed:", err);
      process.exit(1);
    });
}

