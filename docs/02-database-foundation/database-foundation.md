# GNSG INTELLIGENCE CORE
# DATABASE FOUNDATION

**Organization:** Ganesha Sistem Gemilang  
**Project:** GNSG Intelligence Core  
**Document:** Database Foundation  
**Status:** Initial Database Foundation Established  
**Database Engine:** MariaDB  
**Character Set:** utf8mb4

---

## 1. Tujuan

Dokumen ini menjelaskan fondasi database GNSG Intelligence Core.

Database menjadi pusat penyimpanan informasi yang dibutuhkan oleh backend untuk mengelola:

- Identitas pengguna.
- Role dan permission.
- Hubungan identitas Telegram.
- Audit aktivitas sistem.
- Permintaan persetujuan tindakan.
- Sesi agent AI.
- Registrasi layanan infrastruktur.

Struktur database dirancang agar dapat dikembangkan secara bertahap dan tetap mengikuti prinsip keamanan serta integritas data.

## 2. Database Environment

Database engine yang digunakan adalah MariaDB.

Informasi lingkungan development:

| Komponen | Informasi |
|---|---|
| Database Engine | MariaDB |
| Version | 11.8.6 |
| Storage Engine | InnoDB |
| Character Set | utf8mb4 |
| Collation | utf8mb4_general_ci |
| Application Access | PDO |
| Database Protocol | Localhost connection |

Database inti proyek menggunakan nama:

`ganeshasistemgemilang`

**Catatan:** Nama database production tersebut hanya untuk dokumentasi internal. Versi repository publik menggunakan nama contoh `gnsg_core`.

## 3. Database Architecture Principles

Fondasi database mengikuti prinsip:

1. **Data Integrity** — relasi dan batasan data harus dijaga.
2. **Referential Integrity** — hubungan antar-tabel menggunakan foreign key.
3. **Least Privilege** — akun aplikasi hanya memperoleh hak akses yang diperlukan.
4. **Migration-Based Management** — perubahan struktur dilakukan melalui migration.
5. **Credential Separation** — credential tidak ditulis dalam migration atau source code.
6. **Auditability** — aktivitas penting memiliki catatan yang dapat ditelusuri.
7. **Controlled Schema Changes** — perubahan schema harus melalui proses pemeriksaan dan pengujian.

## 4. Database Structure

Fondasi awal memiliki 10 tabel yang dikelompokkan berdasarkan fungsi.

### 4.1 Identity and Access Management

| Table | Fungsi |
|---|---|
| `users` | Menyimpan identitas dan status pengguna |
| `roles` | Menyimpan definisi role |
| `permissions` | Menyimpan daftar permission |
| `user_roles` | Menghubungkan pengguna dengan role |
| `role_permissions` | Menghubungkan role dengan permission |
| `telegram_identities` | Menghubungkan akun internal dengan identitas Telegram |

### 4.2 Security and Operational Management

| Table | Fungsi |
|---|---|
| `audit_logs` | Mencatat aktivitas penting sistem |
| `approval_requests` | Menyimpan permintaan dan keputusan approval |
| `agent_sessions` | Menyimpan informasi sesi agent |
| `service_registry` | Mencatat layanan yang dikelola sistem |

## 5. Table Design Overview

### 5.1 users

Menyimpan informasi pengguna internal.

Data utama:

- id
- username
- email
- password_hash
- display_name
- status
- created_at
- updated_at

Status akun:

- active
- disabled
- locked

Password disimpan menggunakan password hashing, bukan plaintext.

### 5.2 roles

Menyimpan definisi role yang digunakan sistem.

Role awal:

- super_admin
- admin
- operator
- customer

### 5.3 permissions

Menyimpan daftar permission yang menjadi dasar RBAC.

Contoh:

- system.health.read
- system.service.read
- system.service.restart
- database.status.read
- database.backup.create
- users.read
- users.manage
- roles.read
- roles.manage
- approvals.read
- approvals.decide
- audit_logs.read
- services.read
- services.manage
- customer.services.read

Permission dapat dikembangkan sesuai kebutuhan sistem.

### 5.4 user_roles

Menghubungkan pengguna dengan satu atau beberapa role.

Menggunakan composite primary key:

- user_id
- role_id

Relasi menggunakan foreign key dengan cascade delete.

### 5.5 role_permissions

Menghubungkan role dengan permission yang dimiliki.

Menggunakan composite primary key:

- role_id
- permission_id

Relasi menggunakan foreign key dengan cascade delete.

### 5.6 telegram_identities

Menyimpan hubungan antara akun internal dan identitas Telegram.

Informasi utama:

- user_id
- telegram_user_id
- telegram_username
- is_verified
- created_at

Identitas Telegram harus melalui mekanisme verifikasi yang dikendalikan backend.

Nilai `is_verified` tidak boleh dipercaya hanya karena dikirim oleh pengguna atau AI.

### 5.7 audit_logs

Mencatat aktivitas penting sistem.

Informasi utama:

- user_id
- action
- resource_type
- resource_id
- status
- details
- ip_address
- created_at

Status yang tersedia:

- success
- failed
- denied
- pending

Informasi sensitif tidak boleh disimpan secara terbuka dalam audit log.

### 5.8 approval_requests

Menyimpan permintaan persetujuan tindakan operasional.

Informasi utama:

- requested_by
- approved_by
- action
- payload
- status
- reason
- requested_at
- decided_at
- expires_at
- executed_at

Status yang tersedia:

- pending
- approved
- rejected
- expired
- executed
- failed

Backend bertanggung jawab memvalidasi identitas pemohon, pemberi persetujuan, payload, masa berlaku, dan penggunaan approval.

### 5.9 agent_sessions

Menyimpan informasi sesi AI.

Channel yang tersedia:

- telegram_admin
- telegram_customer
- web
- api
- system

Informasi utama:

- user_id
- channel
- external_session_id
- provider
- model
- status
- metadata
- created_at
- updated_at

Tabel ini menjadi fondasi pencatatan sesi dan penggunaan model AI.

### 5.10 service_registry

Menyimpan informasi layanan yang dapat dikelola sistem.

Informasi utama:

- service_key
- display_name
- description
- category
- environment
- risk_level
- requires_approval
- is_enabled

Tingkat risiko:

- low
- medium
- high
- critical

Registrasi layanan tidak berarti AI otomatis memiliki izin untuk mengeksekusinya.

Otorisasi tetap dilakukan oleh backend.

## 6. Database Relationship

Hubungan utama antar-tabel:

```text
users
  |
  +---- user_roles ---- roles
  |                       |
  |                       +---- role_permissions ---- permissions
  |
  +---- telegram_identities
  |
  +---- audit_logs
  |
  +---- approval_requests
  |
  +---- agent_sessions
```

`service_registry` berdiri sebagai tabel registrasi layanan dan belum memiliki foreign key ke tabel identitas.

Relasi foreign key digunakan untuk menjaga konsistensi data antar-tabel.

## 7. Migration Management

Struktur database dibangun melalui migration bernomor.

Migration yang telah dibuat:

| Migration | Fungsi |
|---|---|
| 001 | Identity and Access Tables |
| 002 | Role Relationships |
| 003 | Telegram Identities |
| 004 | Audit Logs |
| 005 | Approval Requests |
| 006 | Agent Sessions and Service Registry |
| 007 | Initial RBAC Seed |
| 008 | Initial Owner Account |

Migration 001–008 telah dijalankan dan diverifikasi pada database production development.

### Migration Rules

- Migration dijalankan sesuai urutan.
- Migration yang sudah berhasil tidak dijalankan ulang secara sembarangan.
- Perubahan schema dibuat melalui migration baru.
- Migration harus diperiksa sebelum dieksekusi.
- Backup harus dipertimbangkan sebelum perubahan berisiko.
- Migration production dan repository publik harus menggunakan konfigurasi yang sesuai dengan lingkungannya.

## 8. Initial RBAC Seed

Migration 007 telah memasukkan empat role dan 15 permission awal.

Jumlah permission berdasarkan hasil verifikasi:

| Role | Permission Count |
|---|---:|
| super_admin | 15 |
| admin | 9 |
| operator | 5 |
| customer | 1 |

Pembagian hak akses mengikuti prinsip least privilege.

Role `admin` tidak memperoleh permission `approvals.decide` pada konfigurasi awal.

Permission tersebut diberikan kepada `super_admin`.

Seed awal ini belum menggantikan enforcement RBAC pada backend. Setiap akses tetap harus diperiksa oleh aplikasi.

## 9. Initial Owner Account

Migration 008 membuat akun owner awal.

Karakteristik akun:

- Username internal awal: `gnsg-owner`
- Role: `super_admin`
- Status awal: `disabled`
- Password hash: diisi melalui proses terpisah.
- Identitas Telegram: belum dihubungkan.

Password diverifikasi menggunakan Argon2id dan pengujian `password_verify()` berhasil.

Akun harus tetap disabled sampai mekanisme pengaitan identitas Telegram dan autentikasi selesai dibuat serta diuji.

Credential dan password hash production tidak boleh dimasukkan ke repository publik.

## 10. Database Access Control

Akses database dipisahkan berdasarkan tanggung jawab.

### Administrative Access

Digunakan untuk:

- Database administration.
- Schema management.
- Migration execution.
- Maintenance yang telah diotorisasi.

### Application Runtime Access

Aplikasi menggunakan akun runtime khusus.

Akun:

`gnsg_core_runtime@localhost`

Hak akses yang diberikan dibatasi pada tabel yang diperlukan aplikasi.

Akun runtime tidak memiliki hak:

- DELETE.
- CREATE.
- ALTER.
- DROP.
- ALL PRIVILEGES global.

Dengan demikian, aplikasi tidak menggunakan akun administrator database untuk operasi sehari-hari.

## 11. Database Connection

Backend menggunakan PDO untuk terhubung ke MariaDB.

Prinsip koneksi:

- Menggunakan konfigurasi eksternal.
- Menggunakan runtime account.
- Menggunakan prepared statements.
- Mengaktifkan exception-based error handling.
- Menonaktifkan persistent connection pada implementasi awal.
- Tidak menampilkan credential atau detail koneksi sensitif kepada pengguna.

Koneksi database telah diuji melalui PHP-FPM dengan hasil berhasil.

## 12. Integrity and Validation

Pemeriksaan awal telah dilakukan terhadap:

- Jumlah tabel.
- Struktur tabel.
- Primary key.
- Foreign key.
- Index.
- Relasi antar-tabel.
- Engine tabel.
- Character set.
- Collation.

Hasil verifikasi:

- 10 tabel inti tersedia.
- Seluruh tabel menggunakan InnoDB.
- 9 foreign key terdeteksi.
- Relasi yang dirancang telah diperiksa.
- Tidak ditemukan anomali struktural dari pemeriksaan awal.

Pemeriksaan ini merupakan validasi fondasi awal, bukan pengganti pengujian database menyeluruh.

## 13. Security Considerations

Beberapa kontrol keamanan yang harus diterapkan pada backend:

### 13.1 User Data Protection

- Password menggunakan password hashing.
- Data autentikasi tidak boleh dicatat sebagai plaintext.
- Status akun harus divalidasi sebelum login berhasil.

### 13.2 Telegram Identity Protection

- Identitas Telegram harus diverifikasi.
- Pengaitan identitas harus menggunakan challenge yang aman.
- Challenge harus memiliki masa berlaku dan penggunaan satu kali.
- Perubahan identitas harus dicatat.

### 13.3 Audit Log Protection

- Detail sensitif harus disaring.
- Hak akses audit dibatasi.
- Aktivitas penting harus tercatat.
- Kegagalan audit pada operasi sensitif harus ditangani secara fail-closed.

### 13.4 Approval Protection

- Approval harus terkait dengan payload yang spesifik.
- Perubahan payload setelah approval harus ditolak.
- Approval tidak boleh digunakan berulang.
- Masa berlaku harus diperiksa.
- Pemisahan pemohon dan pemberi persetujuan harus diterapkan sesuai kebijakan.

### 13.5 Runtime Database Protection

- Gunakan akun runtime khusus.
- Hindari hak administratif pada akun aplikasi.
- Jangan menyimpan password database dalam repository.
- Batasi akses terhadap konfigurasi eksternal.

## 14. Current Completion Status

Fondasi database yang telah selesai:

- [x] Migration 001–008 dibuat.
- [x] Sepuluh tabel inti berhasil dibuat.
- [x] Relasi foreign key diverifikasi.
- [x] Initial RBAC seed berhasil dimasukkan.
- [x] Empat role awal diverifikasi.
- [x] Lima belas permission awal diverifikasi.
- [x] Initial owner account dibuat.
- [x] Password hash owner diverifikasi.
- [x] Runtime database account dibuat.
- [x] Runtime privileges diperiksa.
- [x] PDO connection berhasil diuji.

Pekerjaan yang belum selesai:

- [ ] Migration tracking mechanism.
- [ ] Challenge-based Telegram identity verification.
- [ ] Backend RBAC enforcement.
- [ ] Approval workflow enforcement.
- [ ] Database backup automation.
- [ ] Database recovery testing.
- [ ] Database performance review.
- [ ] Production security validation.

## 15. Future Database Development

Pengembangan database berikutnya dapat mencakup:

- Migration tracking table.
- Identity verification challenge storage.
- Expanded agent execution records.
- Provider usage tracking.
- Model usage and cost records.
- Operational execution history.
- Monitoring history.
- Security event records.

Setiap penambahan tabel harus melalui evaluasi kebutuhan, relasi, permission, dan dampak terhadap sistem.

---

**GNSG INTELLIGENCE CORE — Building from Zero, Running with Intelligence.**