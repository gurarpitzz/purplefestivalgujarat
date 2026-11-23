import {
  pgTable,
  uuid,
  text,
  varchar,
  boolean,
  integer,
  timestamp,
  jsonb,
  uniqueIndex,
  index,
  pgEnum,
} from "drizzle-orm/pg-core";

// ============================================================================
// ENUMS
// ============================================================================

export const roleCodeEnum = pgEnum("role_code", [
  "SUPERADMIN",
  "ADMIN",
  "RGST",
  "PARTICIPANT",
]);

export const registrationStatusEnum = pgEnum("registration_status", [
  "DRAFT",
  "SUBMITTED",
  "UNDER_REVIEW",
  "ADDITIONAL_INFO_REQUIRED",
  "CONFIRMED",
  "APPROVED",
  "WAITLISTED",
  "REJECTED",
  "WITHDRAWN",
  "CANCELLED",
]);

export const paymentStatusEnum = pgEnum("payment_status", [
  "NOT_REQUIRED",
  "PENDING",
  "AUTHORIZED",
  "CAPTURED",
  "FAILED",
  "REFUNDED",
  "PARTIALLY_REFUNDED",
]);

export const credentialStatusEnum = pgEnum("credential_status", [
  "ACTIVE",
  "REVOKED",
  "EXPIRED",
  "SUSPENDED",
]);

export const attendancePolicyEnum = pgEnum("attendance_policy", [
  "ONCE_PER_FESTIVAL",
  "ONCE_PER_DAY",
  "PER_SESSION",
  "RESTRICTED_AREA",
]);

export const publishStatusEnum = pgEnum("publish_status", [
  "DRAFT",
  "PUBLISHED",
  "ARCHIVED",
]);

export const notificationStatusEnum = pgEnum("notification_status", [
  "QUEUED",
  "PROCESSING",
  "SENT",
  "DELIVERED",
  "FAILED",
  "BOUNCED",
]);

export const discountTypeEnum = pgEnum("discount_type", [
  "PERCENTAGE",
  "FIXED_AMOUNT",
  "FULL_WAIVER",
]);

// ============================================================================
// 1. AUTHENTICATION & RBAC
// ============================================================================

export const users = pgTable(
  "users",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    email: varchar("email", { length: 255 }).notNull(),
    emailVerifiedAt: timestamp("email_verified_at", { withTimezone: true }),
    passwordHash: text("password_hash"),
    fullName: varchar("full_name", { length: 200 }).notNull(),
    phone: varchar("phone", { length: 40 }),
    country: varchar("country", { length: 100 }).default("India").notNull(),
    isActive: boolean("is_active").default(true).notNull(),
    mfaEnabled: boolean("mfa_enabled").default(false).notNull(),
    mfaSecretEncrypted: text("mfa_secret_encrypted"),
    failedLoginAttempts: integer("failed_login_attempts").default(0).notNull(),
    lockedUntil: timestamp("locked_until", { withTimezone: true }),
    lastLoginAt: timestamp("last_login_at", { withTimezone: true }),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
    deletedAt: timestamp("deleted_at", { withTimezone: true }),
  },
  (t) => [uniqueIndex("users_email_unique").on(t.email)]
);

export const authAccounts = pgTable(
  "auth_accounts",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    provider: varchar("provider", { length: 64 }).notNull(), // 'credentials', 'email_otp', 'password_reset'
    providerAccountId: varchar("provider_account_id", { length: 255 }).notNull(),
    tokenHash: text("token_hash"),
    expiresAt: timestamp("expires_at", { withTimezone: true }),
    metadata: jsonb("metadata"),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [index("auth_accounts_user_idx").on(t.userId)]
);

export const sessions = pgTable(
  "sessions",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    tokenHash: varchar("token_hash", { length: 128 }).notNull(),
    ipAddress: varchar("ip_address", { length: 64 }),
    userAgent: text("user_agent"),
    mfaVerified: boolean("mfa_verified").default(false).notNull(),
    expiresAt: timestamp("expires_at", { withTimezone: true }).notNull(),
    revokedAt: timestamp("revoked_at", { withTimezone: true }),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [
    uniqueIndex("sessions_token_hash_unique").on(t.tokenHash),
    index("sessions_user_idx").on(t.userId),
  ]
);

export const roles = pgTable(
  "roles",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    code: roleCodeEnum("code").notNull(),
    name: varchar("name", { length: 100 }).notNull(),
    description: text("description"),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("roles_code_unique").on(t.code)]
);

export const permissions = pgTable(
  "permissions",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    code: varchar("code", { length: 100 }).notNull(), // e.g. 'registrations.read', 'cms.write', 'checkins.confirm'
    description: text("description"),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("permissions_code_unique").on(t.code)]
);

export const rolePermissions = pgTable(
  "role_permissions",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    roleId: uuid("role_id")
      .notNull()
      .references(() => roles.id, { onDelete: "cascade" }),
    permissionId: uuid("permission_id")
      .notNull()
      .references(() => permissions.id, { onDelete: "cascade" }),
  },
  (t) => [uniqueIndex("role_permissions_unique").on(t.roleId, t.permissionId)]
);

export const userRoles = pgTable(
  "user_roles",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    roleId: uuid("role_id")
      .notNull()
      .references(() => roles.id, { onDelete: "cascade" }),
    customPermissions: jsonb("custom_permissions").$type<string[]>(), // Per-admin permission overrides configured by Superadmin
    assignedBy: uuid("assigned_by").references(() => users.id, { onDelete: "set null" }),
    assignedAt: timestamp("assigned_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("user_roles_unique").on(t.userId, t.roleId)]
);

// ============================================================================
// 2. FESTIVAL CORE
// ============================================================================

export const festivalEditions = pgTable(
  "festival_editions",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    slug: varchar("slug", { length: 80 }).notNull(), // 'gujarat-2027'
    name: varchar("name", { length: 200 }).notNull(), // 'Purple Festival Gujarat 2027'
    tagline: varchar("tagline", { length: 300 }).notNull(),
    city: varchar("city", { length: 100 }).notNull(),
    state: varchar("state", { length: 100 }).notNull(),
    startDate: timestamp("start_date", { withTimezone: true }).notNull(),
    endDate: timestamp("end_date", { withTimezone: true }).notNull(),
    registrationOpensAt: timestamp("registration_opens_at", { withTimezone: true }),
    registrationClosesAt: timestamp("registration_closes_at", { withTimezone: true }),
    attendancePolicy: attendancePolicyEnum("attendance_policy")
      .default("ONCE_PER_DAY")
      .notNull(),
    isActive: boolean("is_active").default(true).notNull(),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("festival_editions_slug_unique").on(t.slug)]
);

export const venues = pgTable("venues", {
  id: uuid("id").defaultRandom().primaryKey(),
  editionId: uuid("edition_id")
    .notNull()
    .references(() => festivalEditions.id, { onDelete: "cascade" }),
  name: varchar("name", { length: 200 }).notNull(),
  address: text("address"),
  confirmationStatus: varchar("confirmation_status", { length: 50 })
    .default("PENDING")
    .notNull(), // 'CONFIRMED', 'COMMITTED', 'PENDING'
  accessibilityFeatures: jsonb("accessibility_features").$type<string[]>().default([]),
  capacity: integer("capacity"),
  mapUrl: text("map_url"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const festivalSessions = pgTable("festival_sessions", {
  id: uuid("id").defaultRandom().primaryKey(),
  editionId: uuid("edition_id")
    .notNull()
    .references(() => festivalEditions.id, { onDelete: "cascade" }),
  venueId: uuid("venue_id").references(() => venues.id, { onDelete: "set null" }),
  title: varchar("title", { length: 250 }).notNull(),
  track: varchar("track", { length: 100 }).notNull(),
  dayNumber: integer("day_number").notNull(), // 1, 2, 3
  startsAt: timestamp("starts_at", { withTimezone: true }).notNull(),
  endsAt: timestamp("ends_at", { withTimezone: true }).notNull(),
  islRequired: boolean("isl_required").default(true).notNull(),
  captioningAvailable: boolean("captioning_available").default(true).notNull(),
  wheelchairAccessible: boolean("wheelchair_accessible").default(true).notNull(),
  status: publishStatusEnum("status").default("PUBLISHED").notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const programmeItems = pgTable("programme_items", {
  id: uuid("id").defaultRandom().primaryKey(),
  editionId: uuid("edition_id")
    .notNull()
    .references(() => festivalEditions.id, { onDelete: "cascade" }),
  sessionId: uuid("session_id").references(() => festivalSessions.id, {
    onDelete: "set null",
  }),
  dayNumber: integer("day_number").notNull(),
  timeSlot: varchar("time_slot", { length: 80 }).notNull(),
  title: varchar("title", { length: 250 }).notNull(),
  description: text("description"),
  category: varchar("category", { length: 100 }).notNull(),
  venueName: varchar("venue_name", { length: 200 }),
  speakers: jsonb("speakers").$type<Array<{ name: string; role?: string; org?: string }>>().default([]),
  accessibilityServices: jsonb("accessibility_services").$type<string[]>().default([]),
  displayOrder: integer("display_order").default(0).notNull(),
  status: publishStatusEnum("status").default("PUBLISHED").notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

export const organizations = pgTable("organizations", {
  id: uuid("id").defaultRandom().primaryKey(),
  name: varchar("name", { length: 250 }).notNull(),
  shortName: varchar("short_name", { length: 100 }),
  websiteUrl: text("website_url"),
  logoUrl: text("logo_url"),
  logoAltText: text("logo_alt_text"),
  description: text("description"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const partners = pgTable("partners", {
  id: uuid("id").defaultRandom().primaryKey(),
  editionId: uuid("edition_id")
    .notNull()
    .references(() => festivalEditions.id, { onDelete: "cascade" }),
  organizationId: uuid("organization_id")
    .notNull()
    .references(() => organizations.id, { onDelete: "cascade" }),
  category: varchar("category", { length: 100 }).notNull(), // 'Organizers', 'Knowledge Partners', 'Venue Partners', etc.
  consentVerified: boolean("consent_verified").default(false).notNull(),
  displayOrder: integer("display_order").default(0).notNull(),
  status: publishStatusEnum("status").default("DRAFT").notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

// ============================================================================
// 3. REGISTRATION & APPLICATIONS
// ============================================================================

export const registrationCategories = pgTable(
  "registration_categories",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    editionId: uuid("edition_id")
      .notNull()
      .references(() => festivalEditions.id, { onDelete: "cascade" }),
    code: varchar("code", { length: 64 }).notNull(), // 'ATTENDEE', 'DELEGATE', 'VOLUNTEER', 'SLI', 'STUDENT', 'ALLY', 'INSTITUTIONAL'
    name: varchar("name", { length: 150 }).notNull(),
    description: text("description"),
    requiresApproval: boolean("requires_approval").default(false).notNull(),
    requiresPayment: boolean("requires_payment").default(false).notNull(),
    feeAmountInr: integer("fee_amount_inr").default(0).notNull(), // Stored in INR whole rupees; 0 = free
    isActive: boolean("is_active").default(true).notNull(),
    displayOrder: integer("display_order").default(0).notNull(),
    opensAt: timestamp("opens_at", { withTimezone: true }),
    closesAt: timestamp("closes_at", { withTimezone: true }),
    confirmationMessage: text("confirmation_message"),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("reg_cat_edition_code_unique").on(t.editionId, t.code)]
);

export const registrationFormVersions = pgTable(
  "registration_form_versions",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    editionId: uuid("edition_id")
      .notNull()
      .references(() => festivalEditions.id, { onDelete: "cascade" }),
    categoryCode: varchar("category_code", { length: 64 }).notNull(),
    versionNumber: integer("version_number").notNull(),
    schemaDefinition: jsonb("schema_definition").notNull(),
    isActive: boolean("is_active").default(true).notNull(),
    createdBy: uuid("created_by").references(() => users.id, { onDelete: "set null" }),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [
    uniqueIndex("reg_form_ver_unique").on(
      t.editionId,
      t.categoryCode,
      t.versionNumber
    ),
  ]
);

export const registrations = pgTable(
  "registrations",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    registrationCode: varchar("registration_code", { length: 40 }).notNull(), // e.g., PFG-2027-000001
    editionId: uuid("edition_id")
      .notNull()
      .references(() => festivalEditions.id, { onDelete: "cascade" }),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    categoryId: uuid("category_id")
      .notNull()
      .references(() => registrationCategories.id, { onDelete: "restrict" }),
    categoryCode: varchar("category_code", { length: 64 }).notNull(),
    formVersionId: uuid("form_version_id").references(
      () => registrationFormVersions.id,
      { onDelete: "set null" }
    ),
    status: registrationStatusEnum("status").default("SUBMITTED").notNull(),
    paymentStatus: paymentStatusEnum("payment_status")
      .default("NOT_REQUIRED")
      .notNull(),
    fullName: varchar("full_name", { length: 200 }).notNull(),
    email: varchar("email", { length: 255 }).notNull(),
    phone: varchar("phone", { length: 40 }).notNull(),
    country: varchar("country", { length: 100 }).default("India").notNull(),
    occupation: varchar("occupation", { length: 120 }),
    organizationName: varchar("organization_name", { length: 200 }),
    designation: varchar("designation", { length: 150 }),
    isPersonWithDisability: varchar("is_person_with_disability", { length: 32 })
      .default("PREFER_NOT_TO_SAY")
      .notNull(), // 'YES', 'NO', 'PREFER_NOT_TO_SAY'
    attendingDays: jsonb("attending_days").$type<string[]>().default([]),
    interestedTracks: jsonb("interested_tracks").$type<string[]>().default([]),
    reviewerId: uuid("reviewer_id").references(() => users.id, {
      onDelete: "set null",
    }),
    reviewNotes: text("review_notes"),
    reviewedAt: timestamp("reviewed_at", { withTimezone: true }),
    submittedAt: timestamp("submitted_at", { withTimezone: true }).defaultNow(),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
    deletedAt: timestamp("deleted_at", { withTimezone: true }),
  },
  (t) => [
    uniqueIndex("registrations_code_unique").on(t.registrationCode),
    index("registrations_user_idx").on(t.userId),
    index("registrations_edition_cat_idx").on(t.editionId, t.categoryCode),
    index("registrations_status_idx").on(t.status),
  ]
);

export const registrationResponses = pgTable("registration_responses", {
  id: uuid("id").defaultRandom().primaryKey(),
  registrationId: uuid("registration_id")
    .notNull()
    .references(() => registrations.id, { onDelete: "cascade" }),
  formVersionId: uuid("form_version_id").references(
    () => registrationFormVersions.id,
    { onDelete: "set null" }
  ),
  answers: jsonb("answers").notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

export const participantAffiliations = pgTable("participant_affiliations", {
  id: uuid("id").defaultRandom().primaryKey(),
  userId: uuid("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  occupation: varchar("occupation", { length: 120 }),
  organizationName: varchar("organization_name", { length: 200 }),
  designation: varchar("designation", { length: 150 }),
  city: varchar("city", { length: 100 }),
  state: varchar("state", { length: 100 }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const delegateDetails = pgTable("delegate_details", {
  id: uuid("id").defaultRandom().primaryKey(),
  registrationId: uuid("registration_id")
    .notNull()
    .references(() => registrations.id, { onDelete: "cascade" }),
  gender: varchar("gender", { length: 50 }),
  age: integer("age"),
  cityState: varchar("city_state", { length: 150 }),
  paraSportsInterests: jsonb("para_sports_interests").$type<string[]>().default([]),
  outstationTravelRequired: boolean("outstation_travel_required").default(false),
  accommodationNeeded: boolean("accommodation_needed").default(false),
  foodPreference: varchar("food_preference", { length: 80 }),
  emergencyContactName: varchar("emergency_contact_name", { length: 150 }),
  emergencyContactPhone: varchar("emergency_contact_phone", { length: 40 }),
  guardianName: varchar("guardian_name", { length: 150 }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const volunteerApplications = pgTable("volunteer_applications", {
  id: uuid("id").defaultRandom().primaryKey(),
  registrationId: uuid("registration_id")
    .notNull()
    .references(() => registrations.id, { onDelete: "cascade" }),
  age: integer("age"),
  gender: varchar("gender", { length: 50 }),
  cityState: varchar("city_state", { length: 150 }),
  institutionName: varchar("institution_name", { length: 200 }),
  isMsuStudentOrAlumni: boolean("is_msu_student_or_alumni").default(false),
  crrNumber: varchar("crr_number", { length: 80 }), // RCI CRR No. from VOLUNTEER AND SLI.docx
  preferredRoles: jsonb("preferred_roles").$type<string[]>().default([]),
  availabilityDays: jsonb("availability_days").$type<string[]>().default([]),
  languagesSpoken: jsonb("languages_spoken").$type<string[]>().default([]),
  relevantExperience: text("relevant_experience"),
  emergencyContactName: varchar("emergency_contact_name", { length: 150 }),
  emergencyContactPhone: varchar("emergency_contact_phone", { length: 40 }),
  documentUrl: text("document_url"),
  // Admin Assignment fields
  assignedDepartment: varchar("assigned_department", { length: 120 }),
  assignedResponsibility: text("assigned_responsibility"),
  assignedSupervisor: varchar("assigned_supervisor", { length: 150 }),
  assignedVenue: varchar("assigned_venue", { length: 200 }),
  assignedShift: varchar("assigned_shift", { length: 100 }),
  reportingTime: varchar("reporting_time", { length: 80 }),
  assignmentNotes: text("assignment_notes"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

export const sliApplications = pgTable("sli_applications", {
  id: uuid("id").defaultRandom().primaryKey(),
  registrationId: uuid("registration_id")
    .notNull()
    .references(() => registrations.id, { onDelete: "cascade" }),
  cityState: varchar("city_state", { length: 150 }),
  basedInGujarat: boolean("based_in_gujarat").default(true),
  crrNumber: varchar("crr_number", { length: 80 }), // Optional configurable RCI CRR No.
  qualifications: text("qualifications"),
  trainingInstitute: varchar("training_institute", { length: 200 }),
  certificationLevel: varchar("certification_level", { length: 120 }),
  yearsOfExperience: integer("years_of_experience"),
  professionalAffiliation: varchar("professional_affiliation", { length: 200 }),
  areasOfExpertise: jsonb("areas_of_expertise").$type<string[]>().default([]),
  interpretationPreferences: jsonb("interpretation_preferences")
    .$type<string[]>()
    .default([]),
  availabilityDays: jsonb("availability_days").$type<string[]>().default([]),
  videoDemoUrl: text("video_demo_url"),
  documentUrl: text("document_url"),
  honorariumOrTravelNotes: text("honorarium_or_travel_notes"),
  // Admin Assignment fields
  assignedSessions: jsonb("assigned_sessions")
    .$type<Array<{ sessionTitle: string; venue: string; dutyTime: string; day: string }>>()
    .default([]),
  assignedVenue: varchar("assigned_venue", { length: 200 }),
  dutyTimes: varchar("duty_times", { length: 150 }),
  assignmentNotes: text("assignment_notes"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

// ============================================================================
// 4. ACCESSIBILITY & ACCOMMODATIONS (SENSITIVE — PERMISSION PROTECTED)
// ============================================================================

export const disabilityCategories = pgTable(
  "disability_categories",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    code: varchar("code", { length: 64 }).notNull(),
    name: varchar("name", { length: 180 }).notNull(),
    rpwdGroup: varchar("rpwd_group", { length: 100 }).notNull(), // Physical, Intellectual, Mental, Chronic Neurological, Blood, Multiple
    description: text("description").notNull(),
    accommodationExamples: jsonb("accommodation_examples").$type<string[]>().default([]),
    displayOrder: integer("display_order").default(0).notNull(),
  },
  (t) => [uniqueIndex("disability_categories_code_unique").on(t.code)]
);

export const participantDisabilityDisclosures = pgTable(
  "participant_disability_disclosures",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    registrationId: uuid("registration_id")
      .notNull()
      .references(() => registrations.id, { onDelete: "cascade" }),
    disabilityCategoryId: uuid("disability_category_id").references(
      () => disabilityCategories.id,
      { onDelete: "set null" }
    ),
    selfDescription: text("self_description"),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [index("pdd_reg_idx").on(t.registrationId)]
);

export const accommodationTypes = pgTable(
  "accommodation_types",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    code: varchar("code", { length: 64 }).notNull(),
    label: varchar("label", { length: 180 }).notNull(),
    description: text("description"),
    isActive: boolean("is_active").default(true).notNull(),
    displayOrder: integer("display_order").default(0).notNull(),
  },
  (t) => [uniqueIndex("accommodation_types_code_unique").on(t.code)]
);

export const accommodationRequests = pgTable(
  "accommodation_requests",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    registrationId: uuid("registration_id")
      .notNull()
      .references(() => registrations.id, { onDelete: "cascade" }),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    selectedTypes: jsonb("selected_types").$type<string[]>().default([]),
    freeTextDetails: text("free_text_details"),
    status: varchar("status", { length: 40 }).default("REQUESTED").notNull(), // 'REQUESTED', 'IN_PROGRESS', 'ARRANGED', 'DECLINED'
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [index("accomm_req_reg_idx").on(t.registrationId)]
);

export const accommodationAssignments = pgTable("accommodation_assignments", {
  id: uuid("id").defaultRandom().primaryKey(),
  requestId: uuid("request_id")
    .notNull()
    .references(() => accommodationRequests.id, { onDelete: "cascade" }),
  assignedToUserId: uuid("assigned_to_user_id").references(() => users.id, {
    onDelete: "set null",
  }),
  coordinatorNotes: text("coordinator_notes"),
  status: varchar("status", { length: 40 }).default("ASSIGNED").notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

// ============================================================================
// 5. QR CREDENTIALS & ATTENDANCE / CHECK-IN
// ============================================================================

export const qrCredentials = pgTable(
  "qr_credentials",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    credentialCode: varchar("credential_code", { length: 40 }).notNull(), // e.g., PFG-2027-000001
    tokenHash: varchar("token_hash", { length: 128 }).notNull(), // SHA-256 of opaque signed token (never store raw token)
    registrationId: uuid("registration_id")
      .notNull()
      .references(() => registrations.id, { onDelete: "cascade" }),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    editionId: uuid("edition_id")
      .notNull()
      .references(() => festivalEditions.id, { onDelete: "cascade" }),
    categoryCode: varchar("category_code", { length: 64 }).notNull(),
    status: credentialStatusEnum("status").default("ACTIVE").notNull(),
    issuedAt: timestamp("issued_at", { withTimezone: true }).defaultNow().notNull(),
    revokedAt: timestamp("revoked_at", { withTimezone: true }),
    revokedBy: uuid("revoked_by").references(() => users.id, { onDelete: "set null" }),
    revocationReason: text("revocation_reason"),
    replacedByCredentialId: uuid("replaced_by_credential_id"),
  },
  (t) => [
    uniqueIndex("qr_credentials_code_unique").on(t.credentialCode),
    uniqueIndex("qr_credentials_token_hash_unique").on(t.tokenHash),
    index("qr_credentials_reg_idx").on(t.registrationId),
  ]
);

export const checkinStations = pgTable("checkin_stations", {
  id: uuid("id").defaultRandom().primaryKey(),
  editionId: uuid("edition_id")
    .notNull()
    .references(() => festivalEditions.id, { onDelete: "cascade" }),
  venueId: uuid("venue_id").references(() => venues.id, { onDelete: "set null" }),
  name: varchar("name", { length: 150 }).notNull(), // e.g. 'Main Gate A — Sama Indoor Stadium'
  gateCode: varchar("gate_code", { length: 50 }).notNull(),
  isActive: boolean("is_active").default(true).notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const attendanceEntitlements = pgTable(
  "attendance_entitlements",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    credentialId: uuid("credential_id")
      .notNull()
      .references(() => qrCredentials.id, { onDelete: "cascade" }),
    policyType: attendancePolicyEnum("policy_type").notNull(),
    scopeKey: varchar("scope_key", { length: 100 }).notNull(), // e.g. 'FESTIVAL', '2027-01-21', 'SESSION:<id>'
    maxUses: integer("max_uses").default(1).notNull(),
    usedCount: integer("used_count").default(0).notNull(),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [
    uniqueIndex("attendance_entitlements_unique").on(t.credentialId, t.scopeKey),
  ]
);

export const checkins = pgTable(
  "checkins",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    editionId: uuid("edition_id")
      .notNull()
      .references(() => festivalEditions.id, { onDelete: "cascade" }),
    credentialId: uuid("credential_id")
      .notNull()
      .references(() => qrCredentials.id, { onDelete: "cascade" }),
    registrationId: uuid("registration_id")
      .notNull()
      .references(() => registrations.id, { onDelete: "cascade" }),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    operatorUserId: uuid("operator_user_id")
      .notNull()
      .references(() => users.id, { onDelete: "restrict" }),
    stationId: uuid("station_id").references(() => checkinStations.id, {
      onDelete: "set null",
    }),
    venueLabel: varchar("venue_label", { length: 150 }).notNull(),
    entitlementScope: varchar("entitlement_scope", { length: 100 }).notNull(), // Prevents duplicate check-in per scope (e.g. '2027-01-21' or 'FESTIVAL')
    checkinDateIst: varchar("checkin_date_ist", { length: 20 }).notNull(), // YYYY-MM-DD in IST
    method: varchar("method", { length: 40 }).default("QR_CAMERA").notNull(), // 'QR_CAMERA', 'USB_SCANNER', 'MANUAL_ID'
    result: varchar("result", { length: 40 }).default("CHECKED_IN").notNull(),
    checkedInAt: timestamp("checked_in_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [
    // DB-level race-condition protection against duplicate check-ins!
    uniqueIndex("checkins_credential_scope_unique").on(
      t.credentialId,
      t.entitlementScope
    ),
    index("checkins_reg_idx").on(t.registrationId),
    index("checkins_operator_idx").on(t.operatorUserId),
  ]
);

export const credentialEvents = pgTable("credential_events", {
  id: uuid("id").defaultRandom().primaryKey(),
  credentialId: uuid("credential_id")
    .notNull()
    .references(() => qrCredentials.id, { onDelete: "cascade" }),
  actorUserId: uuid("actor_user_id").references(() => users.id, {
    onDelete: "set null",
  }),
  eventType: varchar("event_type", { length: 64 }).notNull(), // 'ISSUED', 'SCANNED', 'CHECKED_IN', 'DUPLICATE_ATTEMPT', 'REVOKED', 'REISSUED'
  details: jsonb("details"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

// ============================================================================
// 6. FINANCE, PAYMENTS & COUPONS
// ============================================================================

export const couponCodes = pgTable(
  "coupon_codes",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    editionId: uuid("edition_id")
      .notNull()
      .references(() => festivalEditions.id, { onDelete: "cascade" }),
    code: varchar("code", { length: 64 }).notNull(),
    description: text("description"),
    discountType: discountTypeEnum("discount_type").notNull(),
    discountValue: integer("discount_value").notNull(), // % or INR
    eligibleCategories: jsonb("eligible_categories").$type<string[]>().default([]),
    validFrom: timestamp("valid_from", { withTimezone: true }),
    validUntil: timestamp("valid_until", { withTimezone: true }),
    maxUses: integer("max_uses"),
    usedCount: integer("used_count").default(0).notNull(),
    maxUsesPerUser: integer("max_uses_per_user").default(1).notNull(),
    isActive: boolean("is_active").default(true).notNull(),
    createdBy: uuid("created_by").references(() => users.id, { onDelete: "set null" }),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("coupon_codes_edition_code_unique").on(t.editionId, t.code)]
);

export const couponRedemptions = pgTable(
  "coupon_redemptions",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    couponId: uuid("coupon_id")
      .notNull()
      .references(() => couponCodes.id, { onDelete: "cascade" }),
    registrationId: uuid("registration_id")
      .notNull()
      .references(() => registrations.id, { onDelete: "cascade" }),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    discountAppliedInr: integer("discount_applied_inr").notNull(),
    redeemedAt: timestamp("redeemed_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [index("coupon_redemptions_coupon_idx").on(t.couponId)]
);

export const paymentOrders = pgTable(
  "payment_orders",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    registrationId: uuid("registration_id")
      .notNull()
      .references(() => registrations.id, { onDelete: "cascade" }),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    provider: varchar("provider", { length: 50 }).default("RAZORPAY").notNull(),
    providerOrderId: varchar("provider_order_id", { length: 120 }).notNull(),
    amountInr: integer("amount_inr").notNull(),
    discountInr: integer("discount_inr").default(0).notNull(),
    netAmountInr: integer("net_amount_inr").notNull(),
    currency: varchar("currency", { length: 10 }).default("INR").notNull(),
    status: paymentStatusEnum("status").default("PENDING").notNull(),
    receiptNumber: varchar("receipt_number", { length: 80 }),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("payment_orders_provider_id_unique").on(t.providerOrderId)]
);

export const paymentTransactions = pgTable(
  "payment_transactions",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    orderId: uuid("order_id")
      .notNull()
      .references(() => paymentOrders.id, { onDelete: "cascade" }),
    providerPaymentId: varchar("provider_payment_id", { length: 120 }).notNull(),
    webhookEventId: varchar("webhook_event_id", { length: 120 }),
    status: paymentStatusEnum("status").notNull(),
    amountInr: integer("amount_inr").notNull(),
    rawPayload: jsonb("raw_payload"),
    verifiedAt: timestamp("verified_at", { withTimezone: true }),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [
    uniqueIndex("payment_tx_provider_id_unique").on(t.providerPaymentId),
  ]
);

export const refunds = pgTable("refunds", {
  id: uuid("id").defaultRandom().primaryKey(),
  transactionId: uuid("transaction_id")
    .notNull()
    .references(() => paymentTransactions.id, { onDelete: "cascade" }),
  providerRefundId: varchar("provider_refund_id", { length: 120 }),
  amountInr: integer("amount_inr").notNull(),
  reason: text("reason"),
  status: varchar("status", { length: 40 }).default("INITIATED").notNull(),
  initiatedBy: uuid("initiated_by").references(() => users.id, {
    onDelete: "set null",
  }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

// ============================================================================
// 7. NOTIFICATIONS
// ============================================================================

export const notificationTemplates = pgTable(
  "notification_templates",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    code: varchar("code", { length: 80 }).notNull(), // 'REGISTRATION_CONFIRMED', 'VOLUNTEER_APPROVED', 'SLI_ASSIGNED', 'QR_PASS_ISSUED'
    subject: varchar("subject", { length: 250 }).notNull(),
    bodyHtml: text("body_html").notNull(),
    bodyText: text("body_text").notNull(),
    isActive: boolean("is_active").default(true).notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("notification_templates_code_unique").on(t.code)]
);

export const notificationJobs = pgTable("notification_jobs", {
  id: uuid("id").defaultRandom().primaryKey(),
  templateCode: varchar("template_code", { length: 80 }).notNull(),
  recipientUserId: uuid("recipient_user_id").references(() => users.id, {
    onDelete: "set null",
  }),
  recipientEmail: varchar("recipient_email", { length: 255 }).notNull(),
  subject: varchar("subject", { length: 250 }).notNull(),
  payload: jsonb("payload"),
  status: notificationStatusEnum("status").default("QUEUED").notNull(),
  attempts: integer("attempts").default(0).notNull(),
  maxAttempts: integer("max_attempts").default(3).notNull(),
  nextRetryAt: timestamp("next_retry_at", { withTimezone: true }),
  lastError: text("last_error"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

export const notificationDeliveryLogs = pgTable("notification_delivery_logs", {
  id: uuid("id").defaultRandom().primaryKey(),
  jobId: uuid("job_id")
    .notNull()
    .references(() => notificationJobs.id, { onDelete: "cascade" }),
  provider: varchar("provider", { length: 64 }).notNull(),
  providerMessageId: varchar("provider_message_id", { length: 200 }),
  status: notificationStatusEnum("status").notNull(),
  responseDetails: jsonb("response_details"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

// ============================================================================
// 8. CMS & CONTENT MANAGEMENT
// ============================================================================

export const cmsPages = pgTable(
  "cms_pages",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    editionId: uuid("edition_id")
      .notNull()
      .references(() => festivalEditions.id, { onDelete: "cascade" }),
    slug: varchar("slug", { length: 120 }).notNull(),
    title: varchar("title", { length: 200 }).notNull(),
    metaDescription: text("meta_description"),
    status: publishStatusEnum("status").default("PUBLISHED").notNull(),
    updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("cms_pages_edition_slug_unique").on(t.editionId, t.slug)]
);

export const cmsSections = pgTable(
  "cms_sections",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    pageId: uuid("page_id")
      .notNull()
      .references(() => cmsPages.id, { onDelete: "cascade" }),
    sectionKey: varchar("section_key", { length: 100 }).notNull(), // 'hero', 'about', 'viksit-bharat', 'highlights', 'accessibility', 'contact'
    title: varchar("title", { length: 250 }),
    subtitle: text("subtitle"),
    body: text("body"),
    data: jsonb("data"),
    displayOrder: integer("display_order").default(0).notNull(),
    status: publishStatusEnum("status").default("PUBLISHED").notNull(),
    updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("cms_sections_page_key_unique").on(t.pageId, t.sectionKey)]
);

export const mediaAssets = pgTable("media_assets", {
  id: uuid("id").defaultRandom().primaryKey(),
  editionId: uuid("edition_id").references(() => festivalEditions.id, {
    onDelete: "set null",
  }),
  fileName: varchar("file_name", { length: 255 }).notNull(),
  url: text("url").notNull(),
  mimeType: varchar("mime_type", { length: 100 }).notNull(),
  sizeBytes: integer("size_bytes"),
  altText: text("alt_text").notNull(), // Mandatory for WCAG 2.2 AA
  category: varchar("category", { length: 64 }).default("GALLERY").notNull(),
  sourceAttribution: text("source_attribution"),
  licenseInfo: text("license_info"),
  uploadedBy: uuid("uploaded_by").references(() => users.id, { onDelete: "set null" }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const contentRevisions = pgTable("content_revisions", {
  id: uuid("id").defaultRandom().primaryKey(),
  entityType: varchar("entity_type", { length: 64 }).notNull(), // 'cms_section', 'programme_item', 'partner'
  entityId: uuid("entity_id").notNull(),
  versionNumber: integer("version_number").notNull(),
  snapshot: jsonb("snapshot").notNull(),
  changedBy: uuid("changed_by").references(() => users.id, { onDelete: "set null" }),
  changeSummary: text("change_summary"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const announcements = pgTable("announcements", {
  id: uuid("id").defaultRandom().primaryKey(),
  editionId: uuid("edition_id")
    .notNull()
    .references(() => festivalEditions.id, { onDelete: "cascade" }),
  title: varchar("title", { length: 200 }).notNull(),
  message: text("message").notNull(),
  severity: varchar("severity", { length: 32 }).default("INFO").notNull(),
  status: publishStatusEnum("status").default("PUBLISHED").notNull(),
  publishedAt: timestamp("published_at", { withTimezone: true }).defaultNow(),
  expiresAt: timestamp("expires_at", { withTimezone: true }),
  createdBy: uuid("created_by").references(() => users.id, { onDelete: "set null" }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const faqItems = pgTable("faq_items", {
  id: uuid("id").defaultRandom().primaryKey(),
  editionId: uuid("edition_id")
    .notNull()
    .references(() => festivalEditions.id, { onDelete: "cascade" }),
  category: varchar("category", { length: 80 }).default("General").notNull(),
  question: text("question").notNull(),
  answer: text("answer").notNull(),
  displayOrder: integer("display_order").default(0).notNull(),
  status: publishStatusEnum("status").default("PUBLISHED").notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
});

// ============================================================================
// 9. SECURITY, PRIVACY & OPERATIONS
// ============================================================================

export const auditLogs = pgTable(
  "audit_logs",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    actorUserId: uuid("actor_user_id").references(() => users.id, {
      onDelete: "set null",
    }),
    action: varchar("action", { length: 120 }).notNull(),
    resourceType: varchar("resource_type", { length: 80 }).notNull(),
    resourceId: varchar("resource_id", { length: 120 }),
    ipAddress: varchar("ip_address", { length: 64 }),
    userAgent: text("user_agent"),
    metadata: jsonb("metadata"),
    createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [
    index("audit_logs_actor_idx").on(t.actorUserId),
    index("audit_logs_action_idx").on(t.action),
    index("audit_logs_created_idx").on(t.createdAt),
  ]
);

export const adminActions = pgTable("admin_actions", {
  id: uuid("id").defaultRandom().primaryKey(),
  adminUserId: uuid("admin_user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  targetUserId: uuid("target_user_id").references(() => users.id, {
    onDelete: "set null",
  }),
  actionType: varchar("action_type", { length: 100 }).notNull(),
  reason: text("reason"),
  beforeState: jsonb("before_state"),
  afterState: jsonb("after_state"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const systemSettings = pgTable(
  "system_settings",
  {
    id: uuid("id").defaultRandom().primaryKey(),
    key: varchar("key", { length: 120 }).notNull(),
    value: jsonb("value").notNull(),
    description: text("description"),
    updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
    updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [uniqueIndex("system_settings_key_unique").on(t.key)]
);

export const privacyConsentRecords = pgTable("privacy_consent_records", {
  id: uuid("id").defaultRandom().primaryKey(),
  userId: uuid("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  registrationId: uuid("registration_id").references(() => registrations.id, {
    onDelete: "set null",
  }),
  consentType: varchar("consent_type", { length: 80 }).notNull(), // 'ZERO_TOLERANCE_HARASSMENT', 'DATA_PROCESSING_DPDP', 'PHOTOGRAPHY_MEDIA'
  consentVersion: varchar("consent_version", { length: 40 }).default("1.0").notNull(),
  granted: boolean("granted").notNull(),
  ipAddress: varchar("ip_address", { length: 64 }),
  recordedAt: timestamp("recorded_at", { withTimezone: true }).defaultNow().notNull(),
  withdrawnAt: timestamp("withdrawn_at", { withTimezone: true }),
});

export const dataRetentionRequests = pgTable("data_retention_requests", {
  id: uuid("id").defaultRandom().primaryKey(),
  userId: uuid("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  requestType: varchar("request_type", { length: 60 }).notNull(), // 'EXPORT', 'CORRECTION', 'DELETION', 'CONSENT_WITHDRAWAL'
  status: varchar("status", { length: 40 }).default("OPEN").notNull(),
  details: text("details"),
  handledBy: uuid("handled_by").references(() => users.id, { onDelete: "set null" }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  resolvedAt: timestamp("resolved_at", { withTimezone: true }),
});

