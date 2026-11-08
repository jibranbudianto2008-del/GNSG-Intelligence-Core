# GNSG INTELLIGENCE CORE
# SERVER PREPARATION & ENVIRONMENT BASELINE

**Organization:** Ganesha Sistem Gemilang  
**Project:** GNSG Intelligence Core  
**Document:** Server Preparation  
**Status:** Initial Foundation Established  
**Environment:** Ubuntu Server  
**Timezone:** Asia/Jakarta

---

## 1. Tujuan

Dokumen ini mencatat persiapan lingkungan server yang menjadi fondasi pembangunan GNSG Intelligence Core.

Tujuan utamanya:

- Mendokumentasikan spesifikasi dan kondisi server.
- Mengidentifikasi layanan yang sudah berjalan.
- Menetapkan struktur direktori proyek.
- Mendokumentasikan pengaturan permission.
- Memisahkan konfigurasi aplikasi dari source code.
- Menjadi acuan pemeriksaan sebelum perubahan server berikutnya.

Server GNSG juga menjalankan layanan existing. Oleh karena itu, pembangunan Intelligence Core harus mempertahankan stabilitas layanan yang sudah digunakan.

## 2. Server Environment

Lingkungan server yang digunakan:

| Komponen | Informasi |
|---|---|
| Operating System | Ubuntu Server 26.04 LTS |
| Kernel | Linux 7.0.0-42-generic |
| Hostname | ganeshasistemgemilang |
| Architecture | Virtual Machine |
| CPU | Intel Core i5-7300U (virtualized) |
| Allocated CPU | 1 vCPU |
| RAM | Approximately 2.7 GiB |
| Swap | 2 GiB |
| Root Storage | 22 GB LVM |
| Network Interface | enp0s3 |
| Private IP | 192.168.xxx-xxx |
| Timezone | Asia/Jakarta |

**Catatan:** Informasi jaringan di atas merupakan informasi lingkungan internal.

## 3. Existing Services

Server telah memiliki beberapa layanan yang harus dipertahankan.

| Service | Fungsi |
|---|---|
| Nginx | Web server |
| MariaDB | Database server |
| PHP / PHP-FPM | Application runtime |
| Postfix | Mail transfer |
| Dovecot | Mail access |
| Roundcube | Webmail |
| BIND9 | DNS service |

Layanan tersebut merupakan bagian dari lingkungan existing GNSG.

## 4. Application Runtime

Lingkungan backend menggunakan PHP dan MariaDB.

Komponen yang telah diperiksa:

- PHP CLI.
- PHP-FPM.
- PDO.
- PDO MySQL.
- JSON.
- MBString.
- OpenSSL.

PHP-FPM menggunakan user dan group `www-data`.

Koneksi aplikasi ke database dilakukan menggunakan PDO dan akun database runtime khusus.

## 5. Project Directory Structure

Direktori utama proyek berada di:

`/home/jibran/gnsg-core`

Struktur awal:

gnsg-core/
├── app/
│   ├── Auth/
│   ├── Telegram/
│   ├── Security/
│   ├── Database/
│   └── Audit/
├── bootstrap/
├── config/
├── public/
├── routes/
├── storage/
│   └── logs/
└── migrations/

Direktori tersebut memisahkan kode aplikasi, konfigurasi, entry point, routing, penyimpanan log, dan database migrations.

Struktur dapat berkembang mengikuti kebutuhan implementasi.

## 6. Filesystem Permission Strategy

Akses filesystem diatur berdasarkan prinsip least privilege.

Ketentuan utama:

- Source code dimiliki oleh user pengembang.
- Web server tidak memperoleh hak tulis terhadap source code.
- Direktori log diberikan hak tulis kepada `www-data` sesuai kebutuhan.
- Direktori konfigurasi rahasia tidak ditempatkan di public directory.
- Hak akses harus diperiksa sebelum aplikasi menggunakan file atau direktori.

Direktori log aplikasi:

`/home/jibran/gnsg-core/storage/logs`

Direktori ini telah diuji agar dapat ditulis oleh `www-data`.

Web server tidak diberikan hak tulis terhadap seluruh direktori proyek.

## 7. External Configuration and Secrets

Konfigurasi database production dipisahkan dari source code.

Lokasi konfigurasi:

`/etc/gnsg/gnsg-core.env`

Ketentuan akses:

- Owner: root.
- Group: www-data.
- Permission: 640.
- Web server dapat membaca konfigurasi sesuai kebutuhan.
- Web server tidak dapat mengubah konfigurasi.

Informasi sensitif seperti password database, API key, dan Telegram token tidak boleh ditulis langsung dalam source code atau dokumentasi publik.

File konfigurasi production tidak boleh dipindahkan ke repository publik.

## 8. Database Runtime Access

GNSG Intelligence Core menggunakan akun database runtime khusus:

`gnsg_core_runtime@localhost`

Akun tersebut memiliki akses minimum terhadap tabel yang diperlukan aplikasi.

Prinsip permission:

- SELECT untuk membaca data yang dibutuhkan.
- INSERT untuk membuat record yang diperlukan.
- UPDATE untuk memperbarui data yang diizinkan.
- Tidak memiliki hak DELETE.
- Tidak memiliki hak CREATE, ALTER, atau DROP.
- Tidak memiliki ALL PRIVILEGES global.

Database schema management dilakukan melalui jalur administrasi dan migration yang terkontrol, bukan menggunakan akun runtime aplikasi.

## 9. Existing Service Protection

Setiap perubahan server harus mempertimbangkan layanan existing.

Sebelum perubahan:

1. Identifikasi service yang terdampak.
2. Periksa konfigurasi terkait.
3. Periksa dependensi.
4. Evaluasi risiko downtime.
5. Siapkan langkah pemulihan.
6. Minta persetujuan jika perubahan berisiko.

Dilarang melakukan tindakan destruktif tanpa persetujuan eksplisit.

Contoh tindakan yang membutuhkan pemeriksaan khusus:

- Reinstall service.
- Menghapus package.
- Mengubah konfigurasi Nginx.
- Mengubah konfigurasi MariaDB.
- Mengubah PHP-FPM pool.
- Mengubah DNS.
- Mengubah konfigurasi mail server.
- Menghapus atau memindahkan data.

## 10. Backup and Recovery Considerations

Sebelum perubahan besar, kondisi sistem harus dapat dipulihkan.

Prinsip yang digunakan:

- Backup sebelum perubahan berisiko.
- Verifikasi keberhasilan backup.
- Dokumentasikan perubahan.
- Simpan informasi pemulihan secara aman.
- Jangan menganggap snapshot sebagai pengganti seluruh strategi backup.

Snapshot yang tersedia pada tahap awal harus dipertahankan sampai kondisi sistem stabil dan kebutuhan pemulihan dievaluasi.

## 11. Operational Access

Akses administrasi server dilakukan melalui SSH menggunakan lingkungan administrasi yang telah disiapkan.

Pengembangan dan pemeriksaan dilakukan secara bertahap.

Setiap perintah yang berpotensi mengubah sistem harus diperiksa sebelum dijalankan.

Credential tidak boleh dibagikan melalui chat, log, atau dokumentasi.

## 12. Completion Status

Fondasi awal server telah disiapkan dengan hasil berikut:

- Ubuntu Server telah tersedia.
- Layanan existing telah diidentifikasi.
- Struktur direktori GNSG Core telah dibuat.
- Permission source code telah dibatasi.
- Permission direktori log telah diuji.
- Direktori konfigurasi eksternal telah dibuat.
- File konfigurasi database production telah dipisahkan dari source code.
- Runtime database account telah dibuat dan diuji.
- Koneksi database dari PHP-FPM telah berhasil diverifikasi.

Status ini tidak berarti seluruh aspek keamanan server telah selesai. Pemeriksaan dan hardening lanjutan tetap menjadi bagian dari tahapan pengembangan berikutnya.

## 13. Future Improvements

Peningkatan yang dapat dilakukan pada tahap berikutnya:

- Monitoring resource server.
- Monitoring service health.
- Backup automation.
- Log rotation review.
- Network access review.
- SSH security review.
- Security baseline validation.
- Disaster recovery testing.

Peningkatan dilakukan tanpa mengganggu layanan existing.

---

**GNSG INTELLIGENCE CORE — Building from Zero, Running with Intelligence.**