<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Accountless Public Registration Migration 013 (CAD-17)
 *
 * Supersedes mandatory participant website accounts while preserving 100% of historical
 * registration records, user associations, foreign key integrity, and registration sequences.
 *
 * Changes (100% portable across MySQL 5.7/8.0 and MariaDB 10.4+, strictly idempotent):
 * 1. Make `registrations.user_id` NULLABLE (`CHAR(36) NULL DEFAULT NULL`) with FK `fk_reg_user`
 *    using `ON DELETE SET NULL ON UPDATE CASCADE`.
 * 2. Make `privacy_consent_records.user_id` NULLABLE (`CHAR(36) NULL DEFAULT NULL`) with FK `fk_pcr_user`
 *    using `ON DELETE SET NULL ON UPDATE CASCADE`.
 * 3. Add optional `preferred_language` column (`VARCHAR(32) NULL DEFAULT NULL`) on `registrations`.
 * 4. Add composite index `idx_reg_edition_cat_email (edition_id, category_code, email(191))` on
 *    `registrations` to support fast accountless duplicate detection & lookup.
 */

return static function (PDO $pdo): array {
    $changes = [
        'columnsModified' => [],
        'columnsAdded'    => [],
        'indexesAdded'    => [],
        'foreignKeys'     => [],
    ];

    $makeUserIdNullable = static function (string $table, string $fkName) use ($pdo, &$changes): void {
        if (!pfgDbTableExists($pdo, $table) || !pfgDbColumnExists($pdo, $table, 'user_id')) {
            return;
        }

        $st = $pdo->prepare(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = 'user_id' LIMIT 1"
        );
        $st->execute([':tbl' => $table]);
        $isNullable = strtoupper((string)$st->fetchColumn()) === 'YES';

        if (!$isNullable) {
            if (pfgDbForeignKeyExists($pdo, $table, $fkName)) {
                $pdo->exec(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $fkName));
            }
            $pdo->exec(sprintf('ALTER TABLE `%s` MODIFY COLUMN `user_id` CHAR(36) NULL DEFAULT NULL', $table));
            $changes['columnsModified'][] = "{$table}.user_id -> NULLABLE";
        }

        if (!pfgDbForeignKeyExists($pdo, $table, $fkName)) {
            $fkRes = pfgDbEnsureForeignKey($pdo, $table, $fkName, 'user_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $changes['foreignKeys'][] = $fkRes;
        }
    };

    // 1. Make registrations.user_id nullable (preserving all historical non-null user_id rows)
    $makeUserIdNullable('registrations', 'fk_reg_user');

    // 2. Make privacy_consent_records.user_id nullable (preserving all historical consent rows)
    $makeUserIdNullable('privacy_consent_records', 'fk_pcr_user');

    // 3. Optional preferred_language directly on registrations for accountless applicants
    if (pfgDbEnsureColumn($pdo, 'registrations', 'preferred_language', 'VARCHAR(32) NULL DEFAULT NULL AFTER `country`')) {
        $changes['columnsAdded'][] = 'registrations.preferred_language';
    }

    // 4. Composite index for accountless duplicate detection & contact lookup (MySQL 5.7/8.0 + MariaDB safe prefix)
    if (pfgDbEnsureIndex($pdo, 'registrations', 'idx_reg_edition_cat_email', '`edition_id`, `category_code`, `email`(191)', false)) {
        $changes['indexesAdded'][] = 'registrations.idx_reg_edition_cat_email';
    }

    return $changes;
};

