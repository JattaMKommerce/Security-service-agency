<?php
declare(strict_types=1);

/**
 * Automatic database upgrades for sites installed before a feature existed.
 * Every step is safe to run more than once (it checks before changing).
 * database/schema.sql always holds the full, current schema for new installs.
 */

const SCHEMA_VERSION = 2;

function maybe_migrate(): void
{
    if ((int)setting('schema_version', '1') >= SCHEMA_VERSION) {
        return;
    }
    // Only one request runs the upgrade; the others carry on.
    if ((int)q_val("SELECT GET_LOCK('sa_migrate', 0)") !== 1) {
        return;
    }
    try {
        $current = (int)(q_val("SELECT setting_value FROM settings WHERE setting_key = 'schema_version'") ?? 1);
        if ($current < 2) {
            migrate_to_2();
            save_setting('schema_version', '2');
        }
    } catch (Throwable $e) {
        error_log('Database upgrade failed: ' . $e->getMessage());
    } finally {
        q("SELECT RELEASE_LOCK('sa_migrate')");
    }
}

function db_has_column(string $table, string $column): bool
{
    return (bool)q_val('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
}

function db_add_columns(string $table, array $columns): void
{
    foreach ($columns as $name => $definition) {
        if (!db_has_column($table, $name)) {
            db()->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
        }
    }
}

/** v2: duty selfie, late / overtime, extensions, handovers, tracking health alerts. */
function migrate_to_2(): void
{
    db_add_columns('attendance', [
        'check_in_selfie'     => 'VARCHAR(80) NULL',
        'shift_start_at'      => 'DATETIME NULL',
        'shift_end_at'        => 'DATETIME NULL',
        'late_minutes'        => 'INT UNSIGNED NULL',
        'overtime_minutes'    => 'INT UNSIGNED NULL',
        'approved_ot_minutes' => 'INT UNSIGNED NULL',
    ]);
    db_add_columns('guards', [
        'last_ping_at' => 'DATETIME NULL',
        'gps_state'    => "ENUM('ok','off','denied','unknown') NOT NULL DEFAULT 'unknown'",
        'gps_state_at' => 'DATETIME NULL',
    ]);
    db()->exec("ALTER TABLE alerts MODIFY type ENUM('sos','geofence_exit','late','no_movement','gps_off','offline') NOT NULL");

    db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS duty_extensions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guard_id            INT UNSIGNED NOT NULL,
    attendance_id       INT UNSIGNED NOT NULL,
    assignment_id       INT UNSIGNED NULL,
    requested_minutes   INT UNSIGNED NOT NULL,
    approved_minutes    INT UNSIGNED NULL,
    reason              VARCHAR(255) NOT NULL,
    status              ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requested_at        DATETIME NOT NULL,
    decided_by          INT UNSIGNED NULL,
    decided_at          DATETIME NULL,
    admin_note          VARCHAR(255) NULL,
    KEY idx_ext_status (status, requested_at),
    KEY idx_ext_att (attendance_id),
    KEY idx_ext_guard (guard_id, requested_at),
    CONSTRAINT fk_ext_guard FOREIGN KEY (guard_id) REFERENCES guards(id) ON DELETE CASCADE,
    CONSTRAINT fk_ext_att FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS shift_handovers (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id               INT UNSIGNED NULL,
    site_name               VARCHAR(150) NULL,
    outgoing_guard_id       INT UNSIGNED NOT NULL,
    outgoing_attendance_id  INT UNSIGNED NULL,
    incoming_guard_id       INT UNSIGNED NOT NULL,
    incoming_attendance_id  INT UNSIGNED NULL,
    items                   VARCHAR(500) NULL,
    notes                   VARCHAR(1000) NULL,
    lat                     DECIMAL(10,7) NULL,
    lng                     DECIMAL(10,7) NULL,
    status                  ENUM('pending','accepted','disputed') NOT NULL DEFAULT 'pending',
    created_at              DATETIME NOT NULL,
    responded_at            DATETIME NULL,
    incoming_note           VARCHAR(500) NULL,
    KEY idx_ho_in (incoming_guard_id, status),
    KEY idx_ho_out (outgoing_guard_id, created_at),
    KEY idx_ho_time (created_at),
    CONSTRAINT fk_ho_out FOREIGN KEY (outgoing_guard_id) REFERENCES guards(id) ON DELETE CASCADE,
    CONSTRAINT fk_ho_in FOREIGN KEY (incoming_guard_id) REFERENCES guards(id) ON DELETE CASCADE,
    CONSTRAINT fk_ho_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    q("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('require_selfie', '1'), ('selfie_retention_days', '90'), ('late_grace_min', '15'),
        ('default_shift_hours', '8'), ('ot_min_minutes', '15'), ('no_movement_min', '60')");
}
