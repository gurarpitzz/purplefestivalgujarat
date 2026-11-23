import { z } from "zod";

export const internationalPhoneRegex = /^\+?[0-9\s\-()]{7,20}$/;

export const baseRegistrationSchema = z.object({
  fullName: z.string().trim().min(2, "Please enter your full name.").max(200),
  email: z.string().trim().email("Please enter a valid email address.").max(255),
  phone: z
    .string()
    .trim()
    .regex(internationalPhoneRegex, "Please enter a valid phone number (e.g., +91 98250 12345)."),
  country: z.string().trim().min(2).max(100).default("India"),
  password: z.string().min(8, "Password must be at least 8 characters.").optional(),
  categoryCode: z.string().trim().min(2).max(64),
  occupation: z.string().trim().min(2, "Please select your occupation or affiliation.").max(120),
  organizationName: z.string().trim().max(200).optional().or(z.literal("")),
  designation: z.string().trim().max(150).optional().or(z.literal("")),
  isPersonWithDisability: z.enum(["YES", "NO", "PREFER_NOT_TO_SAY"]),
  disabilityCategoryCodes: z.array(z.string()).default([]),
  disabilitySelfDescription: z.string().trim().max(500).optional().or(z.literal("")),
  accommodationTypes: z.array(z.string()).default([]),
  accommodationFreeText: z.string().trim().max(1000).optional().or(z.literal("")),
  attendingDays: z.array(z.string()).default([]),
  interestedTracks: z.array(z.string()).default([]),
  couponCode: z.string().trim().max(64).optional().or(z.literal("")),
  // Declarations (separating media consent from mandatory registration!)
  consentZeroTolerance: z.literal(true, {
    errorMap: () => ({
      message: "You must acknowledge the zero-tolerance policy towards harassment.",
    }),
  }),
  consentDataProcessing: z.literal(true, {
    errorMap: () => ({
      message: "You must consent to the processing of your registration information.",
    }),
  }),
  consentMediaPhotography: z.boolean().default(false),
  // Optional Delegate fields
  delegateDetails: z
    .object({
      gender: z.string().max(50).optional(),
      age: z.coerce.number().int().min(1).max(120).optional(),
      cityState: z.string().max(150).optional(),
      paraSportsInterests: z.array(z.string()).default([]),
      outstationTravelRequired: z.boolean().default(false),
      accommodationNeeded: z.boolean().default(false),
      foodPreference: z.string().max(80).optional(),
      emergencyContactName: z.string().max(150).optional(),
      emergencyContactPhone: z.string().max(40).optional(),
      guardianName: z.string().max(150).optional(),
    })
    .optional(),
  // Optional Volunteer fields (merged from VOLUNTEER AND SLI.docx & VOLUNTEER AND SLI 1.docx)
  volunteerDetails: z
    .object({
      age: z.coerce.number().int().min(16).max(100).optional(),
      gender: z.string().max(50).optional(),
      cityState: z.string().max(150).optional(),
      institutionName: z.string().max(200).optional(),
      isMsuStudentOrAlumni: z.boolean().default(false),
      crrNumber: z.string().max(80).optional(),
      preferredRoles: z.array(z.string()).default([]),
      availabilityDays: z.array(z.string()).default([]),
      languagesSpoken: z.array(z.string()).default([]),
      relevantExperience: z.string().max(2000).optional(),
      emergencyContactName: z.string().max(150).optional(),
      emergencyContactPhone: z.string().max(40).optional(),
    })
    .optional(),
  // Optional SLI fields (merged from VOLUNTEER AND SLI.docx & VOLUNTEER AND SLI 1.docx)
  sliDetails: z
    .object({
      cityState: z.string().max(150).optional(),
      basedInGujarat: z.boolean().default(true),
      crrNumber: z.string().max(80).optional(),
      qualifications: z.string().max(1000).optional(),
      trainingInstitute: z.string().max(200).optional(),
      certificationLevel: z.string().max(120).optional(),
      yearsOfExperience: z.coerce.number().int().min(0).max(70).optional(),
      professionalAffiliation: z.string().max(200).optional(),
      areasOfExpertise: z.array(z.string()).default([]),
      interpretationPreferences: z.array(z.string()).default([]),
      availabilityDays: z.array(z.string()).default([]),
      videoDemoUrl: z.string().url("Please enter a valid URL.").optional().or(z.literal("")),
      honorariumOrTravelNotes: z.string().max(1000).optional(),
    })
    .optional(),
});

export const loginSchema = z.object({
  email: z.string().trim().email("Please enter a valid email address."),
  password: z.string().min(1, "Please enter your password."),
  mfaCode: z.string().trim().max(10).optional(),
});

export const signupSchema = z.object({
  fullName: z.string().trim().min(2, "Full name is required.").max(200),
  email: z.string().trim().email("Please enter a valid email address.").max(255),
  phone: z.string().trim().regex(internationalPhoneRegex, "Invalid phone number.").optional().or(z.literal("")),
  country: z.string().trim().default("India"),
  password: z.string().min(8, "Password must be at least 8 characters.").max(128),
});

