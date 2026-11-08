# Database Migration Guide

Folder ini berisi berkas migrasi SQL untuk membangun struktur database GNSG Intelligence Core.

Migration digunakan untuk membuat tabel, relasi, role, permission, dan data awal yang dibutuhkan oleh sistem.

## 1. Migration Files

Jalankan seluruh migration sesuai urutan berikut:

| Urutan | File | Fungsi |
|---|---|---|
| 001 | `001_identity_access.sql` | Struktur identitas dan akses pengguna |
| 002 | `002_role_relationships.sql` | Relasi role dan permission |
| 003 | `003_telegram_identities.sql` | Identitas Telegram |
| 004 | `004_audit_logs.sql` | Pencatatan aktivitas sistem |
| 005 | `005_approval_requests.sql` | Permintaan persetujuan |
| 006 | `006_agent_sessions_service_registry.sql` | Agent sessions dan service registry |
| 007 | `007_initial_rbac_seed.sql` | Data awal role dan permission |
| 008 | `008_initial_owner_account.sql` | Akun owner awal |

## 2. Database Preparation

Sebelum menjalankan migration, buat database tujuan terlebih dahulu.

Contoh:

```sql
CREATE DATABASE yourdatabase
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;
```

Setiap file migration menggunakan:

```sql
USE yourdatabase;
```

`yourdatabase` merupakan placeholder.

Ganti placeholder tersebut dengan nama database yang benar-benar dibuat pada lingkungan masing-masing sebelum migration dijalankan.

Jangan menggunakan placeholder secara literal kecuali database tersebut memang dibuat dengan nama `yourdatabase`.

## 3. Download Project Files

1. Buka repository GNSG Intelligence Core di GitHub.
2. Download atau clone repository ke laptop.
3. Buka folder project.
4. Masuk ke direktori `migrations`.
5. Pastikan seluruh file SQL tersedia dan berurutan.

## 4. Transfer Migration to Ubuntu Server

Jika menggunakan FileZilla:

1. Hubungkan FileZilla ke Ubuntu Server melalui SFTP.
2. Buka folder migration pada laptop.
3. Buka direktori project pada Ubuntu Server.
4. Masuk ke folder `migrations` di server.
5. Transfer seluruh file SQL dari laptop ke server.
6. Pastikan seluruh file berhasil dipindahkan.

Contoh direktori server:


/home/username/gnsg-core/migrations/


Sesuaikan lokasi dengan direktori project masing-masing.

## 5. Execute Migration

Jalankan migration menggunakan MariaDB atau MySQL client.

Contoh:

```bash
mariadb -u your_database_user -p yourdatabase < 001_identity_access.sql
```

Lanjutkan sesuai urutan:

```text
001_identity_access.sql
002_role_relationships.sql
003_telegram_identities.sql
004_audit_logs.sql
005_approval_requests.sql
006_agent_sessions_service_registry.sql
007_initial_rbac_seed.sql
008_initial_owner_account.sql
```

Pastikan nama database pada command sesuai dengan database tujuan.

Setiap migration harus diperiksa hasilnya sebelum melanjutkan ke migration berikutnya.

## 6. Verification

Setelah seluruh migration berhasil dijalankan:

```sql
USE yourdatabase;

SHOW TABLES;
```

Periksa jumlah dan struktur tabel yang terbentuk.

Pastikan relasi foreign key, data role, permission, dan akun owner sesuai dengan definisi migration.

## 7. Security Guidelines

- Jalankan migration sesuai urutan.
- Setiap migration dirancang untuk diterapkan satu kali.
- Lakukan backup sebelum perubahan database.
- Jangan menjalankan migration contoh langsung pada database produksi.
- Jangan menyimpan password, API key, token, hash produksi, atau konfigurasi privat di repository.
- Akun owner awal dibuat dalam status `disabled`.
- Jangan mengaktifkan akun owner sebelum mekanisme autentikasi dan verifikasi identitas diterapkan serta diuji.
- Gunakan akun database dengan hak akses sesuai kebutuhan.

## 8. Migration Management

Catat setiap migration yang berhasil diterapkan pada lingkungan pengembangan maupun produksi.

Jangan menjalankan ulang migration yang sudah diterapkan tanpa prosedur pemulihan yang telah ditinjau.

Setiap perubahan struktur database berikutnya harus dibuat sebagai migration baru agar riwayat perubahan tetap terjaga.

---

**GNSG Intelligence Core**

Building from Zero, Running with Intelligence.