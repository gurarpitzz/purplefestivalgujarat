-- Rollback for 005_phase3_referential_integrity_and_indexes.php
-- Drops Phase 3 foreign keys and performance indexes (non-destructive to data).

ALTER TABLE `payment_orders` DROP FOREIGN KEY `fk_payord_user`, DROP FOREIGN KEY `fk_payord_reg`;
ALTER TABLE `coupon_redemptions` DROP FOREIGN KEY `fk_credemp_user`, DROP FOREIGN KEY `fk_credemp_reg`, DROP FOREIGN KEY `fk_credemp_coupon`;
ALTER TABLE `admin_actions` DROP FOREIGN KEY `fk_admact_target`, DROP FOREIGN KEY `fk_admact_admin`;
ALTER TABLE `audit_logs` DROP FOREIGN KEY `fk_audit_actor`;
ALTER TABLE `notification_delivery_logs` DROP FOREIGN KEY `fk_ndl_job`;
ALTER TABLE `notification_queue` DROP FOREIGN KEY `fk_nq_user`;
ALTER TABLE `announcements` DROP FOREIGN KEY `fk_ann_edition`;
ALTER TABLE `faq_items` DROP FOREIGN KEY `fk_faq_edition`;
ALTER TABLE `cms_sections` DROP FOREIGN KEY `fk_cmssec_page`;
ALTER TABLE `cms_pages` DROP FOREIGN KEY `fk_cmspage_edition`;
ALTER TABLE `programme_items` DROP FOREIGN KEY `fk_prog_edition`;
ALTER TABLE `checkin_stations` DROP FOREIGN KEY `fk_chkst_venue`, DROP FOREIGN KEY `fk_chkst_edition`;
ALTER TABLE `credential_events` DROP FOREIGN KEY `fk_credev_actor`, DROP FOREIGN KEY `fk_credev_cred`;
ALTER TABLE `checkins` DROP FOREIGN KEY `fk_chk_operator`, DROP FOREIGN KEY `fk_chk_edition`, DROP FOREIGN KEY `fk_chk_reg`, DROP FOREIGN KEY `fk_chk_cred`;
ALTER TABLE `qr_credentials` DROP FOREIGN KEY `fk_qrc_edition`, DROP FOREIGN KEY `fk_qrc_user`, DROP FOREIGN KEY `fk_qrc_reg`;
ALTER TABLE `attendance_correction_logs` DROP FOREIGN KEY `fk_attcorr_actor`, DROP FOREIGN KEY `fk_attcorr_reg`, DROP FOREIGN KEY `fk_attcorr_record`;
ALTER TABLE `attendance_records` DROP FOREIGN KEY `fk_attrec_correctedby`, DROP FOREIGN KEY `fk_attrec_station`, DROP FOREIGN KEY `fk_attrec_markedby`, DROP FOREIGN KEY `fk_attrec_reg`, DROP FOREIGN KEY `fk_attrec_edition`;
ALTER TABLE `privacy_consent_records` DROP FOREIGN KEY `fk_pcr_reg`, DROP FOREIGN KEY `fk_pcr_user`;
ALTER TABLE `accommodation_assignments` DROP FOREIGN KEY `fk_accass_assigned`, DROP FOREIGN KEY `fk_accass_req`;
ALTER TABLE `accommodation_requests` DROP FOREIGN KEY `fk_accreq_assigned`, DROP FOREIGN KEY `fk_accreq_user`, DROP FOREIGN KEY `fk_accreq_reg`;
ALTER TABLE `participant_disability_disclosures` DROP FOREIGN KEY `fk_pdd_discat`, DROP FOREIGN KEY `fk_pdd_reg`;
ALTER TABLE `delegate_details` DROP FOREIGN KEY `fk_deldet_reg`;
ALTER TABLE `sli_applications` DROP FOREIGN KEY `fk_sliapp_reg`;
ALTER TABLE `volunteer_applications` DROP FOREIGN KEY `fk_volapp_reg`;
ALTER TABLE `registration_number_sequences` DROP FOREIGN KEY `fk_regnumseq_edition`;
ALTER TABLE `registration_responses` DROP FOREIGN KEY `fk_regresp_reg`;
ALTER TABLE `registration_categories` DROP FOREIGN KEY `fk_regcat_edition`;
ALTER TABLE `registrations` DROP FOREIGN KEY `fk_reg_reviewer`, DROP FOREIGN KEY `fk_reg_category`, DROP FOREIGN KEY `fk_reg_user`, DROP FOREIGN KEY `fk_reg_edition`;
