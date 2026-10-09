CREATE TABLE IF NOT EXISTS cms_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_key VARCHAR(190) NOT NULL,
    title VARCHAR(160) NOT NULL,
    message VARCHAR(500) NOT NULL,
    target_path VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cms_notifications_recipient_event_unique (recipient_user_id, event_key),
    KEY cms_notifications_recipient_unread_index (recipient_user_id, read_at, created_at),
    CONSTRAINT cms_notifications_recipient_foreign FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT cms_notifications_actor_foreign FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
