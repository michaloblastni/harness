-- MySQL schema equivalent to Liquibase changelog (PostgreSQL → MySQL)

CREATE TABLE IF NOT EXISTS message (
    id BIGINT NOT NULL AUTO_INCREMENT,
    content VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY message_content_key (content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS victim (
    id BIGINT NOT NULL AUTO_INCREMENT,
    username VARCHAR(40) NOT NULL,
    password VARCHAR(255) NULL,
    email VARCHAR(255) NOT NULL,
    google_sub VARCHAR(255) NULL DEFAULT NULL,
    notify_email_on_new_link TINYINT(1) NOT NULL DEFAULT 1,
    password_reset_token_hash VARCHAR(64) NULL DEFAULT NULL,
    password_reset_expires DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY victim_username_key (username),
    UNIQUE KEY victim_google_sub_key (google_sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS victim_message (
    victim_id BIGINT NOT NULL,
    message_id BIGINT NOT NULL,
    saved_at DATETIME NOT NULL,
    PRIMARY KEY (victim_id, message_id),
    CONSTRAINT fk_victim_message_message FOREIGN KEY (message_id) REFERENCES message (id),
    CONSTRAINT fk_victim_message_victim FOREIGN KEY (victim_id) REFERENCES victim (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per directed pair (notifier was emailed about peer); avoids repeat mail when the same two users later share another line.
CREATE TABLE IF NOT EXISTS victim_link_notification (
    notifier_victim_id BIGINT NOT NULL,
    peer_victim_id BIGINT NOT NULL,
    notified_at DATETIME NOT NULL,
    PRIMARY KEY (notifier_victim_id, peer_victim_id),
    CONSTRAINT fk_victim_link_notification_notifier FOREIGN KEY (notifier_victim_id) REFERENCES victim (id) ON DELETE CASCADE,
    CONSTRAINT fk_victim_link_notification_peer FOREIGN KEY (peer_victim_id) REFERENCES victim (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
