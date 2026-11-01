CREATE TABLE IF NOT EXISTS cms_product_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product VARCHAR(24) NOT NULL,
    version_number VARCHAR(40) NOT NULL,
    release_name VARCHAR(160) NOT NULL,
    notes TEXT NOT NULL,
    published_by BIGINT UNSIGNED NOT NULL,
    published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY cms_product_versions_product_number_unique (product, version_number),
    KEY cms_product_versions_product_published_index (product, published_at),
    CONSTRAINT cms_product_versions_user_foreign FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
