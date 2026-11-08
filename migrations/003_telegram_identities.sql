USE yourdatabase;

CREATE TABLE telegram_identities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    telegram_user_id BIGINT UNSIGNED NOT NULL,
    telegram_username VARCHAR(64) NULL,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_telegram_user_id (telegram_user_id),

    KEY idx_telegram_identity_user (user_id),

    CONSTRAINT fk_telegram_identity_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

