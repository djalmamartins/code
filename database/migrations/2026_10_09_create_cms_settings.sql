CREATE TABLE IF NOT EXISTS cms_settings (
    setting_key VARCHAR(64) NOT NULL,
    setting_value TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    KEY cms_settings_updated_by_index (updated_by),
    CONSTRAINT cms_settings_user_foreign FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
