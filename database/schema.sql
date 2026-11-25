-- Security Agency Management System - database schema
-- Compatible with MySQL 5.7+/8.x and MariaDB 10.3+ (Hostinger)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS admins (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guards (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_code           VARCHAR(20)  NOT NULL,
    category                ENUM('bodyguard','building_security','agency_security','event_security','patrol') NOT NULL DEFAULT 'bodyguard',
    full_name               VARCHAR(120) NOT NULL,
    phone                   VARCHAR(20)  NOT NULL,
    email                   VARCHAR(150) NULL,
    password_hash           VARCHAR(255) NOT NULL,
    gender                  ENUM('male','female','other') NULL,
    dob                     DATE NULL,
    blood_group             VARCHAR(5) NULL,
    address                 TEXT NULL,
    city                    VARCHAR(80) NULL,
    id_proof_type           VARCHAR(40) NULL,
    id_proof_number         VARCHAR(60) NULL,
    photo                   VARCHAR(255) NULL,
    experience_years        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_armed                TINYINT(1) NOT NULL DEFAULT 0,
    weapon_license_no       VARCHAR(60) NULL,
    skills                  VARCHAR(255) NULL,
    emergency_contact_name  VARCHAR(120) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    joined_on               DATE NULL,
    status                  ENUM('active','on_leave','inactive') NOT NULL DEFAULT 'active',
    is_on_duty              TINYINT(1) NOT NULL DEFAULT 0,
    last_lat                DECIMAL(10,7) NULL,
    last_lng                DECIMAL(10,7) NULL,
    last_accuracy           FLOAT NULL,
    last_speed              FLOAT NULL,
    last_battery            TINYINT NULL,
    last_seen_at            DATETIME NULL,
    last_ping_at            DATETIME NULL,
    gps_state               ENUM('ok','off','denied','unknown') NOT NULL DEFAULT 'unknown',
    gps_state_at            DATETIME NULL,
    remember_hash           CHAR(64) NULL,
    remember_expires        DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_guard_code (employee_code),
    UNIQUE KEY uq_guard_phone (phone),
    KEY idx_guard_category (category),
    KEY idx_guard_status (status),
    KEY idx_guard_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_code     VARCHAR(20)  NOT NULL,
    name            VARCHAR(150) NOT NULL,
    client_type     ENUM('individual','corporate','event','residential','other') NOT NULL DEFAULT 'individual',
    contact_person  VARCHAR(120) NULL,
    phone           VARCHAR(20)  NOT NULL,
    alt_phone       VARCHAR(20)  NULL,
    email           VARCHAR(150) NULL,
    address         TEXT NULL,
    city            VARCHAR(80) NULL,
    state           VARCHAR(80) NULL,
    pincode         VARCHAR(12) NULL,
    gstin           VARCHAR(20) NULL,
    notes           TEXT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_code (client_code),
    KEY idx_client_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A guard "hired" by a client. Only ONE active assignment per guard is allowed
-- (enforced by the unique index on the generated column active_guard_id).
CREATE TABLE IF NOT EXISTS assignments (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id           INT UNSIGNED NOT NULL,
    guard_id            INT UNSIGNED NOT NULL,
    site_name           VARCHAR(150) NOT NULL,
    site_address        VARCHAR(255) NULL,
    site_lat            DECIMAL(10,7) NULL,
    site_lng            DECIMAL(10,7) NULL,
    geofence_radius_m   INT UNSIGNED NOT NULL DEFAULT 300,
    start_date          DATE NOT NULL,
    end_date            DATE NULL,
    shift_start         TIME NULL,
    shift_end           TIME NULL,
    rate_type           ENUM('daily','hourly','monthly') NOT NULL DEFAULT 'daily',
    rate                DECIMAL(10,2) NOT NULL DEFAULT 0,
    status              ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    notes               TEXT NULL,
    created_by          INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at            DATETIME NULL,
    active_guard_id     INT UNSIGNED AS (IF(status = 'active', guard_id, NULL)) STORED,
    UNIQUE KEY uq_one_active_per_guard (active_guard_id),
    KEY idx_asg_guard (guard_id, status),
    KEY idx_asg_client (client_id, status),
    CONSTRAINT fk_asg_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asg_guard  FOREIGN KEY (guard_id)  REFERENCES guards(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guard_id                INT UNSIGNED NOT NULL,
    assignment_id           INT UNSIGNED NULL,
    client_id               INT UNSIGNED NULL,
    check_in_at             DATETIME NOT NULL,
    check_in_lat            DECIMAL(10,7) NULL,
    check_in_lng            DECIMAL(10,7) NULL,
    check_in_accuracy       FLOAT NULL,
    check_in_distance_m     INT NULL,
    check_out_at            DATETIME NULL,
    check_out_lat           DECIMAL(10,7) NULL,
    check_out_lng           DECIMAL(10,7) NULL,
    check_out_distance_m    INT NULL,
    duration_minutes        INT UNSIGNED NULL,
    status                  ENUM('open','closed','auto_closed') NOT NULL DEFAULT 'open',
    closed_by_admin         INT UNSIGNED NULL,
    notes                   VARCHAR(255) NULL,
    check_in_selfie         VARCHAR(80) NULL,
    shift_start_at          DATETIME NULL,
    shift_end_at            DATETIME NULL,
    late_minutes            INT UNSIGNED NULL,
    overtime_minutes        INT UNSIGNED NULL,
    approved_ot_minutes     INT UNSIGNED NULL,
    KEY idx_att_guard (guard_id, check_in_at),
    KEY idx_att_asg (assignment_id, check_in_at),
    KEY idx_att_client (client_id, check_in_at),
    KEY idx_att_status (status),
    CONSTRAINT fk_att_guard FOREIGN KEY (guard_id) REFERENCES guards(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_asg   FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
    CONSTRAINT fk_att_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guard_locations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guard_id        INT UNSIGNED NOT NULL,
    assignment_id   INT UNSIGNED NULL,
    lat             DECIMAL(10,7) NOT NULL,
    lng             DECIMAL(10,7) NOT NULL,
    accuracy        FLOAT NULL,
    speed           FLOAT NULL,
    heading         FLOAT NULL,
    battery         TINYINT NULL,
    recorded_at     DATETIME NOT NULL,
    KEY idx_loc_guard_time (guard_id, recorded_at),
    KEY idx_loc_time (recorded_at),
    CONSTRAINT fk_loc_guard FOREIGN KEY (guard_id) REFERENCES guards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alerts (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type                ENUM('sos','geofence_exit','late','no_movement','gps_off','offline') NOT NULL,
    guard_id            INT UNSIGNED NOT NULL,
    assignment_id       INT UNSIGNED NULL,
    client_id           INT UNSIGNED NULL,
    lat                 DECIMAL(10,7) NULL,
    lng                 DECIMAL(10,7) NULL,
    distance_m          INT NULL,
    message             VARCHAR(255) NULL,
    status              ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
    created_at          DATETIME NOT NULL,
    acknowledged_by     INT UNSIGNED NULL,
    acknowledged_at     DATETIME NULL,
    resolved_by         INT UNSIGNED NULL,
    resolved_at         DATETIME NULL,
    resolution_note     VARCHAR(255) NULL,
    KEY idx_alert_status (status, created_at),
    KEY idx_alert_guard (guard_id, type, status),
    CONSTRAINT fk_alert_guard FOREIGN KEY (guard_id) REFERENCES guards(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_asg FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
    CONSTRAINT fk_alert_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no      VARCHAR(30) NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    period_from     DATE NULL,
    period_to       DATE NULL,
    issued_on       DATE NOT NULL,
    due_on          DATE NULL,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_percent     DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid     DECIMAL(12,2) NOT NULL DEFAULT 0,
    status          ENUM('draft','sent','paid','partially_paid','cancelled') NOT NULL DEFAULT 'draft',
    notes           TEXT NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_no (invoice_no),
    KEY idx_inv_client (client_id),
    KEY idx_inv_status (status),
    CONSTRAINT fk_inv_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id      INT UNSIGNED NOT NULL,
    assignment_id   INT UNSIGNED NULL,
    description     VARCHAR(255) NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit            VARCHAR(20) NOT NULL DEFAULT 'day',
    rate            DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order      INT NOT NULL DEFAULT 0,
    KEY idx_item_inv (invoice_id),
    CONSTRAINT fk_item_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_asg FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key     VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value   TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type  ENUM('admin','guard','system') NOT NULL,
    actor_id    INT UNSIGNED NULL,
    action      VARCHAR(60) NOT NULL,
    details     VARCHAR(500) NULL,
    ip          VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL,
    KEY idx_log_time (created_at),
    KEY idx_log_actor (actor_type, actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip              VARCHAR(45) NOT NULL,
    identifier      VARCHAR(150) NOT NULL,
    attempted_at    DATETIME NOT NULL,
    KEY idx_attempt (ip, attempted_at),
    KEY idx_attempt_id (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    phone           VARCHAR(30)  NOT NULL,
    email           VARCHAR(150) NULL,
    service_type    VARCHAR(50)  NOT NULL DEFAULT 'bodyguard',
    is_armed        TINYINT(1)   NOT NULL DEFAULT 0,
    guards_needed   INT UNSIGNED NOT NULL DEFAULT 1,
    city            VARCHAR(80)  NULL,
    message         TEXT         NULL,
    status          ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('company_name',            'Security Agency'),
    ('company_address',         ''),
    ('company_phone',           ''),
    ('company_email',           ''),
    ('company_gstin',           ''),
    ('currency_symbol',         '₹'),
    ('tax_label',               'GST'),
    ('tax_percent',             '18'),
    ('invoice_prefix',          'INV-'),
    ('invoice_due_days',        '15'),
    ('invoice_terms',           'Payment due within 15 days of invoice date.'),
    ('tracking_interval_sec',   '20'),
    ('offline_after_min',       '3'),
    ('location_retention_days', '60'),
    ('map_default_lat',         '20.5937'),
    ('map_default_lng',         '78.9629'),
    ('map_default_zoom',        '5'),
    ('checkin_max_distance_m',  '500'),
    ('last_maintenance_at',     '0'),
    ('require_selfie',          '1'),
    ('selfie_retention_days',   '90'),
    ('late_grace_min',          '15'),
    ('default_shift_hours',     '8'),
    ('ot_min_minutes',          '15'),
    ('no_movement_min',         '60'),
    ('schema_version',          '2');
