USE yourdatabase;

CREATE TABLE agent_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    channel ENUM(
        'telegram_admin',
        'telegram_customer',
        'web',
        'api',
        'system'
    ) NOT NULL,
    external_session_id VARCHAR(191) NULL,
    provider VARCHAR(64) NOT NULL,
    model VARCHAR(100) NOT NULL,
    status ENUM(
        'active',
        'closed',
        'expired',
        'failed'
    ) NOT NULL DEFAULT 'active',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    metadata JSON NULL,

    UNIQUE KEY uq_agent_channel_session (
        channel,
        external_session_id
    ),
    KEY idx_agent_user_activity (
        user_id,
        last_activity_at
    ),
    KEY idx_agent_status_activity (
        status,
        last_activity_at
    ),

    CONSTRAINT fk_agent_session_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;


CREATE TABLE service_registry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_key VARCHAR(64) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    category VARCHAR(64) NOT NULL,
    environment ENUM(
        'production',
        'staging',
        'development'
    ) NOT NULL DEFAULT 'production',
    risk_level ENUM(
        'low',
        'medium',
        'high',
        'critical'
    ) NOT NULL DEFAULT 'medium',
    requires_approval BOOLEAN NOT NULL DEFAULT TRUE,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_service_key (service_key),
    KEY idx_service_category (category),
    KEY idx_service_enabled_risk (is_enabled, risk_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

