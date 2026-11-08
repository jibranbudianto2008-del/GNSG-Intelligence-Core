USE yourdatabase;

START TRANSACTION;

INSERT INTO users (
    username,
    email,
    password_hash,
    display_name,
    status
) VALUES (
    'project-owner',
    NULL,
    NULL,
    'GNSG Owner',
    'disabled'
);

INSERT INTO user_roles (
    user_id,
    role_id
)
SELECT
    u.id,
    r.id
FROM users u
CROSS JOIN roles r
WHERE u.username = 'project-owner'
AND r.role_key = 'super_admin';

COMMIT;

