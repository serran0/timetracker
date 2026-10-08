-- Timetracker 0.2 schema (schema number 2; see src/Migrator.php for upgrades from older installs) (MySQL 5.7+/8.x, MariaDB 10.3+)
-- Every user-owned table carries user_id: each user has an isolated environment.
-- Statements are separated by a semicolon at the end of a line.

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(64)  NOT NULL,
    display_name  VARCHAR(120) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    timezone      VARCHAR(64)  NOT NULL DEFAULT 'UTC',
    currency      VARCHAR(8)   NOT NULL DEFAULT 'EUR',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME     NULL,
    lunch_start   TIME         NULL COMMENT 'default unpaid break window',
    lunch_end     TIME         NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clients (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(160) NOT NULL,
    reference   VARCHAR(160) NULL,
    color       CHAR(7)      NOT NULL DEFAULT '#4f46e5',
    hourly_rate DECIMAL(10,2) NULL,
    notes       TEXT         NULL,
    is_archived TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clients_user_name (user_id, name),
    CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE actions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(120) NOT NULL,
    color           CHAR(7)      NOT NULL DEFAULT '#10b981',
    rate_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    is_billable     TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      INT          NOT NULL DEFAULT 0,
    is_archived     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_actions_user_name (user_id, name),
    CONSTRAINT fk_actions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE working_hours (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    weekday    TINYINT UNSIGNED NOT NULL COMMENT '1 = Monday ... 7 = Sunday',
    start_time TIME NOT NULL,
    end_time   TIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_working_hours_user (user_id, weekday),
    CONSTRAINT fk_working_hours_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE time_entries (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    client_id   INT UNSIGNED NOT NULL,
    action_id   INT UNSIGNED NOT NULL,
    entry_date  DATE NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL COMMENT 'may be 24:00:00 for "until midnight"',
    break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'unpaid break deducted from the time span',
    description TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_entries_user_date (user_id, entry_date),
    KEY idx_entries_client (client_id),
    KEY idx_entries_action (action_id),
    CONSTRAINT fk_entries_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT fk_entries_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT,
    CONSTRAINT fk_entries_action FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username     VARCHAR(64) NOT NULL,
    ip           VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_username (username, attempted_at),
    KEY idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_meta (
    meta_key   VARCHAR(64)  NOT NULL,
    meta_value VARCHAR(255) NOT NULL,
    PRIMARY KEY (meta_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
