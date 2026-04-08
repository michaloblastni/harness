-- Run against an existing Harness MySQL database (optional one-time migration).

ALTER TABLE victim
    MODIFY COLUMN notify_email_on_new_link TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS victim_link_notification (
    notifier_victim_id BIGINT NOT NULL,
    peer_victim_id BIGINT NOT NULL,
    notified_at DATETIME NOT NULL,
    PRIMARY KEY (notifier_victim_id, peer_victim_id),
    CONSTRAINT fk_victim_link_notification_notifier FOREIGN KEY (notifier_victim_id) REFERENCES victim (id) ON DELETE CASCADE,
    CONSTRAINT fk_victim_link_notification_peer FOREIGN KEY (peer_victim_id) REFERENCES victim (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
