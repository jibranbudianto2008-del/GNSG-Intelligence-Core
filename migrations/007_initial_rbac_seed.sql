USE yourdatabase;

START TRANSACTION;

INSERT INTO roles (role_key, role_name, description) VALUES
('super_admin', 'Super Administrator', 'Pemilik utama dan pengendali tertinggi GNSG Core'),
('admin', 'Administrator', 'Administrator operasional GNSG'),
('operator', 'Operator', 'Petugas monitoring dan operasional terbatas'),
('customer', 'Customer', 'Pengguna layanan pelanggan');

INSERT INTO permissions (permission_key, description) VALUES
('system.health.read', 'Melihat kesehatan server'),
('system.service.read', 'Melihat status layanan'),
('system.service.restart', 'Meminta restart layanan'),
('database.status.read', 'Melihat status database'),
('database.backup.create', 'Meminta pembuatan backup database'),
('users.read', 'Melihat daftar pengguna'),
('users.manage', 'Mengelola pengguna'),
('roles.read', 'Melihat role dan permission'),
('roles.manage', 'Mengelola role dan permission'),
('approvals.read', 'Melihat permintaan persetujuan'),
('approvals.decide', 'Menyetujui atau menolak permintaan'),
('audit_logs.read', 'Melihat catatan audit'),
('services.read', 'Melihat katalog layanan'),
('services.manage', 'Mengelola katalog layanan'),
('customer.services.read', 'Mengakses informasi layanan pelanggan');

-- Super Admin: seluruh permission yang terdaftar.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_key = 'super_admin';

-- Admin: administrasi operasional tanpa hak persetujuan.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_key = 'admin'
AND p.permission_key IN (
    'system.health.read',
    'system.service.read',
    'database.status.read',
    'users.read',
    'users.manage',
    'roles.read',
    'approvals.read',
    'audit_logs.read',
    'services.read'
);

-- Operator: monitoring dan permintaan operasi terbatas.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_key = 'operator'
AND p.permission_key IN (
    'system.health.read',
    'system.service.read',
    'database.status.read',
    'approvals.read',
    'services.read'
);

-- Customer: akses layanan pelanggan.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_key = 'customer'
AND p.permission_key = 'customer.services.read';

COMMIT;

