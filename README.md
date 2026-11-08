# GNSG Intelligence Core

**Membangun Sistem Jaringan & Komunikasi Serta Menjalankannya dengan Kecerdasan.**

GNSG Intelligence Core merupakan proyek pengembangan pusat kendali dan kecerdasan buatan untuk mendukung pengelolaan infrastruktur teknologi informasi Ganesha Sistem Gemilang.

## 1. Visi Proyek

Membangun sistem asisten AI terintegrasi yang mampu memahami kondisi infrastruktur dan melakukan pemantauan/monitoring Jaringan, menganalisis permasalahan Server dan Mikrotik, serta membantu menjalankan tugas operasional berdasarkan hak akses dan kebijakan keamanan yang telah ditentukan.

Sistem ini dirancang untuk menghubungkan kecerdasan buatan dengan infrastruktur operasional Jaringan dan Web Server melalui lapisan backend yang terkontrol.

## 2. Tujuan Utama

- Membangun pusat kendali infrastruktur berbasis AI.
- Mengintegrasikan AI dengan sistem operasional GNSG.
- Menyediakan administrasi melalui Telegram.
- Memisahkan akses administrator dan pelanggan.
- Menerapkan autentikasi dan Role-Based Access Control (RBAC).
- Menyediakan pencatatan aktivitas dan sistem persetujuan.
- Mengutamakan keamanan, keterlacakan, dan kendali manusia.

## 3. Gambaran Arsitektur

Arsitektur yang direncanakan terdiri dari:

- **AI Engine:** pusat penalaran dan kecerdasan sistem.
- **Orchestrator:** mengelola percakapan, tugas, dan penggunaan alat.
- **GNSG Core Backend:** menangani autentikasi, otorisasi, layanan, audit, dan persetujuan.
- **Telegram Interface:** menyediakan jalur interaksi administrator dan pelanggan.
- **Infrastructure Layer:** menghubungkan sistem dengan server, basis data, jaringan, dan layanan operasional.

## 4. Prinsip Keamanan

Keamanan menjadi bagian utama dalam setiap tahap pembangunan.

- Penerapan hak akses berdasarkan peran dan izin.
- Pembatasan akses sesuai prinsip hak minimum.
- Persetujuan manusia untuk tindakan sensitif.
- Pencatatan aktivitas melalui audit log.
- Pemisahan kredensial dan kode sumber.
- Larangan memberikan akses shell tanpa pembatasan kepada AI.
- Validasi setiap tindakan sebelum diteruskan ke infrastruktur.

## 5. Teknologi

Teknologi yang direncanakan dalam proyek ini meliputi:

- PHP dan PDO.
- MariaDB.
- Telegram Bot API.
- Model AI berbasis Gemini.
- Hermes sebagai AI Agent.
- Ubuntu Server.
- Integrasi layanan jaringan dan infrastruktur.


Setiap direktori memiliki tanggung jawab tersendiri dalam pengembangan sistem.

## 6. Status Pengembangan

**Status: Dalam tahap pembangunan aktif.**

Fondasi awal yang telah dibangun meliputi:

- Struktur basis data awal.
- Struktur Role-Based Access Control.
- Akun pemilik awal dalam status nonaktif.
- Fondasi koneksi basis data.
- Fondasi pencatatan audit.
- Struktur direktori backend.

Modul AI, autentikasi lengkap, integrasi Telegram, pemantauan infrastruktur, dan sistem persetujuan masih dalam tahap pengembangan.

Keberadaan direktori atau nama berkas belum berarti fitur terkait sudah selesai diimplementasikan.

## 7. Pengembangan dan Kontribusi

Proyek ini dikembangkan secara bertahap dengan mengutamakan keamanan, pengujian, dokumentasi, dan verifikasi sebelum setiap perubahan diterapkan.

Untuk pedoman pengembangan, silakan merujuk ke `CONTRIBUTING.md`.

## 8. Keamanan dan Penggunaan

Repository ini tidak boleh memuat kredensial produksi, token API, hash kata sandi, data pelanggan, cadangan basis data, atau konfigurasi privat.

Untuk pelaporan kerentanan keamanan, silakan merujuk ke `SECURITY.md`.

---

**GNSG Intelligence Core**

Dikembangkan untuk Mohammad Jibran N.A Budianto.
