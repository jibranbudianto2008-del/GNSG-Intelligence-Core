USE yourdatabase;

CREATE TABLE approval_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    requested_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,

    action VARCHAR(100) NOT NULL,
    payload JSON NULL,

    status ENUM(
        'pending',
        'approved',
        'rejected',
        'expired',
        'executed',
        'failed'
    ) NOT NULL DEFAULT 'pending',

    reason VARCHAR(500) NULL,

    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME NULL,
    expires_at DATETIME NULL,
    executed_at DATETIME NULL,

    KEY idx_approval_status_created (status, requested_at),
    KEY idx_approval_requester (requested_by, requested_at),
    KEY idx_approval_approver (approved_by),

    CONSTRAINT fk_approval_requester
        FOREIGN KEY (requested_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_approval_approver
        FOREIGN KEY (approved_by)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

