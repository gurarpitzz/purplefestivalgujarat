-- Rollback for 002_phase3_atomic_registration_sequences.sql
-- WARNING: Only execute after removing FK fk_reg_num_seq_edition (005 rollback) and only if reverting Phase 3.

DROP TABLE IF EXISTS `registration_number_sequences`;

