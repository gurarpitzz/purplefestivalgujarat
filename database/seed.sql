-- ============================================================================
-- Purple Festival Gujarat 2027 — Idempotent Production Seed Data (MySQL / MariaDB)
-- ============================================================================

SET NAMES utf8mb4;

-- 1. ROLES
INSERT INTO `roles` (`id`, `code`, `name`, `description`) VALUES
  ('10000000-0000-4000-8000-000000000001', 'SUPERADMIN', 'Superadmin', 'Full system, security, CMS, user, and analytics authority.'),
  ('10000000-0000-4000-8000-000000000002', 'ADMIN', 'Festival Admin', 'Day-to-day registration, volunteer/SLI review, attendance, and reporting.'),
  ('10000000-0000-4000-8000-000000000003', 'RGST', 'Registration Desk Staff', 'Restricted reception-desk QR scanning and entry confirmation.'),
  ('10000000-0000-4000-8000-000000000004', 'PARTICIPANT', 'Participant', 'Attendee, delegate, volunteer, or SLI professional account.')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`);

-- 2. PERMISSIONS
INSERT INTO `permissions` (`id`, `code`, `description`) VALUES
  ('20000000-0000-4000-8000-000000000001', 'account.read.own', 'account.read.own'),
  ('20000000-0000-4000-8000-000000000002', 'registrations.read.all', 'registrations.read.all'),
  ('20000000-0000-4000-8000-000000000003', 'registrations.write', 'registrations.write'),
  ('20000000-0000-4000-8000-000000000004', 'registrations.export', 'registrations.export'),
  ('20000000-0000-4000-8000-000000000005', 'volunteers.review', 'volunteers.review'),
  ('20000000-0000-4000-8000-000000000006', 'sli.review', 'sli.review'),
  ('20000000-0000-4000-8000-000000000007', 'accommodations.read', 'accommodations.read'),
  ('20000000-0000-4000-8000-000000000008', 'accommodations.manage', 'accommodations.manage'),
  ('20000000-0000-4000-8000-000000000009', 'cms.manage', 'cms.manage'),
  ('20000000-0000-4000-8000-000000000010', 'admin_accounts.manage', 'admin_accounts.manage'),
  ('20000000-0000-4000-8000-000000000011', 'credentials.issue', 'credentials.issue'),
  ('20000000-0000-4000-8000-000000000012', 'credentials.revoke', 'credentials.revoke'),
  ('20000000-0000-4000-8000-000000000013', 'qr.scan', 'qr.scan'),
  ('20000000-0000-4000-8000-000000000014', 'checkins.confirm', 'checkins.confirm'),
  ('20000000-0000-4000-8000-000000000015', 'attendance.read.full', 'attendance.read.full'),
  ('20000000-0000-4000-8000-000000000016', 'attendance.read.own', 'attendance.read.own'),
  ('20000000-0000-4000-8000-000000000017', 'coupons.manage', 'coupons.manage'),
  ('20000000-0000-4000-8000-000000000018', 'payments.read', 'payments.read'),
  ('20000000-0000-4000-8000-000000000019', 'audit_logs.read', 'audit_logs.read'),
  ('20000000-0000-4000-8000-000000000020', 'settings.manage', 'settings.manage')
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`);

-- 3. FESTIVAL EDITION
INSERT INTO `festival_editions` (`id`, `slug`, `name`, `tagline`, `city`, `state`, `start_date`, `end_date`, `registration_opens_at`, `registration_closes_at`, `attendance_policy`, `is_active`) VALUES
  ('00000000-0000-4000-8000-000000002027', 'gujarat-2027', 'Purple Festival Gujarat 2027', 'Where inclusion becomes a celebration.', 'Vadodara', 'Gujarat', '2027-01-21 09:00:00', '2027-01-23 20:30:00', '2026-11-01 00:00:00', '2027-01-20 23:59:59', 'ONCE_PER_DAY', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `tagline` = VALUES(`tagline`),
  `is_active` = 1;

-- 4. VENUES
INSERT IGNORE INTO `venues` (`id`, `edition_id`, `name`, `address`, `confirmation_status`, `accessibility_features`, `capacity`) VALUES
  ('30000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000002027', 'Sama Indoor Stadium', 'Sama, Vadodara, Gujarat', 'CONFIRMED', '["Step-free entry","Wheelchair-accessible courts","Accessible restrooms","Para-sports ready"]', 2500),
  ('30000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000002027', 'The Maharaja Sayajirao University of Baroda (FSW & Dept. of Physical Education)', 'Pratapgunj, Vadodara, Gujarat', 'COMMITTED', '["Campus access audit in progress","Accessible seminar halls","Sports pavilion"]', 3000),
  ('30000000-0000-4000-8000-000000000003', '00000000-0000-4000-8000-000000002027', 'GMERS Medical College, Gotri', 'Gotri, Vadodara, Gujarat', 'PENDING', '["Pending final venue confirmation"]', 1000);

-- 5. CHECK-IN STATIONS
INSERT IGNORE INTO `checkin_stations` (`id`, `edition_id`, `venue_id`, `name`, `gate_code`, `is_active`) VALUES
  ('31000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000002027', '30000000-0000-4000-8000-000000000001', 'Sama Indoor Stadium — Main Gate A', 'SAMA-A', 1),
  ('31000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000002027', '30000000-0000-4000-8000-000000000001', 'Sama Indoor Stadium — Accessible Priority Desk', 'SAMA-ACC', 1),
  ('31000000-0000-4000-8000-000000000003', '00000000-0000-4000-8000-000000002027', '30000000-0000-4000-8000-000000000002', 'MSU Baroda — Central Registration Desk', 'MSU-MAIN', 1);

-- 6. ALL 21 RPwD ACT 2016 DISABILITY CATEGORIES
INSERT INTO `disability_categories` (`id`, `code`, `name`, `rpwd_group`, `description`, `accommodation_examples`, `display_order`) VALUES
  ('40000000-0000-4000-8000-000000000001', 'BLINDNESS', 'Blindness', 'Visual Impairment', 'Total absence of sight, or visual acuity not exceeding 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 10 degrees.', '["Braille schedules and signage","Screen-reader-compatible digital materials","Sighted guides and tactile floor pathways","Audio description during stage performances"]', 1),
  ('40000000-0000-4000-8000-000000000002', 'LOW_VISION', 'Low Vision', 'Visual Impairment', 'Visual acuity not exceeding 6/18 or less than 20/60 up to 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 40 degrees up to 10 degrees.', '["Large-print schedules and high-contrast signage","Front-row seating near screens","Adjustable text size and high-contrast digital access"]', 2),
  ('40000000-0000-4000-8000-000000000003', 'LEPROSY_CURED', 'Leprosy Cured Persons', 'Locomotor Disability', 'Persons cured of leprosy who continue to experience loss of sensation in hands or feet, paresis in the eye and eyelid, or manifest deformity that limits daily participation.', '["Ergonomic seating and minimal-grip utensils","Accessible washrooms and step-free routes","Dignified, stigma-free assistance desks"]', 3),
  ('40000000-0000-4000-8000-000000000004', 'HEARING_IMPAIRMENT', 'Hearing Impairment (Deaf and Hard of Hearing)', 'Hearing Impairment', 'Includes Deaf persons (70 dB or greater loss in speech frequencies in both ears) and Hard of Hearing persons (60 dB to 70 dB loss in speech frequencies in both ears).', '["Qualified Indian Sign Language (ISL) interpretation","Live captioning (CART) on venue screens","Visual alerts and well-lit front seating for lip-reading"]', 4),
  ('40000000-0000-4000-8000-000000000005', 'LOCOMOTOR_DISABILITY', 'Locomotor Disability', 'Locomotor Disability', 'Inability to execute distinctive activities associated with movement of self and objects resulting from affliction of musculoskeletal or nervous system.', '["Step-free ramps (1:12 gradient) and elevators","Wheelchair-accessible seating and charging bays","Accessible restrooms and battery-operated venue shuttles"]', 5),
  ('40000000-0000-4000-8000-000000000006', 'DWARFISM', 'Dwarfism', 'Locomotor Disability', 'A medical or genetic condition resulting in an adult height of 4 feet 10 inches (147 centimeters) or less.', '["Lowered registration and refreshment counters","Accessible microphones and seating options","Step-free access across all stages and pavilions"]', 6),
  ('40000000-0000-4000-8000-000000000007', 'INTELLECTUAL_DISABILITY', 'Intellectual Disability', 'Intellectual Disability', 'A condition characterized by significant limitation both in intellectual functioning (reasoning, learning, problem solving) and in adaptive behavior covering everyday social and practical skills.', '["Easy-read plain-language schedules with pictograms","Trained orientation volunteers and buddy support","Predictable signage and calm rest areas"]', 7),
  ('40000000-0000-4000-8000-000000000008', 'MENTAL_ILLNESS', 'Mental Illness', 'Mental Behavior', 'A substantial disorder of thinking, mood, perception, orientation, or memory that grossly impairs judgment, behavior, capacity to recognize reality, or ability to meet the ordinary demands of life.', '["Quiet sensory-friendly sanctuary spaces","Flexible breaks without losing seating","Trained psychosocial first-aid volunteers"]', 8),
  ('40000000-0000-4000-8000-000000000009', 'AUTISM_SPECTRUM_DISORDER', 'Autism Spectrum Disorder', 'Intellectual / Neurodevelopmental', 'A neuro-developmental condition typically appearing in the first three years of life that significantly affects verbal and non-verbal communication, social interaction, and sensory processing.', '["Low-stimulation sensory sanctuary rooms","Noise-dampening headphones and visual schedules","Advance venue walkthroughs and reduced-queue entry"]', 9),
  ('40000000-0000-4000-8000-000000000010', 'CEREBRAL_PALSY', 'Cerebral Palsy', 'Locomotor Disability', 'A group of non-progressive neurological conditions affecting body movements and muscle coordination caused by damage to one or more specific areas of the brain.', '["Wide wheelchair pathways and supportive seating","AAC (Augmentative & Alternative Communication) patience","Accessible restrooms with caregiver space"]', 10),
  ('40000000-0000-4000-8000-000000000011', 'MUSCULAR_DYSTROPHY', 'Muscular Dystrophy', 'Locomotor Disability', 'A group of hereditary genetic muscle diseases that weaken the muscles that move the human body, characterized by progressive skeletal muscle weakness.', '["Power wheelchair charging bays","Short-distance accessible drop-off points","Climate-controlled rest zones"]', 11),
  ('40000000-0000-4000-8000-000000000012', 'CHRONIC_NEUROLOGICAL', 'Chronic Neurological Conditions', 'Chronic Neurological', 'Conditions originating in the central or peripheral nervous system (such as epilepsy, stroke aftermath, or neurodegenerative conditions) requiring ongoing management.', '["Seizure-safe lighting (no flashing strobes)","Priority shaded seating and hydration stations","On-site medical emergency response desk"]', 12),
  ('40000000-0000-4000-8000-000000000013', 'SPECIFIC_LEARNING_DISABILITIES', 'Specific Learning Disabilities', 'Intellectual / Neurodevelopmental', 'A heterogeneous group of conditions wherein there is a deficit in processing language, spoken or written, manifesting as difficulty in comprehending, reading, writing, spelling, or mathematical calculation.', '["Readable sans-serif typography and uncluttered layouts","Audio and visual alternatives to dense text","Untimed, save-and-resume registration workflows"]', 13),
  ('40000000-0000-4000-8000-000000000014', 'MULTIPLE_SCLEROSIS', 'Multiple Sclerosis', 'Chronic Neurological', 'An inflammatory, nervous system disease in which the myelin sheaths around the axons of nerve cells of the brain and spinal cord are damaged, affecting communication between brain and body.', '["Cooling and shaded rest areas to prevent heat fatigue","Mobility assistance and priority seating","Accessible restrooms near every hall"]', 14),
  ('40000000-0000-4000-8000-000000000015', 'SPEECH_AND_LANGUAGE', 'Speech and Language Disability', 'Speech & Language', 'A permanent disability arising out of conditions such as laryngectomy or aphasia affecting one or more components of speech and language.', '["Text, email, and digital chat support at help desks","Extended speaking time and AAC-friendly microphones","Written Q&A options during panel discussions"]', 15),
  ('40000000-0000-4000-8000-000000000016', 'THALASSEMIA', 'Thalassemia', 'Blood Disorder', 'A group of inherited disorders characterized by reduced or absent amounts of hemoglobin.', '["Priority seating to reduce standing fatigue","Hydration and dietary accommodation support","Quick access to medical assistance desks"]', 16),
  ('40000000-0000-4000-8000-000000000017', 'HEMOPHILIA', 'Hemophilia', 'Blood Disorder', 'An inheritable disease, usually affecting males, characterized by impairment of the normal clotting ability of blood.', '["Safe, uncrowded pathways and priority entry lanes","On-site medical room awareness and rapid first aid","Accessible seating near aisles"]', 17),
  ('40000000-0000-4000-8000-000000000018', 'SICKLE_CELL_DISEASE', 'Sickle Cell Disease', 'Blood Disorder', 'A hemolytic disorder characterized by chronic anemia, painful events, and various complications due to associated tissue and organ damage.', '["Shaded rest lounges and continuous hydration stations","Minimal walking distances with shuttle support","Medical assistance readiness"]', 18),
  ('40000000-0000-4000-8000-000000000019', 'MULTIPLE_DISABILITIES', 'Multiple Disabilities (including Deafblindness)', 'Multiple Disabilities', 'Two or more of the specified disabilities occurring together, including deafblindness — a combination of hearing and visual impairments causing severe communication and developmental needs.', '["Tactile sign language interpreters and intervener support","Combined physical, sensory, and communication accommodations","Dedicated companion / escort seating"]', 19),
  ('40000000-0000-4000-8000-000000000020', 'ACID_ATTACK_VICTIM', 'Acid Attack Victim', 'Locomotor / Physical', 'A person disfigured due to violent assaults by throwing of acid or similar corrosive substance.', '["Climate-controlled, sun-protected spaces","Visual or mobility accommodations as individually requested","Respectful, privacy-preserving check-in and support"]', 20),
  ('40000000-0000-4000-8000-000000000021', 'PARKINSONS_DISEASE', 'Parkinson''s Disease', 'Chronic Neurological', 'A progressive disease of the nervous system marked by tremor, muscular rigidity, and slow, imprecise movement.', '["Large touch targets and keyboard-accessible controls","Seated check-in desks and handrails along walkways","Untimed forms and mobility assistance"]', 21)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `rpwd_group` = VALUES(`rpwd_group`),
  `description` = VALUES(`description`),
  `accommodation_examples` = VALUES(`accommodation_examples`),
  `display_order` = VALUES(`display_order`);

-- 7. 13 ACCOMMODATION TYPES
INSERT INTO `accommodation_types` (`id`, `code`, `label`, `is_active`, `display_order`) VALUES
  ('41000000-0000-4000-8000-000000000001', 'ISL_INTERPRETER', 'Indian Sign Language (ISL) Interpreter', 1, 1),
  ('41000000-0000-4000-8000-000000000002', 'TACTILE_INTERPRETATION', 'Tactile Interpretation (Deafblind support)', 1, 2),
  ('41000000-0000-4000-8000-000000000003', 'BRAILLE_SCHEDULE', 'Braille Schedule & Programme', 1, 3),
  ('41000000-0000-4000-8000-000000000004', 'LARGE_PRINT', 'Large-Print Material', 1, 4),
  ('41000000-0000-4000-8000-000000000005', 'SCREEN_READER_DIGITAL', 'Screen-Reader-Compatible Digital Material', 1, 5),
  ('41000000-0000-4000-8000-000000000006', 'WHEELCHAIR_SEATING', 'Wheelchair-Accessible Seating', 1, 6),
  ('41000000-0000-4000-8000-000000000007', 'WHEELCHAIR_CHARGING', 'Wheelchair Charging Bay', 1, 7),
  ('41000000-0000-4000-8000-000000000008', 'SIGHTED_GUIDE', 'Sighted Guide', 1, 8),
  ('41000000-0000-4000-8000-000000000009', 'SENSORY_SANCTUARY', 'Sensory Sanctuary Access', 1, 9),
  ('41000000-0000-4000-8000-000000000010', 'CAPTIONING', 'Live Captioning (CART)', 1, 10),
  ('41000000-0000-4000-8000-000000000011', 'MOBILITY_ASSISTANCE', 'Mobility Assistance / Venue Shuttle', 1, 11),
  ('41000000-0000-4000-8000-000000000012', 'DIETARY', 'Dietary Accommodation', 1, 12),
  ('41000000-0000-4000-8000-000000000013', 'OTHER', 'Other Accessibility Requirement', 1, 13)
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `display_order` = VALUES(`display_order`);

-- 8. 8 REGISTRATION CATEGORIES
INSERT INTO `registration_categories` (`id`, `edition_id`, `code`, `name`, `description`, `requires_approval`, `requires_payment`, `fee_amount_inr`, `is_active`, `display_order`) VALUES
  ('50000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000002027', 'ATTENDEE', 'General Attendee', 'Open festival access to cultural performances, exhibitions, para-sports, and inclusive sessions.', 0, 0, 0, 1, 1),
  ('50000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000002027', 'DELEGATE', 'Delegate (3-Day Conference & All-Access)', 'Full access to conferences, workshops, panels, and networking. Delegate fee policy is managed by organizers in CMS.', 0, 0, 0, 1, 2),
  ('50000000-0000-4000-8000-000000000003', '00000000-0000-4000-8000-000000002027', 'VOLUNTEER', 'Festival Volunteer', 'Support accessibility desks, stage anchoring, para-sports, and participant hospitality. Requires organizer review.', 1, 0, 0, 1, 3),
  ('50000000-0000-4000-8000-000000000004', '00000000-0000-4000-8000-000000002027', 'SLI', 'SLI / Indian Sign Language Professional', 'Qualified Indian Sign Language interpreters supporting plenaries, panels, workshops, and cultural stages. Requires verification.', 1, 0, 0, 1, 4),
  ('50000000-0000-4000-8000-000000000005', '00000000-0000-4000-8000-000000002027', 'STUDENT', 'Student Participant', 'School, college, and university students participating in inclusive education, workshops, and youth tracks.', 0, 0, 0, 1, 5),
  ('50000000-0000-4000-8000-000000000006', '00000000-0000-4000-8000-000000002027', 'PROFESSIONAL', 'Professional / Educator / Practitioner', 'Educators, healthcare professionals, accessibility specialists, and industry practitioners.', 0, 0, 0, 1, 6),
  ('50000000-0000-4000-8000-000000000007', '00000000-0000-4000-8000-000000002027', 'INSTITUTIONAL', 'Institutional Representative', 'Representatives from DPOs, NGOs, universities, and civic institutions.', 0, 0, 0, 1, 7),
  ('50000000-0000-4000-8000-000000000008', '00000000-0000-4000-8000-000000002027', 'ALLY', 'Ally / Supporter', 'Family members, caregivers, community allies, and inclusion advocates.', 0, 0, 0, 1, 8)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `display_order` = VALUES(`display_order`);

-- 9. ORGANIZATIONS & PARTNERS
INSERT IGNORE INTO `organizations` (`id`, `name`, `short_name`, `logo_url`, `logo_alt_text`, `description`) VALUES
  ('60000000-0000-4000-8000-000000000001', 'Brotherhood', 'Brotherhood', '/assets/logos/brotherhood-transparent.png', 'Brotherhood official logo', 'Co-organiser advancing disability inclusion, film festivals, and cultural participation across India.'),
  ('60000000-0000-4000-8000-000000000002', 'Global Inclusive Education Network (GIEN)', 'GIEN', '/assets/logos/gien.jpg', 'Global Inclusive Education Network (GIEN) official logo', 'Co-organiser driving inclusive education policy, teacher training, and universal design for learning.'),
  ('60000000-0000-4000-8000-000000000003', 'The Maharaja Sayajirao University of Baroda', 'MSU Baroda', NULL, 'The Maharaja Sayajirao University of Baroda', 'Committed academic and venue host via Faculty of Social Work and Department of Physical Education.'),
  ('60000000-0000-4000-8000-000000000004', 'Praangan', 'Praangan', '/assets/logos/praangan.jpg', 'Praangan Foundation logo', 'Co-organiser advancing creative expression, accessibility, and inclusive community initiatives.'),
  ('60000000-0000-4000-8000-000000000005', 'Centre for Disability Studies and Action, TISS', 'CDSA-TISS', '/assets/logos/cdsa-tiss.jpg', 'Centre for Disability Studies and Action, TISS logo', 'Academic Partner advancing disability studies, social work research, and inclusive policy.');

INSERT IGNORE INTO `partners` (`id`, `edition_id`, `organization_id`, `category`, `consent_verified`, `display_order`, `status`) VALUES
  ('61000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000002027', '60000000-0000-4000-8000-000000000001', 'Organizers', 1, 1, 'PUBLISHED'),
  ('61000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000002027', '60000000-0000-4000-8000-000000000002', 'Organizers', 1, 2, 'PUBLISHED'),
  ('61000000-0000-4000-8000-000000000003', '00000000-0000-4000-8000-000000002027', '60000000-0000-4000-8000-000000000003', 'Venue & Academic Partners', 1, 3, 'PUBLISHED'),
  ('61000000-0000-4000-8000-000000000004', '00000000-0000-4000-8000-000000002027', '60000000-0000-4000-8000-000000000004', 'Organizers', 1, 4, 'PUBLISHED'),
  ('61000000-0000-4000-8000-000000000005', '00000000-0000-4000-8000-000000002027', '60000000-0000-4000-8000-000000000005', 'Knowledge Partners', 1, 5, 'PUBLISHED');

-- 10. 3-DAY PROGRAMME ITEMS
INSERT IGNORE INTO `programme_items` (`id`, `edition_id`, `day_number`, `time_slot`, `title`, `description`, `category`, `venue_name`, `speakers`, `accessibility_services`, `display_order`, `status`) VALUES
  ('70000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000002027', 1, '08:30 – 09:30 IST', 'Participant Check-in, Accessible Welcome & Registration Desk', 'QR pass verification, Braille & large-print programme distribution, ISL helpdesk, and wheelchair shuttle orientation.', 'Registration', 'Sama Indoor Stadium & MSU Baroda', '[]', '["ISL Interpretation","Braille Programme","Step-Free Access","Sighted Guides"]', 1, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000002027', 1, '09:30 – 11:00 IST', 'Inaugural Ceremony: Celebrating Accessibility, Dignity & Diversity', 'Opening keynote addresses celebrating Gujarat''s inaugural Purple Festival edition and India''s inclusive future under Viksit Bharat@2047.', 'Plenary', 'Main Auditorium', '[]', '["ISL Interpretation","Live Captioning (CART)","Audio Description","Wheelchair Seating"]', 2, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000003', '00000000-0000-4000-8000-000000002027', 1, '11:30 – 13:00 IST', 'Panel 1: Universal Design & Accessible Infrastructure for 2047', 'Architects, urban planners, and disability rights leaders examine barrier-free cities, public transit, and digital public infrastructure.', 'Panel Discussion', 'Hall A', '[]', '["ISL Interpretation","Live Captioning (CART)","Wheelchair Seating"]', 3, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000004', '00000000-0000-4000-8000-000000002027', 1, '14:00 – 16:30 IST', 'Para Sports Championships — Opening Heats (25 Adaptive Disciplines)', 'Adaptive athletics, wheelchair basketball, boccia, blind cricket demonstrations, and inclusive youth sports.', 'Inclusive Sports', 'Sama Indoor Stadium', '[]', '["Step-Free Courts","Wheelchair Charging Bay","Medical Support","ISL Volunteers"]', 4, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000005', '00000000-0000-4000-8000-000000002027', 1, '14:30 – 17:00 IST', 'Assistive Technology & Inclusive Innovation Expo', 'Hands-on demonstrations of screen readers, tactile graphics, mobility aids, AAC devices, and Indian assistive startups.', 'Exhibition', 'Innovation Pavilion', '[]', '["Tactile Demos","ISL Interpretation","Accessible Counters"]', 5, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000006', '00000000-0000-4000-8000-000000002027', 1, '18:30 – 20:30 IST', 'Evening Cultural Showcase: Disability Arts & Inclusive Theatre', 'Performances by artists with and without disabilities celebrating Gujarat''s folk heritage, contemporary dance, and sign-language music.', 'Cultural Performance', 'Open-Air Stage', '[]', '["ISL Interpretation","Audio Description","Seizure-Safe Lighting","Sensory Sanctuary Nearby"]', 6, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000007', '00000000-0000-4000-8000-000000002027', 2, '09:00 – 11:00 IST', 'Panel 2: Inclusive Education & NEP 2020 Implementation', 'Educators, researchers, and students discuss Universal Design for Learning (UDL), teacher preparedness, and accessible classrooms.', 'Inclusive Education', 'MSU Baroda — Hall A', '[]', '["ISL Interpretation","Live Captioning (CART)","Large-Print Handouts"]', 7, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000008', '00000000-0000-4000-8000-000000002027', 2, '11:30 – 13:00 IST', 'Panel 3: Rights, Legal Empowerment & the RPwD Act 2016', 'Legal scholars and DPO representatives examine reasonable accommodation, employment equity, and digital accessibility compliance.', 'Panel Discussion', 'Hall B', '[]', '["ISL Interpretation","Live Captioning (CART)","Braille Summary"]', 8, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000009', '00000000-0000-4000-8000-000000002027', 2, '14:00 – 16:00 IST', 'Inclusive Film Festival & Filmmaker Dialogues', 'Curated short films and documentaries on disability leadership with closed captions and audio description.', 'Disability Arts', 'Screening Hall', '[]', '["Closed Captions","Audio Description","ISL Q&A"]', 9, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000010', '00000000-0000-4000-8000-000000002027', 2, '16:30 – 18:30 IST', 'Sitaare Zameen Par — Inclusive Celebrity Exhibition Match', 'Flagship unified exhibition match bringing together para-athletes, youth players, and guest personalities at Sama Indoor Stadium.', 'Inclusive Sports', 'Sama Indoor Stadium', '[]', '["Wheelchair Seating","ISL Interpretation","Audio Commentary","Accessible Restrooms"]', 10, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000011', '00000000-0000-4000-8000-000000002027', 3, '09:30 – 11:30 IST', 'Inclusive Livelihoods, Entrepreneurship & Employer Conclave', 'Connecting talented persons with disabilities with inclusive employers, startups, and skill-development ecosystems.', 'Networking', 'Hall A', '[]', '["ISL Interpretation","Live Captioning (CART)","Quiet Interview Pods"]', 11, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000012', '00000000-0000-4000-8000-000000002027', 3, '12:00 – 14:00 IST', 'Para Sports Finals & Medal Ceremonies', 'Championship finals across adaptive disciplines celebrating athletic excellence and inclusive sportsmanship.', 'Inclusive Sports', 'Sama Indoor Stadium', '[]', '["Step-Free Podium","ISL Interpretation","Medical Support"]', 12, 'PUBLISHED'),
  ('70000000-0000-4000-8000-000000000013', '00000000-0000-4000-8000-000000002027', 3, '15:30 – 17:30 IST', 'Valedictory Ceremony & Vadodara Declaration on Inclusion', 'Closing keynote, volunteer and SLI recognition, and adoption of the Purple Festival Gujarat 2027 Inclusion Charter.', 'Plenary', 'Main Auditorium', '[]', '["ISL Interpretation","Live Captioning (CART)","Braille Charter","Wheelchair Seating"]', 13, 'PUBLISHED');

-- 11. CMS HOME PAGE & SECTIONS
INSERT INTO `cms_pages` (`id`, `edition_id`, `slug`, `title`, `meta_description`, `status`) VALUES
  ('80000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000002027', 'home', 'Purple Festival Gujarat 2027', 'Celebrating Accessibility, Inclusion, Dignity and Diversity — 21–23 January 2027 in Vadodara, Gujarat.', 'PUBLISHED')
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `meta_description` = VALUES(`meta_description`);

INSERT INTO `cms_sections` (`id`, `page_id`, `section_key`, `title`, `subtitle`, `body`, `data`, `display_order`, `status`) VALUES
  ('81000000-0000-4000-8000-000000000001', '80000000-0000-4000-8000-000000000001', 'hero', 'PURPLE FESTIVAL GUJARAT 2027', 'Where inclusion becomes a celebration.', 'India''s landmark festival of accessibility, human dignity, assistive innovation, para-sports, and universal design across all 21 benchmark disabilities under the RPwD Act, 2016.', '{"dates":"21–23 January 2027","location":"Vadodara, Gujarat","primaryCtaLabel":"Apply Now","primaryCtaHref":"/apply"}', 1, 'PUBLISHED'),
  ('81000000-0000-4000-8000-000000000002', '80000000-0000-4000-8000-000000000001', 'about', 'Inclusion Is Not Charity. It Is Culture, Innovation, and Right.', 'First Edition in Gujarat — Vadodara, January 2027', 'Purple Festival Gujarat 2027 brings together 5,000+ attendees, 500+ participants with and without disabilities, educators, para-athletes, artists, technologists, and policy leaders for three days of barrier-free celebration across Vadodara.', '{"stats":[{"label":"Expected Attendees","value":"5,000+"},{"label":"Benchmark Disabilities","value":"21"},{"label":"Para Sports Disciplines","value":"25"},{"label":"Days of Celebration","value":"3"}]}', 2, 'PUBLISHED'),
  ('81000000-0000-4000-8000-000000000003', '80000000-0000-4000-8000-000000000001', 'viksit-bharat', 'A Viksit Bharat Must Be an Inclusive Bharat.', 'Aligned with India''s Vision for 2047', 'India''s vision for Viksit Bharat@2047 is about progress that reaches every citizen. Purple Festival Gujarat 2027 advances accessible education, dignified livelihoods, assistive technology, and equal cultural participation as pillars of an inclusive nation.', '{"disclaimer":"Purple Festival Gujarat 2027 contributes to the national conversation around inclusive development under Viksit Bharat@2047."}', 3, 'PUBLISHED'),
  ('81000000-0000-4000-8000-000000000004', '80000000-0000-4000-8000-000000000001', 'contact', 'Connect with the Festival Secretariat', 'Accessibility, Registration, and Partnership Inquiries', 'Reach our team for accommodation requests, institutional delegations, volunteer coordination, or media inquiries.', '{"email":"purplefestivalgujarat@gmail.com","phones":[{"name":"Radha Tandon","number":"+91 99743 83920"},{"name":"Satish Kapoor","number":"+91 92117 60202"}],"city":"Vadodara, Gujarat, India"}', 4, 'PUBLISHED')
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `subtitle` = VALUES(`subtitle`),
  `body` = VALUES(`body`);

-- 12. FAQ ITEMS
INSERT IGNORE INTO `faq_items` (`id`, `edition_id`, `category`, `question`, `answer`, `display_order`, `status`) VALUES
  ('82000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000002027', 'Registration', 'Do I need to disclose a disability to attend Purple Festival Gujarat 2027?', 'No. Disability disclosure is completely optional and never restricts which category you may apply for. Persons with disabilities can register as Attendees, Delegates, Volunteers, or SLI Professionals.', 1, 'PUBLISHED'),
  ('82000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000002027', 'Accessibility', 'How do I request Indian Sign Language (ISL), Braille, or wheelchair accommodations?', 'You can select your accommodation requirements during registration or update them anytime in your Participant Dashboard. Your requests are visible only to authorized accessibility coordinators.', 2, 'PUBLISHED'),
  ('82000000-0000-4000-8000-000000000003', '00000000-0000-4000-8000-000000002027', 'QR Pass & Check-in', 'How will I receive my digital QR entry pass?', 'Once your registration is confirmed (or your Volunteer/SLI application is approved), your unique digital QR pass is issued automatically in your Participant Dashboard and can be downloaded as an accessible PDF.', 3, 'PUBLISHED'),
  ('82000000-0000-4000-8000-000000000004', '00000000-0000-4000-8000-000000002027', 'Volunteers & SLI', 'Does submitting a Volunteer or SLI application mean automatic approval?', 'No. Volunteer and Indian Sign Language Interpreter applications undergo review by festival coordinators. Once approved, you will receive your role assignment and official digital credential.', 4, 'PUBLISHED');

-- 13. NOTIFICATION TEMPLATES
INSERT INTO `notification_templates` (`id`, `code`, `subject`, `body_html`, `body_text`, `is_active`) VALUES
  ('83000000-0000-4000-8000-000000000001', 'REGISTRATION_CONFIRMED', 'Confirmed: Your Purple Festival Gujarat 2027 Registration ({{registrationCode}})', '<p>Dear {{fullName}},</p><p>Your registration ({{registrationCode}}) for Purple Festival Gujarat 2027 is confirmed. Log in to your Participant Dashboard to download your accessible QR pass.</p>', 'Dear {{fullName}}, Your registration ({{registrationCode}}) for Purple Festival Gujarat 2027 is confirmed. Log in to your Participant Dashboard to download your accessible QR pass.', 1),
  ('83000000-0000-4000-8000-000000000002', 'APPLICATION_SUBMITTED', 'Application Received: Purple Festival Gujarat 2027 ({{registrationCode}})', '<p>Dear {{fullName}},</p><p>We have received your {{category}} application ({{registrationCode}}). Our review team will notify you once reviewed.</p>', 'Dear {{fullName}}, We have received your {{category}} application ({{registrationCode}}). Our review team will notify you once reviewed.', 1),
  ('83000000-0000-4000-8000-000000000003', 'APPLICATION_APPROVED', 'Approved: Welcome to Purple Festival Gujarat 2027 ({{registrationCode}})', '<p>Dear {{fullName}},</p><p>Congratulations! Your {{category}} application ({{registrationCode}}) has been approved. Your digital QR credential and duty assignment are now available in your dashboard.</p>', 'Dear {{fullName}}, Your {{category}} application ({{registrationCode}}) has been approved. Your digital QR credential and duty assignment are now available in your dashboard.', 1)
ON DUPLICATE KEY UPDATE
  `subject` = VALUES(`subject`),
  `body_html` = VALUES(`body_html`),
  `body_text` = VALUES(`body_text`);

-- 14. SYSTEM SETTINGS
INSERT INTO `system_settings` (`id`, `key`, `value`, `description`) VALUES
  ('84000000-0000-4000-8000-000000000001', 'payments.enabled', '{"enabled":false,"reason":"Awaiting organizer fee confirmation and Razorpay production keys."}', 'Master toggle for paid registrations.'),
  ('84000000-0000-4000-8000-000000000002', 'sli.require_crr', '{"required":false,"gujaratPriority":true}', 'Configurable RCI CRR requirement for SLI applicants.')
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`);

-- 15. MEDIA ASSETS
INSERT IGNORE INTO `media_assets` (`id`, `edition_id`, `file_name`, `url`, `mime_type`, `alt_text`, `category`, `source_attribution`, `license_info`) VALUES
  ('85000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000002027', 'purple-festival.jpg', '/assets/logos/purple-festival.jpg', 'image/jpeg', 'Purple Festival Gujarat 2027 official emblem showing three inclusive figures beneath hearts', 'LOGO', 'Official Purple Festival Organizer Asset', 'Authorized Festival Trademark'),
  ('85000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000002027', 'venue-01.jpg', '/assets/gallery/venue-01.jpg', 'image/jpeg', 'Wide view of the Vadodara sports stadium and athletic ground prepared for inclusive para-sports', 'GALLERY', 'Organizer Venue Reconnaissance — MSU Baroda', 'Organizer Supplied'),
  ('85000000-0000-4000-8000-000000000003', '00000000-0000-4000-8000-000000002027', 'venue-05.jpg', '/assets/gallery/venue-05.jpg', 'image/jpeg', 'Indoor pavilion and accessible court facility in Vadodara for Purple Festival Gujarat 2027', 'GALLERY', 'Organizer Venue Reconnaissance — MSU Baroda', 'Organizer Supplied'),
  ('85000000-0000-4000-8000-000000000004', '00000000-0000-4000-8000-000000002027', 'venue-10.jpg', '/assets/gallery/venue-10.jpg', 'image/jpeg', 'Outdoor sports field and pavilion lawn at The Maharaja Sayajirao University of Baroda', 'GALLERY', 'Organizer Venue Reconnaissance — MSU Baroda', 'Organizer Supplied'),
  ('85000000-0000-4000-8000-000000000005', '00000000-0000-4000-8000-000000002027', 'venue-15.jpg', '/assets/gallery/venue-15.jpg', 'image/jpeg', 'Accessible campus walkway and entrance corridor at Vadodara festival venue', 'GALLERY', 'Organizer Venue Reconnaissance — MSU Baroda', 'Organizer Supplied'),
  ('85000000-0000-4000-8000-000000000006', '00000000-0000-4000-8000-000000002027', 'venue-20.jpg', '/assets/gallery/venue-20.jpg', 'image/jpeg', 'Sports complex seating and shaded pavilion area for spectators and para-athletes', 'GALLERY', 'Organizer Venue Reconnaissance — MSU Baroda', 'Organizer Supplied'),
  ('85000000-0000-4000-8000-000000000007', '00000000-0000-4000-8000-000000002027', 'venue-25.jpg', '/assets/gallery/venue-25.jpg', 'image/jpeg', 'Campus assembly area and shaded courtyard for workshops and community exhibitions', 'GALLERY', 'Organizer Venue Reconnaissance — MSU Baroda', 'Organizer Supplied');
