// ============================================================================
// Purple Festival Gujarat 2027 — Domain Constants & RPwD Act 2016 Taxonomy
// ============================================================================

export const FESTIVAL_EDITION_SLUG = "gujarat-2027";
export const FESTIVAL_NAME = "Purple Festival Gujarat 2027";
export const FESTIVAL_TAGLINE = "Where inclusion becomes a celebration.";
export const FESTIVAL_DATES_DISPLAY = "21–23 January 2027";
export const FESTIVAL_LOCATION_DISPLAY = "Vadodara, Gujarat";

export const PERMISSIONS = {
  ACCOUNT_READ_OWN: "account.read.own",
  REGISTRATIONS_READ_ALL: "registrations.read.all",
  REGISTRATIONS_WRITE: "registrations.write",
  REGISTRATIONS_EXPORT: "registrations.export",
  VOLUNTEERS_REVIEW: "volunteers.review",
  SLI_REVIEW: "sli.review",
  ACCOMMODATIONS_READ: "accommodations.read",
  ACCOMMODATIONS_MANAGE: "accommodations.manage",
  CMS_MANAGE: "cms.manage",
  ADMIN_ACCOUNTS_MANAGE: "admin_accounts.manage",
  CREDENTIALS_ISSUE: "credentials.issue",
  CREDENTIALS_REVOKE: "credentials.revoke",
  QR_SCAN: "qr.scan",
  CHECKINS_CONFIRM: "checkins.confirm",
  ATTENDANCE_READ_FULL: "attendance.read.full",
  ATTENDANCE_READ_OWN: "attendance.read.own",
  COUPONS_MANAGE: "coupons.manage",
  PAYMENTS_READ: "payments.read",
  AUDIT_LOGS_READ: "audit_logs.read",
  SETTINGS_MANAGE: "settings.manage",
} as const;

export type PermissionCode = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];

export const DEFAULT_ROLE_PERMISSIONS: Record<
  "SUPERADMIN" | "ADMIN" | "RGST" | "PARTICIPANT",
  PermissionCode[]
> = {
  SUPERADMIN: Object.values(PERMISSIONS),
  ADMIN: [
    PERMISSIONS.ACCOUNT_READ_OWN,
    PERMISSIONS.REGISTRATIONS_READ_ALL,
    PERMISSIONS.REGISTRATIONS_WRITE,
    PERMISSIONS.REGISTRATIONS_EXPORT,
    PERMISSIONS.VOLUNTEERS_REVIEW,
    PERMISSIONS.SLI_REVIEW,
    PERMISSIONS.ACCOMMODATIONS_READ,
    PERMISSIONS.ACCOMMODATIONS_MANAGE,
    PERMISSIONS.CREDENTIALS_ISSUE,
    PERMISSIONS.CREDENTIALS_REVOKE,
    PERMISSIONS.QR_SCAN,
    PERMISSIONS.CHECKINS_CONFIRM,
    PERMISSIONS.ATTENDANCE_READ_FULL,
    PERMISSIONS.ATTENDANCE_READ_OWN,
    PERMISSIONS.COUPONS_MANAGE,
    PERMISSIONS.PAYMENTS_READ,
  ],
  RGST: [
    PERMISSIONS.ACCOUNT_READ_OWN,
    PERMISSIONS.QR_SCAN,
    PERMISSIONS.CHECKINS_CONFIRM,
    PERMISSIONS.ATTENDANCE_READ_OWN,
  ],
  PARTICIPANT: [PERMISSIONS.ACCOUNT_READ_OWN],
};

/**
 * All 21 Specified Disabilities under the Rights of Persons with Disabilities (RPwD) Act, 2016 (India).
 */
export const RPWD_21_DISABILITIES = [
  {
    code: "BLINDNESS",
    name: "Blindness",
    rpwdGroup: "Visual Impairment",
    description:
      "Total absence of sight, or visual acuity not exceeding 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 10 degrees.",
    accommodationExamples: [
      "Braille schedules and signage",
      "Screen-reader-compatible digital materials",
      "Sighted guides and tactile floor pathways",
      "Audio description during stage performances",
    ],
  },
  {
    code: "LOW_VISION",
    name: "Low Vision",
    rpwdGroup: "Visual Impairment",
    description:
      "Visual acuity not exceeding 6/18 or less than 20/60 up to 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 40 degrees up to 10 degrees.",
    accommodationExamples: [
      "Large-print schedules and high-contrast signage",
      "Front-row seating near screens",
      "Adjustable text size and high-contrast digital access",
    ],
  },
  {
    code: "LEPROSY_CURED",
    name: "Leprosy Cured Persons",
    rpwdGroup: "Locomotor Disability",
    description:
      "Persons cured of leprosy who continue to experience loss of sensation in hands or feet, paresis in the eye and eyelid, or manifest deformity that limits daily participation.",
    accommodationExamples: [
      "Ergonomic seating and minimal-grip utensils",
      "Accessible washrooms and step-free routes",
      "Dignified, stigma-free assistance desks",
    ],
  },
  {
    code: "HEARING_IMPAIRMENT",
    name: "Hearing Impairment (Deaf and Hard of Hearing)",
    rpwdGroup: "Hearing Impairment",
    description:
      "Includes Deaf persons (70 dB or greater loss in speech frequencies in both ears) and Hard of Hearing persons (60 dB to 70 dB loss in speech frequencies in both ears).",
    accommodationExamples: [
      "Qualified Indian Sign Language (ISL) interpretation",
      "Live captioning (CART) on venue screens",
      "Visual alerts and well-lit front seating for lip-reading",
    ],
  },
  {
    code: "LOCOMOTOR_DISABILITY",
    name: "Locomotor Disability",
    rpwdGroup: "Locomotor Disability",
    description:
      "Inability to execute distinctive activities associated with movement of self and objects resulting from affliction of musculoskeletal or nervous system.",
    accommodationExamples: [
      "Step-free ramps (1:12 gradient) and elevators",
      "Wheelchair-accessible seating and charging bays",
      "Accessible restrooms and battery-operated venue shuttles",
    ],
  },
  {
    code: "DWARFISM",
    name: "Dwarfism",
    rpwdGroup: "Locomotor Disability",
    description:
      "A medical or genetic condition resulting in an adult height of 4 feet 10 inches (147 centimeters) or less.",
    accommodationExamples: [
      "Lowered registration and refreshment counters",
      "Accessible microphones and seating options",
      "Step-free access across all stages and pavilions",
    ],
  },
  {
    code: "INTELLECTUAL_DISABILITY",
    name: "Intellectual Disability",
    rpwdGroup: "Intellectual Disability",
    description:
      "A condition characterized by significant limitation both in intellectual functioning (reasoning, learning, problem solving) and in adaptive behavior covering everyday social and practical skills.",
    accommodationExamples: [
      "Easy-read plain-language schedules with pictograms",
      "Trained orientation volunteers and buddy support",
      "Predictable signage and calm rest areas",
    ],
  },
  {
    code: "MENTAL_ILLNESS",
    name: "Mental Illness",
    rpwdGroup: "Mental Behavior",
    description:
      "A substantial disorder of thinking, mood, perception, orientation, or memory that grossly impairs judgment, behavior, capacity to recognize reality, or ability to meet the ordinary demands of life.",
    accommodationExamples: [
      "Quiet sensory-friendly sanctuary spaces",
      "Flexible breaks without losing seating",
      "Trained psychosocial first-aid volunteers",
    ],
  },
  {
    code: "AUTISM_SPECTRUM_DISORDER",
    name: "Autism Spectrum Disorder",
    rpwdGroup: "Intellectual / Neurodevelopmental",
    description:
      "A neuro-developmental condition typically appearing in the first three years of life that significantly affects verbal and non-verbal communication, social interaction, and sensory processing.",
    accommodationExamples: [
      "Low-stimulation sensory sanctuary rooms",
      "Noise-dampening headphones and visual schedules",
      "Advance venue walkthroughs and reduced-queue entry",
    ],
  },
  {
    code: "CEREBRAL_PALSY",
    name: "Cerebral Palsy",
    rpwdGroup: "Locomotor Disability",
    description:
      "A group of non-progressive neurological conditions affecting body movements and muscle coordination caused by damage to one or more specific areas of the brain.",
    accommodationExamples: [
      "Wide wheelchair pathways and supportive seating",
      "AAC (Augmentative & Alternative Communication) patience",
      "Accessible restrooms with caregiver space",
    ],
  },
  {
    code: "MUSCULAR_DYSTROPHY",
    name: "Muscular Dystrophy",
    rpwdGroup: "Locomotor Disability",
    description:
      "A group of hereditary genetic muscle diseases that weaken the muscles that move the human body, characterized by progressive skeletal muscle weakness.",
    accommodationExamples: [
      "Power wheelchair charging bays",
      "Short-distance accessible drop-off points",
      "Climate-controlled rest zones",
    ],
  },
  {
    code: "CHRONIC_NEUROLOGICAL",
    name: "Chronic Neurological Conditions",
    rpwdGroup: "Chronic Neurological",
    description:
      "Conditions originating in the central or peripheral nervous system (such as epilepsy, stroke aftermath, or neurodegenerative conditions) requiring ongoing management.",
    accommodationExamples: [
      "Seizure-safe lighting (no flashing strobes)",
      "Priority shaded seating and hydration stations",
      "On-site medical emergency response desk",
    ],
  },
  {
    code: "SPECIFIC_LEARNING_DISABILITIES",
    name: "Specific Learning Disabilities",
    rpwdGroup: "Intellectual / Neurodevelopmental",
    description:
      "A heterogeneous group of conditions wherein there is a deficit in processing language, spoken or written, manifesting as difficulty in comprehending, reading, writing, spelling, or mathematical calculation.",
    accommodationExamples: [
      "Readable sans-serif typography and uncluttered layouts",
      "Audio and visual alternatives to dense text",
      "Untimed, save-and-resume registration workflows",
    ],
  },
  {
    code: "MULTIPLE_SCLEROSIS",
    name: "Multiple Sclerosis",
    rpwdGroup: "Chronic Neurological",
    description:
      "An inflammatory, nervous system disease in which the myelin sheaths around the axons of nerve cells of the brain and spinal cord are damaged, affecting communication between brain and body.",
    accommodationExamples: [
      "Cooling and shaded rest areas to prevent heat fatigue",
      "Mobility assistance and priority seating",
      "Accessible restrooms near every hall",
    ],
  },
  {
    code: "SPEECH_AND_LANGUAGE",
    name: "Speech and Language Disability",
    rpwdGroup: "Speech & Language",
    description:
      "A permanent disability arising out of conditions such as laryngectomy or aphasia affecting one or more components of speech and language.",
    accommodationExamples: [
      "Text, email, and digital chat support at help desks",
      "Extended speaking time and AAC-friendly microphones",
      "Written Q&A options during panel discussions",
    ],
  },
  {
    code: "THALASSEMIA",
    name: "Thalassemia",
    rpwdGroup: "Blood Disorder",
    description:
      "A group of inherited disorders characterized by reduced or absent amounts of hemoglobin.",
    accommodationExamples: [
      "Priority seating to reduce standing fatigue",
      "Hydration and dietary accommodation support",
      "Quick access to medical assistance desks",
    ],
  },
  {
    code: "HEMOPHILIA",
    name: "Hemophilia",
    rpwdGroup: "Blood Disorder",
    description:
      "An inheritable disease, usually affecting males, characterized by impairment of the normal clotting ability of blood.",
    accommodationExamples: [
      "Safe, uncrowded pathways and priority entry lanes",
      "On-site medical room awareness and rapid first aid",
      "Accessible seating near aisles",
    ],
  },
  {
    code: "SICKLE_CELL_DISEASE",
    name: "Sickle Cell Disease",
    rpwdGroup: "Blood Disorder",
    description:
      "A hemolytic disorder characterized by chronic anemia, painful events, and various complications due to associated tissue and organ damage.",
    accommodationExamples: [
      "Shaded rest lounges and continuous hydration stations",
      "Minimal walking distances with shuttle support",
      "Medical assistance readiness",
    ],
  },
  {
    code: "MULTIPLE_DISABILITIES",
    name: "Multiple Disabilities (including Deafblindness)",
    rpwdGroup: "Multiple Disabilities",
    description:
      "Two or more of the specified disabilities occurring together, including deafblindness — a combination of hearing and visual impairments causing severe communication and developmental needs.",
    accommodationExamples: [
      "Tactile sign language interpreters and intervener support",
      "Combined physical, sensory, and communication accommodations",
      "Dedicated companion / escort seating",
    ],
  },
  {
    code: "ACID_ATTACK_VICTIM",
    name: "Acid Attack Victim",
    rpwdGroup: "Locomotor / Physical",
    description:
      "A person disfigured due to violent assaults by throwing of acid or similar corrosive substance.",
    accommodationExamples: [
      "Climate-controlled, sun-protected spaces",
      "Visual or mobility accommodations as individually requested",
      "Respectful, privacy-preserving check-in and support",
    ],
  },
  {
    code: "PARKINSONS_DISEASE",
    name: "Parkinson's Disease",
    rpwdGroup: "Chronic Neurological",
    description:
      "A progressive disease of the nervous system marked by tremor, muscular rigidity, and slow, imprecise movement.",
    accommodationExamples: [
      "Large touch targets and keyboard-accessible controls",
      "Seated check-in desks and handrails along walkways",
      "Untimed forms and mobility assistance",
    ],
  },
] as const;

export const ACCOMMODATION_TYPES_SEED = [
  { code: "ISL_INTERPRETER", label: "Indian Sign Language (ISL) Interpreter" },
  { code: "TACTILE_INTERPRETATION", label: "Tactile Interpretation (Deafblind support)" },
  { code: "BRAILLE_SCHEDULE", label: "Braille Schedule & Programme" },
  { code: "LARGE_PRINT", label: "Large-Print Material" },
  { code: "SCREEN_READER_DIGITAL", label: "Screen-Reader-Compatible Digital Material" },
  { code: "WHEELCHAIR_SEATING", label: "Wheelchair-Accessible Seating" },
  { code: "WHEELCHAIR_CHARGING", label: "Wheelchair Charging Bay" },
  { code: "SIGHTED_GUIDE", label: "Sighted Guide" },
  { code: "SENSORY_SANCTUARY", label: "Sensory Sanctuary Access" },
  { code: "CAPTIONING", label: "Live Captioning (CART)" },
  { code: "MOBILITY_ASSISTANCE", label: "Mobility Assistance / Venue Shuttle" },
  { code: "DIETARY", label: "Dietary Accommodation" },
  { code: "OTHER", label: "Other Accessibility Requirement" },
] as const;

export const OCCUPATION_OPTIONS = [
  "Student",
  "Teacher",
  "Academic",
  "Researcher",
  "NGO Professional",
  "Government Official",
  "Healthcare Professional",
  "Corporate Professional",
  "Accessibility Professional",
  "Artist",
  "Entrepreneur",
  "Other",
] as const;

export const FESTIVAL_DAYS = [
  { code: "DAY_1", label: "Day 1 — 21 January 2027", isoDate: "2027-01-21" },
  { code: "DAY_2", label: "Day 2 — 22 January 2027", isoDate: "2027-01-22" },
  { code: "DAY_3", label: "Day 3 — 23 January 2027", isoDate: "2027-01-23" },
] as const;

/**
 * Formats a Date into IST (Asia/Kolkata) YYYY-MM-DD for attendance day scoping.
 */
export function getIstDateString(date: Date = new Date()): string {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Kolkata",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(date);
}

/**
 * Formats a Date into human-readable IST timestamp for reception desk operators.
 */
export function formatIstTimestamp(date: Date | string): string {
  const d = typeof date === "string" ? new Date(date) : date;
  return (
    new Intl.DateTimeFormat("en-IN", {
      timeZone: "Asia/Kolkata",
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: true,
    }).format(d) + " IST"
  );
}

