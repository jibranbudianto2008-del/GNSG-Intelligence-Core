# ARSITEKTUR SISTEM
## GNSG Intelligence Core

### 1. Tujuan Arsitektur

GNSG Intelligence Core dirancang sebagai pusat kendali cerdas yang menghubungkan kecerdasan buatan dengan infrastruktur operasional Ganesha Sistem Gemilang.

Arsitektur sistem tidak bergantung pada satu model AI atau satu komponen saja. Sistem dirancang menggunakan pendekatan **Multi-Agent dan Multi-Model**, sehingga setiap tugas dapat ditangani oleh komponen yang sesuai berdasarkan jenis pekerjaan, kemampuan model, ketersediaan layanan, tingkat risiko, dan kebijakan keamanan.

Arsitektur juga menerapkan prinsip bahwa kecerdasan buatan tidak memiliki kewenangan langsung terhadap infrastruktur. Seluruh akses dan tindakan operasional harus melewati lapisan backend keamanan GNSG Intelligence Core.

---

### 2. Prinsip Arsitektur

Arsitektur GNSG Intelligence Core dibangun berdasarkan prinsip berikut:

1. **Multi-Agent**
   
   Sistem memiliki beberapa agen AI dengan tanggung jawab berdasarkan domain infrastruktur.

2. **Multi-Model**
   
   Sistem dapat menggunakan beberapa model AI dan tidak bergantung sepenuhnya pada satu penyedia.

3. **Task-Based Model Routing**
   
   Pemilihan model dilakukan berdasarkan jenis tugas dan kebutuhan, bukan hanya berdasarkan urutan prioritas.

4. **Fallback dan Resiliensi**
   
   Apabila model utama mengalami gangguan, keterbatasan kuota, rate limit, atau kegagalan layanan, sistem dapat menggunakan model alternatif yang telah disetujui.

5. **Security Gate**
   
   Seluruh tindakan terhadap infrastruktur harus melewati mekanisme autentikasi, otorisasi, validasi kebijakan, dan audit.

6. **Human Control**
   
   AI berfungsi sebagai asisten dan pengambil keputusan berbasis kebijakan, bukan sebagai pemilik kewenangan tanpa batas.

7. **Least Privilege**
   
   Setiap pengguna, agen, dan komponen sistem hanya memperoleh hak akses minimum yang diperlukan.

8. **Auditability**
   
   Aktivitas penting harus dapat ditelusuri melalui audit log.

9. **Staged Development**
   
   Kemampuan sistem diaktifkan secara bertahap setelah melalui pengujian dan validasi.

10. **Production Safety**
    
    Pengembangan dan pengujian tidak boleh mengganggu layanan produksi yang sudah berjalan.

---

### 3. Gambaran Arsitektur

Secara konseptual, GNSG Intelligence Core terdiri dari beberapa lapisan:

```text
┌──────────────────────────────────────────────────────┐
│                 COMMUNICATION LAYER                  │
│                                                      │
│       Telegram Admin       Telegram Customer        │
└──────────────────────┬───────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────┐
│                  ORCHESTRATION LAYER                 │
│                                                      │
│                    Hermes Agent                      │
│                                                      │
│   Conversation • Delegation • Task Management       │
└──────────────────────┬───────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────┐
│              MODEL ROUTING & FALLBACK                │
│                                                      │
│       Task-Based Model Selection                     │
│                                                      │
│   Gemini       Qwen       DeepSeek       Other       │
│   Primary      Backup     Backup         Approved    │
│                                                      │
│   9Router = Candidate Gateway / Evaluated            │
└──────────────────────┬───────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────┐
│                    AI AGENTS                         │
│                                                      │
│  Web Specialist     Network Specialist               │
│  Database Specialist Server Specialist               │
│                                                      │
│  Agents may use different models according           │
│  to task requirements.                               │
└──────────────────────┬───────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────┐
│                GNSG CORE BACKEND                     │
│                                                      │
│ Authentication • RBAC • Policy Enforcement           │
│ Approval System • Audit Logging • Sessions            │
│ Service Registry • Security Controls                  │
└──────────────────────┬───────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────┐
│               OPERATIONAL LAYER                      │
│                                                      │
│ Ubuntu Server • Nginx • PHP • MariaDB                │
│ MikroTik • Network • Email • Website                  │
└──────────────────────────────────────────────────────┘
```

Diagram tersebut merupakan gambaran logis sistem. Tidak semua komponen harus berjalan sebagai server atau proses terpisah.

---

### 4. Communication Layer

Communication Layer menjadi titik interaksi antara pengguna dan GNSG Intelligence Core.

#### 4.1 Telegram Admin

Digunakan oleh administrator atau engineer yang memiliki hak akses operasional.

Kemampuan yang direncanakan antara lain:

- Memeriksa kondisi server.
- Memeriksa status layanan.
- Memeriksa database.
- Meminta analisis gangguan.
- Meminta pemeriksaan jaringan.
- Mengajukan tindakan operasional.
- Memberikan persetujuan terhadap tindakan yang memang menjadi kewenangannya.

Hak akses tetap ditentukan oleh RBAC dan bukan berdasarkan identitas Telegram semata.

#### 4.2 Telegram Customer

Digunakan untuk kebutuhan pelanggan dan layanan yang memiliki ruang lingkup berbeda dari administrator.

Customer tidak memperoleh akses terhadap fungsi administratif atau infrastruktur internal.

---

### 5. Orchestration Layer

Hermes berfungsi sebagai **orchestrator**, bukan sebagai pemilik seluruh kewenangan infrastruktur.

Tanggung jawab utama orchestration layer meliputi:

- Mengelola percakapan.
- Memahami konteks tugas.
- Menentukan agen yang sesuai.
- Melakukan delegasi tugas.
- Mengatur penggunaan model AI.
- Menggabungkan hasil dari beberapa agen.
- Menangani fallback model.
- Mengelola alur tugas.

Hermes tidak boleh melewati security boundary yang ditetapkan oleh GNSG Core Backend.

---

### 6. Multi-Model Architecture

GNSG Intelligence Core menggunakan beberapa model AI yang dapat digunakan berdasarkan kebutuhan.

#### 6.1 Gemini

Gemini ditetapkan sebagai model utama untuk:

- Penalaran kompleks.
- Analisis masalah.
- Koordinasi tugas.
- Analisis konteks yang membutuhkan kemampuan reasoning tinggi.

Gemini bukan satu-satunya model yang tersedia.

#### 6.2 Qwen

Qwen dapat digunakan sebagai:

- Model alternatif.
- Pendukung pengembangan dan coding.
- Analisis teknis.
- Fallback ketika model utama tidak tersedia.

#### 6.3 DeepSeek

DeepSeek dapat digunakan sebagai:

- Model alternatif.
- Analisis kode.
- Debugging.
- Troubleshooting teknis.
- Second opinion terhadap hasil analisis.

#### 6.4 Model Tambahan

Arsitektur harus memungkinkan penambahan model lain tanpa mengubah struktur inti sistem.

Model baru harus melalui evaluasi sebelum diberi akses terhadap tugas atau data tertentu.

---

### 7. Task-Based Model Routing

Pemilihan model tidak hanya menggunakan konsep primary dan fallback.

Sistem juga dapat memilih model berdasarkan karakteristik tugas.

Contoh:

| Jenis Tugas | Model Prioritas | Alternatif |
|---|---|---|
| Reasoning kompleks | Gemini | Qwen / DeepSeek |
| Coding | Qwen / Gemini | DeepSeek |
| Code analysis dan debugging | DeepSeek / Gemini | Qwen |
| Troubleshooting jaringan | Gemini / Qwen | DeepSeek |
| Pemeriksaan rutin | Model efisien yang disetujui | Model alternatif |
| Analisis sensitif | Model yang telah disetujui untuk data tersebut | Model alternatif yang disetujui |

Daftar tersebut merupakan kebijakan awal dan dapat berubah setelah pengujian.

Routing harus mempertimbangkan:

- Kemampuan model.
- Ketersediaan provider.
- Rate limit.
- Penggunaan token.
- Biaya.
- Context window.
- Kemampuan tool calling.
- Tingkat sensitivitas data.
- Tingkat risiko tugas.

---

### 8. Fallback Architecture

Fallback digunakan untuk meningkatkan ketahanan sistem terhadap kegagalan provider AI.

Contoh:

```text
Task
  │
  ▼
Task Router
  │
  ▼
Gemini
  │
  ├── SUCCESS ──────────────► Result
  │
  └── FAILURE
          │
          ▼
        Qwen
          │
          ├── SUCCESS ──────► Result
          │
          └── FAILURE
                  │
                  ▼
                DeepSeek
                  │
                  └──────────► Result / Failure
```

Fallback tidak boleh digunakan untuk melewati kebijakan keamanan.

Model alternatif harus tetap tunduk terhadap:

- RBAC.
- Data access policy.
- Tool permission.
- Approval requirement.
- Audit logging.
- Service policy.

---

### 9. Multi-Agent Architecture

AI Specialist dibuat berdasarkan domain pekerjaan.

#### 9.1 Web Specialist

Fokus terhadap:

- Nginx.
- PHP.
- Website.
- SSL/TLS.
- Web service.
- Latency.
- Web-related troubleshooting.

#### 9.2 Network Specialist

Fokus terhadap:

- MikroTik.
- Routing.
- Firewall.
- Interface.
- Bandwidth.
- Connectivity.
- Network troubleshooting.

#### 9.3 Database Specialist

Fokus terhadap:

- MariaDB.
- Database health.
- Backup.
- Query analysis.
- Database integrity.
- Database troubleshooting.

#### 9.4 Server Specialist

Fokus terhadap:

- Ubuntu.
- System resources.
- Services.
- Logs.
- Process monitoring.
- Server troubleshooting.

Jumlah specialist dapat bertambah sesuai kebutuhan sistem.

---

### 10. Model dan Agent Tidak Sama

Identitas agen dan model AI harus dipisahkan.

Contoh:

```text
Network Specialist
        │
        ├── Gemini
        ├── Qwen
        └── DeepSeek
```

Network Specialist merupakan identitas dan domain tanggung jawab.

Gemini, Qwen, dan DeepSeek merupakan model yang dapat digunakan oleh agen tersebut.

Dengan demikian, pergantian model tidak mengubah hak akses atau tanggung jawab agen.

---

### 11. Parallel Agent Collaboration

Untuk tugas tertentu, beberapa agen dapat bekerja secara paralel.

Contoh gangguan jaringan:

```text
                    Network Incident
                           │
                           ▼
                    Hermes Orchestrator
                           │
             ┌─────────────┼─────────────┐
             ▼             ▼             ▼
        Network        Server         Database
       Specialist     Specialist     Specialist
             │             │             │
             └─────────────┼─────────────┘
                           ▼
                    Result Aggregation
                           │
                           ▼
                    Final Analysis
```

Parallel collaboration hanya digunakan apabila memberikan manfaat nyata.

Tidak semua tugas perlu menggunakan banyak agen karena dapat meningkatkan:

- Penggunaan token.
- Biaya.
- Latency.
- Kompleksitas sistem.
- Beban server.

---

### 12. GNSG Core Backend

GNSG Core Backend merupakan **security boundary utama** sistem.

Backend bertanggung jawab terhadap:

- Authentication.
- Authorization.
- RBAC.
- Session management.
- Audit logging.
- Approval system.
- Service registry.
- Policy enforcement.
- Validasi tindakan.
- Pembatasan akses terhadap infrastruktur.

AI tidak boleh menggantikan fungsi backend tersebut.

---

### 13. Security Boundary

Model AI tidak memperoleh akses bebas terhadap sistem operasi atau perangkat infrastruktur.

Alur tindakan operasional:

```text
User
 │
 ▼
Telegram
 │
 ▼
Hermes
 │
 ▼
AI Specialist
 │
 ▼
Action Proposal
 │
 ▼
GNSG Core Backend
 │
 ├── Authentication
 ├── RBAC
 ├── Policy Check
 ├── Risk Check
 ├── Approval Check
 └── Audit
 │
 ▼
Authorized Tool
 │
 ▼
Infrastructure
```

AI hanya menghasilkan permintaan atau rekomendasi tindakan.

Backend menentukan apakah tindakan tersebut diperbolehkan.

---

### 14. Approval Architecture

Tindakan dengan risiko tertentu harus membutuhkan persetujuan manusia.

Contoh tindakan yang dapat membutuhkan approval:

- Restart service penting.
- Mengubah konfigurasi MikroTik.
- Mengubah firewall.
- Mengubah konfigurasi Nginx.
- Perubahan database tertentu.
- Tindakan lain yang ditetapkan sebagai high atau critical risk.

Approval harus terikat pada tindakan dan payload yang spesifik.

Approval tidak boleh menjadi izin umum untuk melakukan tindakan lain.

---

### 15. Audit Architecture

Aktivitas penting harus dicatat.

Audit log dapat mencatat:

- Identitas pengguna.
- Agen yang digunakan.
- Model yang digunakan.
- Jenis tindakan.
- Resource yang ditargetkan.
- Status tindakan.
- Waktu.
- Hasil.
- Informasi pendukung yang tidak mengandung secret.

Secret, token, password, private key, dan credential tidak boleh dicatat ke dalam audit log.

---

### 16. 9Router

9Router ditetapkan sebagai **komponen kandidat**, bukan komponen wajib.

GNSG Intelligence Core akan mengevaluasi:

1. Fallback native Hermes.
2. 9Router sebagai AI gateway.
3. Alternatif routing lain jika diperlukan.

Evaluasi meliputi:

- Stabilitas.
- Kompatibilitas Hermes.
- Tool calling.
- Model routing.
- Fallback.
- Keamanan credential.
- Latency.
- Resource consumption.
- Kemudahan pemeliharaan.

9Router hanya akan digunakan apabila hasil pengujian menunjukkan manfaat yang jelas terhadap arsitektur GNSG.

---

### 17. Data dan Credential Boundary

Credential provider AI, database, Telegram, MikroTik, dan layanan lainnya harus dipisahkan dari source code.

Model AI tidak boleh menerima credential secara langsung jika tindakan dapat dilakukan melalui tool yang memiliki security boundary.

Setiap provider AI harus dikategorikan berdasarkan:

- Jenis data yang boleh dikirim.
- Tingkat sensitivitas.
- Jenis tugas yang diperbolehkan.
- Model yang disetujui.
- Kebijakan penyimpanan data.

---

### 18. Resilience

Sistem dirancang agar kegagalan satu komponen tidak langsung menyebabkan seluruh sistem berhenti.

Contoh kegagalan yang harus ditangani:

- Provider AI down.
- Model terkena rate limit.
- Kuota provider habis.
- API timeout.
- Model gagal menghasilkan response.
- Tool gagal.
- Infrastruktur target tidak tersedia.
- Database tidak tersedia.

Namun fallback hanya berlaku jika jalur alternatif tetap memenuhi kebijakan keamanan.

---

### 19. Tahapan Implementasi Arsitektur

Implementasi dilakukan bertahap:

#### Tahap 1 — Model Foundation

- Stabilkan Gemini.
- Evaluasi Qwen.
- Evaluasi DeepSeek.
- Uji fallback Hermes.

#### Tahap 2 — Model Router

- Implementasi task classification.
- Model selection.
- Provider fallback.
- Policy berdasarkan sensitivitas data.

#### Tahap 3 — Agent Foundation

- Web Specialist.
- Network Specialist.
- Database Specialist.
- Server Specialist.

#### Tahap 4 — Agent Collaboration

- Delegasi antar-agent.
- Parallel execution.
- Result aggregation.
- Second opinion.

#### Tahap 5 — Infrastructure Integration

- Monitoring.
- Tool execution.
- MikroTik integration.
- Database operations.
- Server operations.
- Web operations.

#### Tahap 6 — Security Hardening

- RBAC enforcement.
- Approval enforcement.
- Audit validation.
- Rate limiting.
- Failure handling.
- Security testing.

---

### 20. Prinsip Akhir

GNSG Intelligence Core tidak dirancang sebagai satu chatbot yang diberi akses ke server.

Sistem dirancang sebagai ekosistem kecerdasan buatan yang terdiri dari:

- Multi-Agent.
- Multi-Model.
- Task-Based Routing.
- Provider Fallback.
- Human Approval.
- RBAC.
- Audit Logging.
- Secure Tool Execution.

Model AI dapat berubah.

Provider AI dapat berubah.

Agen dapat bertambah.

Router dapat diganti.

Namun **security boundary dan kebijakan kewenangan GNSG Core harus tetap menjadi fondasi sistem.**

Dengan pendekatan tersebut, GNSG Intelligence Core dapat berkembang dari asisten AI sederhana menjadi pusat kendali infrastruktur yang terstruktur, resilien, dan dapat dikembangkan secara bertahap.

---

**GNSG Intelligence Core**  
**Ganesha Sistem Gemilang**