CREATE TYPE "public"."attendance_policy" AS ENUM('ONCE_PER_FESTIVAL', 'ONCE_PER_DAY', 'PER_SESSION', 'RESTRICTED_AREA');--> statement-breakpoint
CREATE TYPE "public"."credential_status" AS ENUM('ACTIVE', 'REVOKED', 'EXPIRED', 'SUSPENDED');--> statement-breakpoint
CREATE TYPE "public"."discount_type" AS ENUM('PERCENTAGE', 'FIXED_AMOUNT', 'FULL_WAIVER');--> statement-breakpoint
CREATE TYPE "public"."notification_status" AS ENUM('QUEUED', 'PROCESSING', 'SENT', 'DELIVERED', 'FAILED', 'BOUNCED');--> statement-breakpoint
CREATE TYPE "public"."payment_status" AS ENUM('NOT_REQUIRED', 'PENDING', 'AUTHORIZED', 'CAPTURED', 'FAILED', 'REFUNDED', 'PARTIALLY_REFUNDED');--> statement-breakpoint
CREATE TYPE "public"."publish_status" AS ENUM('DRAFT', 'PUBLISHED', 'ARCHIVED');--> statement-breakpoint
CREATE TYPE "public"."registration_status" AS ENUM('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'ADDITIONAL_INFO_REQUIRED', 'CONFIRMED', 'APPROVED', 'WAITLISTED', 'REJECTED', 'WITHDRAWN', 'CANCELLED');--> statement-breakpoint
CREATE TYPE "public"."role_code" AS ENUM('SUPERADMIN', 'ADMIN', 'RGST', 'PARTICIPANT');--> statement-breakpoint
CREATE TABLE "accommodation_assignments" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"request_id" uuid NOT NULL,
	"assigned_to_user_id" uuid,
	"coordinator_notes" text,
	"status" varchar(40) DEFAULT 'ASSIGNED' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "accommodation_requests" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"registration_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"selected_types" jsonb DEFAULT '[]'::jsonb,
	"free_text_details" text,
	"status" varchar(40) DEFAULT 'REQUESTED' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "accommodation_types" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"code" varchar(64) NOT NULL,
	"label" varchar(180) NOT NULL,
	"description" text,
	"is_active" boolean DEFAULT true NOT NULL,
	"display_order" integer DEFAULT 0 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "admin_actions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"admin_user_id" uuid NOT NULL,
	"target_user_id" uuid,
	"action_type" varchar(100) NOT NULL,
	"reason" text,
	"before_state" jsonb,
	"after_state" jsonb,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "announcements" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"title" varchar(200) NOT NULL,
	"message" text NOT NULL,
	"severity" varchar(32) DEFAULT 'INFO' NOT NULL,
	"status" "publish_status" DEFAULT 'PUBLISHED' NOT NULL,
	"published_at" timestamp with time zone DEFAULT now(),
	"expires_at" timestamp with time zone,
	"created_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "attendance_entitlements" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"credential_id" uuid NOT NULL,
	"policy_type" "attendance_policy" NOT NULL,
	"scope_key" varchar(100) NOT NULL,
	"max_uses" integer DEFAULT 1 NOT NULL,
	"used_count" integer DEFAULT 0 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "audit_logs" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"actor_user_id" uuid,
	"action" varchar(120) NOT NULL,
	"resource_type" varchar(80) NOT NULL,
	"resource_id" varchar(120),
	"ip_address" varchar(64),
	"user_agent" text,
	"metadata" jsonb,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "auth_accounts" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"provider" varchar(64) NOT NULL,
	"provider_account_id" varchar(255) NOT NULL,
	"token_hash" text,
	"expires_at" timestamp with time zone,
	"metadata" jsonb,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "checkin_stations" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"venue_id" uuid,
	"name" varchar(150) NOT NULL,
	"gate_code" varchar(50) NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "checkins" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"credential_id" uuid NOT NULL,
	"registration_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"operator_user_id" uuid NOT NULL,
	"station_id" uuid,
	"venue_label" varchar(150) NOT NULL,
	"entitlement_scope" varchar(100) NOT NULL,
	"checkin_date_ist" varchar(20) NOT NULL,
	"method" varchar(40) DEFAULT 'QR_CAMERA' NOT NULL,
	"result" varchar(40) DEFAULT 'CHECKED_IN' NOT NULL,
	"checked_in_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "cms_pages" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"slug" varchar(120) NOT NULL,
	"title" varchar(200) NOT NULL,
	"meta_description" text,
	"status" "publish_status" DEFAULT 'PUBLISHED' NOT NULL,
	"updated_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "cms_sections" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"page_id" uuid NOT NULL,
	"section_key" varchar(100) NOT NULL,
	"title" varchar(250),
	"subtitle" text,
	"body" text,
	"data" jsonb,
	"display_order" integer DEFAULT 0 NOT NULL,
	"status" "publish_status" DEFAULT 'PUBLISHED' NOT NULL,
	"updated_by" uuid,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "content_revisions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"entity_type" varchar(64) NOT NULL,
	"entity_id" uuid NOT NULL,
	"version_number" integer NOT NULL,
	"snapshot" jsonb NOT NULL,
	"changed_by" uuid,
	"change_summary" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "coupon_codes" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"code" varchar(64) NOT NULL,
	"description" text,
	"discount_type" "discount_type" NOT NULL,
	"discount_value" integer NOT NULL,
	"eligible_categories" jsonb DEFAULT '[]'::jsonb,
	"valid_from" timestamp with time zone,
	"valid_until" timestamp with time zone,
	"max_uses" integer,
	"used_count" integer DEFAULT 0 NOT NULL,
	"max_uses_per_user" integer DEFAULT 1 NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "coupon_redemptions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"coupon_id" uuid NOT NULL,
	"registration_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"discount_applied_inr" integer NOT NULL,
	"redeemed_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "credential_events" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"credential_id" uuid NOT NULL,
	"actor_user_id" uuid,
	"event_type" varchar(64) NOT NULL,
	"details" jsonb,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "data_retention_requests" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"request_type" varchar(60) NOT NULL,
	"status" varchar(40) DEFAULT 'OPEN' NOT NULL,
	"details" text,
	"handled_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"resolved_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "delegate_details" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"registration_id" uuid NOT NULL,
	"gender" varchar(50),
	"age" integer,
	"city_state" varchar(150),
	"para_sports_interests" jsonb DEFAULT '[]'::jsonb,
	"outstation_travel_required" boolean DEFAULT false,
	"accommodation_needed" boolean DEFAULT false,
	"food_preference" varchar(80),
	"emergency_contact_name" varchar(150),
	"emergency_contact_phone" varchar(40),
	"guardian_name" varchar(150),
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "disability_categories" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"code" varchar(64) NOT NULL,
	"name" varchar(180) NOT NULL,
	"rpwd_group" varchar(100) NOT NULL,
	"description" text NOT NULL,
	"accommodation_examples" jsonb DEFAULT '[]'::jsonb,
	"display_order" integer DEFAULT 0 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "faq_items" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"category" varchar(80) DEFAULT 'General' NOT NULL,
	"question" text NOT NULL,
	"answer" text NOT NULL,
	"display_order" integer DEFAULT 0 NOT NULL,
	"status" "publish_status" DEFAULT 'PUBLISHED' NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "festival_editions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"slug" varchar(80) NOT NULL,
	"name" varchar(200) NOT NULL,
	"tagline" varchar(300) NOT NULL,
	"city" varchar(100) NOT NULL,
	"state" varchar(100) NOT NULL,
	"start_date" timestamp with time zone NOT NULL,
	"end_date" timestamp with time zone NOT NULL,
	"registration_opens_at" timestamp with time zone,
	"registration_closes_at" timestamp with time zone,
	"attendance_policy" "attendance_policy" DEFAULT 'ONCE_PER_DAY' NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "festival_sessions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"venue_id" uuid,
	"title" varchar(250) NOT NULL,
	"track" varchar(100) NOT NULL,
	"day_number" integer NOT NULL,
	"starts_at" timestamp with time zone NOT NULL,
	"ends_at" timestamp with time zone NOT NULL,
	"isl_required" boolean DEFAULT true NOT NULL,
	"captioning_available" boolean DEFAULT true NOT NULL,
	"wheelchair_accessible" boolean DEFAULT true NOT NULL,
	"status" "publish_status" DEFAULT 'PUBLISHED' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "media_assets" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid,
	"file_name" varchar(255) NOT NULL,
	"url" text NOT NULL,
	"mime_type" varchar(100) NOT NULL,
	"size_bytes" integer,
	"alt_text" text NOT NULL,
	"category" varchar(64) DEFAULT 'GALLERY' NOT NULL,
	"source_attribution" text,
	"license_info" text,
	"uploaded_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "notification_delivery_logs" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"job_id" uuid NOT NULL,
	"provider" varchar(64) NOT NULL,
	"provider_message_id" varchar(200),
	"status" "notification_status" NOT NULL,
	"response_details" jsonb,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "notification_jobs" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"template_code" varchar(80) NOT NULL,
	"recipient_user_id" uuid,
	"recipient_email" varchar(255) NOT NULL,
	"subject" varchar(250) NOT NULL,
	"payload" jsonb,
	"status" "notification_status" DEFAULT 'QUEUED' NOT NULL,
	"attempts" integer DEFAULT 0 NOT NULL,
	"max_attempts" integer DEFAULT 3 NOT NULL,
	"next_retry_at" timestamp with time zone,
	"last_error" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "notification_templates" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"code" varchar(80) NOT NULL,
	"subject" varchar(250) NOT NULL,
	"body_html" text NOT NULL,
	"body_text" text NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "organizations" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"name" varchar(250) NOT NULL,
	"short_name" varchar(100),
	"website_url" text,
	"logo_url" text,
	"logo_alt_text" text,
	"description" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "participant_affiliations" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"occupation" varchar(120),
	"organization_name" varchar(200),
	"designation" varchar(150),
	"city" varchar(100),
	"state" varchar(100),
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "participant_disability_disclosures" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"registration_id" uuid NOT NULL,
	"disability_category_id" uuid,
	"self_description" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "partners" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"organization_id" uuid NOT NULL,
	"category" varchar(100) NOT NULL,
	"consent_verified" boolean DEFAULT false NOT NULL,
	"display_order" integer DEFAULT 0 NOT NULL,
	"status" "publish_status" DEFAULT 'DRAFT' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "payment_orders" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"registration_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"provider" varchar(50) DEFAULT 'RAZORPAY' NOT NULL,
	"provider_order_id" varchar(120) NOT NULL,
	"amount_inr" integer NOT NULL,
	"discount_inr" integer DEFAULT 0 NOT NULL,
	"net_amount_inr" integer NOT NULL,
	"currency" varchar(10) DEFAULT 'INR' NOT NULL,
	"status" "payment_status" DEFAULT 'PENDING' NOT NULL,
	"receipt_number" varchar(80),
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "payment_transactions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"order_id" uuid NOT NULL,
	"provider_payment_id" varchar(120) NOT NULL,
	"webhook_event_id" varchar(120),
	"status" "payment_status" NOT NULL,
	"amount_inr" integer NOT NULL,
	"raw_payload" jsonb,
	"verified_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "permissions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"code" varchar(100) NOT NULL,
	"description" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "privacy_consent_records" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"registration_id" uuid,
	"consent_type" varchar(80) NOT NULL,
	"consent_version" varchar(40) DEFAULT '1.0' NOT NULL,
	"granted" boolean NOT NULL,
	"ip_address" varchar(64),
	"recorded_at" timestamp with time zone DEFAULT now() NOT NULL,
	"withdrawn_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "programme_items" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"session_id" uuid,
	"day_number" integer NOT NULL,
	"time_slot" varchar(80) NOT NULL,
	"title" varchar(250) NOT NULL,
	"description" text,
	"category" varchar(100) NOT NULL,
	"venue_name" varchar(200),
	"speakers" jsonb DEFAULT '[]'::jsonb,
	"accessibility_services" jsonb DEFAULT '[]'::jsonb,
	"display_order" integer DEFAULT 0 NOT NULL,
	"status" "publish_status" DEFAULT 'PUBLISHED' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "qr_credentials" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"credential_code" varchar(40) NOT NULL,
	"token_hash" varchar(128) NOT NULL,
	"registration_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"edition_id" uuid NOT NULL,
	"category_code" varchar(64) NOT NULL,
	"status" "credential_status" DEFAULT 'ACTIVE' NOT NULL,
	"issued_at" timestamp with time zone DEFAULT now() NOT NULL,
	"revoked_at" timestamp with time zone,
	"revoked_by" uuid,
	"revocation_reason" text,
	"replaced_by_credential_id" uuid
);
--> statement-breakpoint
CREATE TABLE "refunds" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"transaction_id" uuid NOT NULL,
	"provider_refund_id" varchar(120),
	"amount_inr" integer NOT NULL,
	"reason" text,
	"status" varchar(40) DEFAULT 'INITIATED' NOT NULL,
	"initiated_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "registration_categories" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"code" varchar(64) NOT NULL,
	"name" varchar(150) NOT NULL,
	"description" text,
	"requires_approval" boolean DEFAULT false NOT NULL,
	"requires_payment" boolean DEFAULT false NOT NULL,
	"fee_amount_inr" integer DEFAULT 0 NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"display_order" integer DEFAULT 0 NOT NULL,
	"opens_at" timestamp with time zone,
	"closes_at" timestamp with time zone,
	"confirmation_message" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "registration_form_versions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"category_code" varchar(64) NOT NULL,
	"version_number" integer NOT NULL,
	"schema_definition" jsonb NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "registration_responses" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"registration_id" uuid NOT NULL,
	"form_version_id" uuid,
	"answers" jsonb NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "registrations" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"registration_code" varchar(40) NOT NULL,
	"edition_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"category_id" uuid NOT NULL,
	"category_code" varchar(64) NOT NULL,
	"form_version_id" uuid,
	"status" "registration_status" DEFAULT 'SUBMITTED' NOT NULL,
	"payment_status" "payment_status" DEFAULT 'NOT_REQUIRED' NOT NULL,
	"full_name" varchar(200) NOT NULL,
	"email" varchar(255) NOT NULL,
	"phone" varchar(40) NOT NULL,
	"country" varchar(100) DEFAULT 'India' NOT NULL,
	"occupation" varchar(120),
	"organization_name" varchar(200),
	"designation" varchar(150),
	"is_person_with_disability" varchar(32) DEFAULT 'PREFER_NOT_TO_SAY' NOT NULL,
	"attending_days" jsonb DEFAULT '[]'::jsonb,
	"interested_tracks" jsonb DEFAULT '[]'::jsonb,
	"reviewer_id" uuid,
	"review_notes" text,
	"reviewed_at" timestamp with time zone,
	"submitted_at" timestamp with time zone DEFAULT now(),
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	"deleted_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "role_permissions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"role_id" uuid NOT NULL,
	"permission_id" uuid NOT NULL
);
--> statement-breakpoint
CREATE TABLE "roles" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"code" "role_code" NOT NULL,
	"name" varchar(100) NOT NULL,
	"description" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "sessions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"token_hash" varchar(128) NOT NULL,
	"ip_address" varchar(64),
	"user_agent" text,
	"mfa_verified" boolean DEFAULT false NOT NULL,
	"expires_at" timestamp with time zone NOT NULL,
	"revoked_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "sli_applications" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"registration_id" uuid NOT NULL,
	"city_state" varchar(150),
	"based_in_gujarat" boolean DEFAULT true,
	"crr_number" varchar(80),
	"qualifications" text,
	"training_institute" varchar(200),
	"certification_level" varchar(120),
	"years_of_experience" integer,
	"professional_affiliation" varchar(200),
	"areas_of_expertise" jsonb DEFAULT '[]'::jsonb,
	"interpretation_preferences" jsonb DEFAULT '[]'::jsonb,
	"availability_days" jsonb DEFAULT '[]'::jsonb,
	"video_demo_url" text,
	"document_url" text,
	"honorarium_or_travel_notes" text,
	"assigned_sessions" jsonb DEFAULT '[]'::jsonb,
	"assigned_venue" varchar(200),
	"duty_times" varchar(150),
	"assignment_notes" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "system_settings" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"key" varchar(120) NOT NULL,
	"value" jsonb NOT NULL,
	"description" text,
	"updated_by" uuid,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "user_roles" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"role_id" uuid NOT NULL,
	"custom_permissions" jsonb,
	"assigned_by" uuid,
	"assigned_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "users" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"email" varchar(255) NOT NULL,
	"email_verified_at" timestamp with time zone,
	"password_hash" text,
	"full_name" varchar(200) NOT NULL,
	"phone" varchar(40),
	"country" varchar(100) DEFAULT 'India' NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"mfa_enabled" boolean DEFAULT false NOT NULL,
	"mfa_secret_encrypted" text,
	"failed_login_attempts" integer DEFAULT 0 NOT NULL,
	"locked_until" timestamp with time zone,
	"last_login_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	"deleted_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "venues" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"edition_id" uuid NOT NULL,
	"name" varchar(200) NOT NULL,
	"address" text,
	"confirmation_status" varchar(50) DEFAULT 'PENDING' NOT NULL,
	"accessibility_features" jsonb DEFAULT '[]'::jsonb,
	"capacity" integer,
	"map_url" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "volunteer_applications" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"registration_id" uuid NOT NULL,
	"age" integer,
	"gender" varchar(50),
	"city_state" varchar(150),
	"institution_name" varchar(200),
	"is_msu_student_or_alumni" boolean DEFAULT false,
	"crr_number" varchar(80),
	"preferred_roles" jsonb DEFAULT '[]'::jsonb,
	"availability_days" jsonb DEFAULT '[]'::jsonb,
	"languages_spoken" jsonb DEFAULT '[]'::jsonb,
	"relevant_experience" text,
	"emergency_contact_name" varchar(150),
	"emergency_contact_phone" varchar(40),
	"document_url" text,
	"assigned_department" varchar(120),
	"assigned_responsibility" text,
	"assigned_supervisor" varchar(150),
	"assigned_venue" varchar(200),
	"assigned_shift" varchar(100),
	"reporting_time" varchar(80),
	"assignment_notes" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
ALTER TABLE "accommodation_assignments" ADD CONSTRAINT "accommodation_assignments_request_id_accommodation_requests_id_fk" FOREIGN KEY ("request_id") REFERENCES "public"."accommodation_requests"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "accommodation_assignments" ADD CONSTRAINT "accommodation_assignments_assigned_to_user_id_users_id_fk" FOREIGN KEY ("assigned_to_user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "accommodation_requests" ADD CONSTRAINT "accommodation_requests_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "accommodation_requests" ADD CONSTRAINT "accommodation_requests_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "admin_actions" ADD CONSTRAINT "admin_actions_admin_user_id_users_id_fk" FOREIGN KEY ("admin_user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "admin_actions" ADD CONSTRAINT "admin_actions_target_user_id_users_id_fk" FOREIGN KEY ("target_user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "announcements" ADD CONSTRAINT "announcements_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "announcements" ADD CONSTRAINT "announcements_created_by_users_id_fk" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "attendance_entitlements" ADD CONSTRAINT "attendance_entitlements_credential_id_qr_credentials_id_fk" FOREIGN KEY ("credential_id") REFERENCES "public"."qr_credentials"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "audit_logs" ADD CONSTRAINT "audit_logs_actor_user_id_users_id_fk" FOREIGN KEY ("actor_user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "auth_accounts" ADD CONSTRAINT "auth_accounts_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "checkin_stations" ADD CONSTRAINT "checkin_stations_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "checkin_stations" ADD CONSTRAINT "checkin_stations_venue_id_venues_id_fk" FOREIGN KEY ("venue_id") REFERENCES "public"."venues"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "checkins" ADD CONSTRAINT "checkins_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "checkins" ADD CONSTRAINT "checkins_credential_id_qr_credentials_id_fk" FOREIGN KEY ("credential_id") REFERENCES "public"."qr_credentials"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "checkins" ADD CONSTRAINT "checkins_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "checkins" ADD CONSTRAINT "checkins_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "checkins" ADD CONSTRAINT "checkins_operator_user_id_users_id_fk" FOREIGN KEY ("operator_user_id") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "checkins" ADD CONSTRAINT "checkins_station_id_checkin_stations_id_fk" FOREIGN KEY ("station_id") REFERENCES "public"."checkin_stations"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "cms_pages" ADD CONSTRAINT "cms_pages_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "cms_pages" ADD CONSTRAINT "cms_pages_updated_by_users_id_fk" FOREIGN KEY ("updated_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "cms_sections" ADD CONSTRAINT "cms_sections_page_id_cms_pages_id_fk" FOREIGN KEY ("page_id") REFERENCES "public"."cms_pages"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "cms_sections" ADD CONSTRAINT "cms_sections_updated_by_users_id_fk" FOREIGN KEY ("updated_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "content_revisions" ADD CONSTRAINT "content_revisions_changed_by_users_id_fk" FOREIGN KEY ("changed_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "coupon_codes" ADD CONSTRAINT "coupon_codes_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "coupon_codes" ADD CONSTRAINT "coupon_codes_created_by_users_id_fk" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "coupon_redemptions" ADD CONSTRAINT "coupon_redemptions_coupon_id_coupon_codes_id_fk" FOREIGN KEY ("coupon_id") REFERENCES "public"."coupon_codes"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "coupon_redemptions" ADD CONSTRAINT "coupon_redemptions_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "coupon_redemptions" ADD CONSTRAINT "coupon_redemptions_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "credential_events" ADD CONSTRAINT "credential_events_credential_id_qr_credentials_id_fk" FOREIGN KEY ("credential_id") REFERENCES "public"."qr_credentials"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "credential_events" ADD CONSTRAINT "credential_events_actor_user_id_users_id_fk" FOREIGN KEY ("actor_user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "data_retention_requests" ADD CONSTRAINT "data_retention_requests_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "data_retention_requests" ADD CONSTRAINT "data_retention_requests_handled_by_users_id_fk" FOREIGN KEY ("handled_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "delegate_details" ADD CONSTRAINT "delegate_details_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "faq_items" ADD CONSTRAINT "faq_items_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "festival_sessions" ADD CONSTRAINT "festival_sessions_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "festival_sessions" ADD CONSTRAINT "festival_sessions_venue_id_venues_id_fk" FOREIGN KEY ("venue_id") REFERENCES "public"."venues"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "media_assets" ADD CONSTRAINT "media_assets_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "media_assets" ADD CONSTRAINT "media_assets_uploaded_by_users_id_fk" FOREIGN KEY ("uploaded_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "notification_delivery_logs" ADD CONSTRAINT "notification_delivery_logs_job_id_notification_jobs_id_fk" FOREIGN KEY ("job_id") REFERENCES "public"."notification_jobs"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "notification_jobs" ADD CONSTRAINT "notification_jobs_recipient_user_id_users_id_fk" FOREIGN KEY ("recipient_user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "participant_affiliations" ADD CONSTRAINT "participant_affiliations_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "participant_disability_disclosures" ADD CONSTRAINT "participant_disability_disclosures_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "participant_disability_disclosures" ADD CONSTRAINT "participant_disability_disclosures_disability_category_id_disability_categories_id_fk" FOREIGN KEY ("disability_category_id") REFERENCES "public"."disability_categories"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "partners" ADD CONSTRAINT "partners_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "partners" ADD CONSTRAINT "partners_organization_id_organizations_id_fk" FOREIGN KEY ("organization_id") REFERENCES "public"."organizations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "payment_orders" ADD CONSTRAINT "payment_orders_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "payment_orders" ADD CONSTRAINT "payment_orders_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "payment_transactions" ADD CONSTRAINT "payment_transactions_order_id_payment_orders_id_fk" FOREIGN KEY ("order_id") REFERENCES "public"."payment_orders"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "privacy_consent_records" ADD CONSTRAINT "privacy_consent_records_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "privacy_consent_records" ADD CONSTRAINT "privacy_consent_records_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "programme_items" ADD CONSTRAINT "programme_items_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "programme_items" ADD CONSTRAINT "programme_items_session_id_festival_sessions_id_fk" FOREIGN KEY ("session_id") REFERENCES "public"."festival_sessions"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "qr_credentials" ADD CONSTRAINT "qr_credentials_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "qr_credentials" ADD CONSTRAINT "qr_credentials_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "qr_credentials" ADD CONSTRAINT "qr_credentials_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "qr_credentials" ADD CONSTRAINT "qr_credentials_revoked_by_users_id_fk" FOREIGN KEY ("revoked_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "refunds" ADD CONSTRAINT "refunds_transaction_id_payment_transactions_id_fk" FOREIGN KEY ("transaction_id") REFERENCES "public"."payment_transactions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "refunds" ADD CONSTRAINT "refunds_initiated_by_users_id_fk" FOREIGN KEY ("initiated_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registration_categories" ADD CONSTRAINT "registration_categories_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registration_form_versions" ADD CONSTRAINT "registration_form_versions_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registration_form_versions" ADD CONSTRAINT "registration_form_versions_created_by_users_id_fk" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registration_responses" ADD CONSTRAINT "registration_responses_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registration_responses" ADD CONSTRAINT "registration_responses_form_version_id_registration_form_versions_id_fk" FOREIGN KEY ("form_version_id") REFERENCES "public"."registration_form_versions"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registrations" ADD CONSTRAINT "registrations_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registrations" ADD CONSTRAINT "registrations_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registrations" ADD CONSTRAINT "registrations_category_id_registration_categories_id_fk" FOREIGN KEY ("category_id") REFERENCES "public"."registration_categories"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registrations" ADD CONSTRAINT "registrations_form_version_id_registration_form_versions_id_fk" FOREIGN KEY ("form_version_id") REFERENCES "public"."registration_form_versions"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "registrations" ADD CONSTRAINT "registrations_reviewer_id_users_id_fk" FOREIGN KEY ("reviewer_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "role_permissions" ADD CONSTRAINT "role_permissions_role_id_roles_id_fk" FOREIGN KEY ("role_id") REFERENCES "public"."roles"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "role_permissions" ADD CONSTRAINT "role_permissions_permission_id_permissions_id_fk" FOREIGN KEY ("permission_id") REFERENCES "public"."permissions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sessions" ADD CONSTRAINT "sessions_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sli_applications" ADD CONSTRAINT "sli_applications_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "system_settings" ADD CONSTRAINT "system_settings_updated_by_users_id_fk" FOREIGN KEY ("updated_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "user_roles" ADD CONSTRAINT "user_roles_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "user_roles" ADD CONSTRAINT "user_roles_role_id_roles_id_fk" FOREIGN KEY ("role_id") REFERENCES "public"."roles"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "user_roles" ADD CONSTRAINT "user_roles_assigned_by_users_id_fk" FOREIGN KEY ("assigned_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "venues" ADD CONSTRAINT "venues_edition_id_festival_editions_id_fk" FOREIGN KEY ("edition_id") REFERENCES "public"."festival_editions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "volunteer_applications" ADD CONSTRAINT "volunteer_applications_registration_id_registrations_id_fk" FOREIGN KEY ("registration_id") REFERENCES "public"."registrations"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE INDEX "accomm_req_reg_idx" ON "accommodation_requests" USING btree ("registration_id");--> statement-breakpoint
CREATE UNIQUE INDEX "accommodation_types_code_unique" ON "accommodation_types" USING btree ("code");--> statement-breakpoint
CREATE UNIQUE INDEX "attendance_entitlements_unique" ON "attendance_entitlements" USING btree ("credential_id","scope_key");--> statement-breakpoint
CREATE INDEX "audit_logs_actor_idx" ON "audit_logs" USING btree ("actor_user_id");--> statement-breakpoint
CREATE INDEX "audit_logs_action_idx" ON "audit_logs" USING btree ("action");--> statement-breakpoint
CREATE INDEX "audit_logs_created_idx" ON "audit_logs" USING btree ("created_at");--> statement-breakpoint
CREATE INDEX "auth_accounts_user_idx" ON "auth_accounts" USING btree ("user_id");--> statement-breakpoint
CREATE UNIQUE INDEX "checkins_credential_scope_unique" ON "checkins" USING btree ("credential_id","entitlement_scope");--> statement-breakpoint
CREATE INDEX "checkins_reg_idx" ON "checkins" USING btree ("registration_id");--> statement-breakpoint
CREATE INDEX "checkins_operator_idx" ON "checkins" USING btree ("operator_user_id");--> statement-breakpoint
CREATE UNIQUE INDEX "cms_pages_edition_slug_unique" ON "cms_pages" USING btree ("edition_id","slug");--> statement-breakpoint
CREATE UNIQUE INDEX "cms_sections_page_key_unique" ON "cms_sections" USING btree ("page_id","section_key");--> statement-breakpoint
CREATE UNIQUE INDEX "coupon_codes_edition_code_unique" ON "coupon_codes" USING btree ("edition_id","code");--> statement-breakpoint
CREATE INDEX "coupon_redemptions_coupon_idx" ON "coupon_redemptions" USING btree ("coupon_id");--> statement-breakpoint
CREATE UNIQUE INDEX "disability_categories_code_unique" ON "disability_categories" USING btree ("code");--> statement-breakpoint
CREATE UNIQUE INDEX "festival_editions_slug_unique" ON "festival_editions" USING btree ("slug");--> statement-breakpoint
CREATE UNIQUE INDEX "notification_templates_code_unique" ON "notification_templates" USING btree ("code");--> statement-breakpoint
CREATE INDEX "pdd_reg_idx" ON "participant_disability_disclosures" USING btree ("registration_id");--> statement-breakpoint
CREATE UNIQUE INDEX "payment_orders_provider_id_unique" ON "payment_orders" USING btree ("provider_order_id");--> statement-breakpoint
CREATE UNIQUE INDEX "payment_tx_provider_id_unique" ON "payment_transactions" USING btree ("provider_payment_id");--> statement-breakpoint
CREATE UNIQUE INDEX "permissions_code_unique" ON "permissions" USING btree ("code");--> statement-breakpoint
CREATE UNIQUE INDEX "qr_credentials_code_unique" ON "qr_credentials" USING btree ("credential_code");--> statement-breakpoint
CREATE UNIQUE INDEX "qr_credentials_token_hash_unique" ON "qr_credentials" USING btree ("token_hash");--> statement-breakpoint
CREATE INDEX "qr_credentials_reg_idx" ON "qr_credentials" USING btree ("registration_id");--> statement-breakpoint
CREATE UNIQUE INDEX "reg_cat_edition_code_unique" ON "registration_categories" USING btree ("edition_id","code");--> statement-breakpoint
CREATE UNIQUE INDEX "reg_form_ver_unique" ON "registration_form_versions" USING btree ("edition_id","category_code","version_number");--> statement-breakpoint
CREATE UNIQUE INDEX "registrations_code_unique" ON "registrations" USING btree ("registration_code");--> statement-breakpoint
CREATE INDEX "registrations_user_idx" ON "registrations" USING btree ("user_id");--> statement-breakpoint
CREATE INDEX "registrations_edition_cat_idx" ON "registrations" USING btree ("edition_id","category_code");--> statement-breakpoint
CREATE INDEX "registrations_status_idx" ON "registrations" USING btree ("status");--> statement-breakpoint
CREATE UNIQUE INDEX "role_permissions_unique" ON "role_permissions" USING btree ("role_id","permission_id");--> statement-breakpoint
CREATE UNIQUE INDEX "roles_code_unique" ON "roles" USING btree ("code");--> statement-breakpoint
CREATE UNIQUE INDEX "sessions_token_hash_unique" ON "sessions" USING btree ("token_hash");--> statement-breakpoint
CREATE INDEX "sessions_user_idx" ON "sessions" USING btree ("user_id");--> statement-breakpoint
CREATE UNIQUE INDEX "system_settings_key_unique" ON "system_settings" USING btree ("key");--> statement-breakpoint
CREATE UNIQUE INDEX "user_roles_unique" ON "user_roles" USING btree ("user_id","role_id");--> statement-breakpoint
CREATE UNIQUE INDEX "users_email_unique" ON "users" USING btree ("email");