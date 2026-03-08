# Thesis Project Redesign

## Tujuan

- Jadikan satu entitas sebagai sumber kebenaran untuk seluruh alur tugas akhir mahasiswa.
- Hilangkan pemisahan workflow utama antara pengajuan judul, sempro, dan pembimbing.
- Siapkan struktur yang bisa menampung `sidang skripsi` tanpa membuat cabang model baru lagi.
- Pertahankan riwayat percobaan tugas akhir, judul, pembimbing, penguji, revisi, dan dokumen.
- Sederhanakan UX admin agar pengelolaan dilakukan dari satu halaman utama per mahasiswa/proyek.

## Keputusan Inti

- `users` tetap menjadi sumber kebenaran untuk autentikasi dan identitas akun.
- Profil per peran tetap dipisah karena atributnya memang berbeda:
    - `mahasiswa_profiles`
    - `dosen_profiles`
    - `admin_profiles`
- Admin UX disatukan di level resource, bukan dengan memaksa semua data profil masuk ke satu tabel nullable besar.
- Entitas baru `thesis_projects` menjadi aggregate root untuk satu percobaan tugas akhir mahasiswa.
- Satu mahasiswa boleh memiliki banyak `thesis_projects` historis, tetapi hanya satu yang aktif dalam satu waktu.
- Sempro dan sidang diperlakukan sebagai varian dari entitas yang sama: `thesis_defenses`.
- Penguji selalu melekat ke satu attempt defense, bukan langsung ke mahasiswa atau proyek.
- Pembimbing selalu melekat ke satu proyek, bukan langsung ke mahasiswa secara global.

## Gambaran Relasi

```text
users
|- mahasiswa_profiles
|- dosen_profiles
|- admin_profiles
`- thesis_projects
   |- thesis_project_titles
   |- thesis_supervisor_assignments
   |- thesis_defenses
   |  `- thesis_defense_examiners
   |- thesis_revisions
   |- thesis_documents
   `- thesis_project_events
```

## Entitas Utama

### 1. `users`

Tetap dipakai untuk:

- autentikasi
- nama dan email akun
- password
- role switching
- notifikasi

Tidak dipakai lagi sebagai pusat state tugas akhir.

### 2. Profil Orang

Tetap pertahankan:

- `mahasiswa_profiles`
    - `user_id`
    - `nim`
    - `angkatan`
    - `program_studi_id`
    - `is_active`
- `dosen_profiles`
    - `user_id`
    - `nik`
    - `program_studi_id`
    - `is_active`
- `admin_profiles`
    - `user_id`
    - `program_studi_id`

Catatan:

- Ini tetap terpisah di database.
- Yang disatukan adalah admin resource `People`, bukan tabel fisiknya.

## Aggregate Root: `thesis_projects`

Satu baris di tabel ini mewakili satu percobaan tugas akhir milik satu mahasiswa.

### Kolom yang direkomendasikan

| Kolom              | Tipe      | Null | Catatan                              |
| ------------------ | --------- | ---- | ------------------------------------ |
| `id`               | bigint    | no   | PK                                   |
| `student_user_id`  | foreignId | no   | FK ke `users.id`                     |
| `program_studi_id` | foreignId | no   | Snapshot prodi saat proyek dibuat    |
| `phase`            | string    | no   | Tahap bisnis saat ini                |
| `state`            | string    | no   | Status global proyek                 |
| `started_at`       | timestamp | yes  | Kapan attempt dimulai                |
| `completed_at`     | timestamp | yes  | Kapan proyek selesai                 |
| `cancelled_at`     | timestamp | yes  | Kapan proyek dibatalkan              |
| `created_by`       | foreignId | yes  | User admin/pihak yang membuat record |
| `closed_by`        | foreignId | yes  | User yang menutup project            |
| `notes`            | text      | yes  | Catatan admin umum                   |
| `created_at`       | timestamp | no   |                                      |
| `updated_at`       | timestamp | no   |                                      |

### Nilai enum yang direkomendasikan

- `phase`
    - `title_review`
    - `sempro`
    - `research`
    - `sidang`
    - `completed`
    - `cancelled`
- `state`
    - `active`
    - `on_hold`
    - `completed`
    - `cancelled`

### Constraint dan index

- index `student_user_id, state`
- index `phase, state`
- index `program_studi_id, phase`
- validasi aplikasi: satu mahasiswa hanya boleh punya satu proyek aktif
- constraint database untuk satu proyek aktif per mahasiswa dilakukan per engine bila memungkinkan

Catatan portabilitas database:

- PostgreSQL / SQLite: bisa memakai partial unique index untuk proyek aktif.
- MySQL: lebih aman ditegakkan di service layer, atau pakai generated column bila nanti dibutuhkan.

## Riwayat Judul: `thesis_project_titles`

Tabel ini menyimpan seluruh versi judul dan proposal, sehingga ganti judul atau revisi proposal tidak menimpa histori.

### Kolom yang direkomendasikan

| Kolom                  | Tipe            | Null | Catatan                    |
| ---------------------- | --------------- | ---- | -------------------------- |
| `id`                   | bigint          | no   | PK                         |
| `project_id`           | foreignId       | no   | FK ke `thesis_projects.id` |
| `version_no`           | unsignedInteger | no   | Versi urut per project     |
| `title_id`             | string          | no   | Judul bahasa Indonesia     |
| `title_en`             | string          | yes  | Judul bahasa Inggris       |
| `proposal_summary`     | text            | yes  | Ringkasan proposal         |
| `status`               | string          | no   | Status versi judul         |
| `submitted_by_user_id` | foreignId       | yes  | Biasanya mahasiswa         |
| `submitted_at`         | timestamp       | yes  |                            |
| `decided_by_user_id`   | foreignId       | yes  | Biasanya admin             |
| `decided_at`           | timestamp       | yes  |                            |
| `decision_notes`       | text            | yes  | Catatan review             |
| `created_at`           | timestamp       | no   |                            |
| `updated_at`           | timestamp       | no   |                            |

### Nilai enum yang direkomendasikan

- `status`
    - `draft`
    - `submitted`
    - `approved`
    - `rejected`
    - `superseded`
    - `withdrawn`

### Constraint dan index

- unique `project_id, version_no`
- index `project_id, status`
- index `submitted_at`
- validasi aplikasi:
    - hanya satu versi `submitted` aktif pada satu waktu per project
    - hanya satu versi `approved` efektif pada satu waktu per project

## Pembimbing Proyek: `thesis_supervisor_assignments`

Tabel ini menggantikan pemodelan pembimbing yang sekarang masih dipisah dari root proyek.

### Kolom yang direkomendasikan

| Kolom              | Tipe      | Null | Catatan                         |
| ------------------ | --------- | ---- | ------------------------------- |
| `id`               | bigint    | no   | PK                              |
| `project_id`       | foreignId | no   | FK ke `thesis_projects.id`      |
| `lecturer_user_id` | foreignId | no   | FK ke `users.id`                |
| `role`             | string    | no   | `primary` / `secondary`         |
| `status`           | string    | no   | `active`, `ended`, `cancelled`  |
| `assigned_by`      | foreignId | yes  | User yang menetapkan pembimbing |
| `started_at`       | timestamp | yes  |                                 |
| `ended_at`         | timestamp | yes  |                                 |
| `notes`            | text      | yes  |                                 |
| `created_at`       | timestamp | no   |                                 |
| `updated_at`       | timestamp | no   |                                 |

### Nilai enum yang direkomendasikan

- `role`
    - `primary`
    - `secondary`
- `status`
    - `active`
    - `ended`
    - `cancelled`

### Constraint dan index

- index `project_id, status`
- index `lecturer_user_id, status`
- index `project_id, role, status`
- validasi aplikasi:
    - maksimum 2 pembimbing aktif per project
    - maksimum 1 pembimbing aktif untuk tiap role (`primary`, `secondary`)
    - dosen yang ditetapkan harus memiliki role `dosen`

## Sempro dan Sidang: `thesis_defenses`

Tabel ini dipakai untuk semua event ujian/seminar resmi, termasuk sempro dan sidang.

### Kolom yang direkomendasikan

| Kolom              | Tipe            | Null | Catatan                               |
| ------------------ | --------------- | ---- | ------------------------------------- |
| `id`               | bigint          | no   | PK                                    |
| `project_id`       | foreignId       | no   | FK ke `thesis_projects.id`            |
| `title_version_id` | foreignId       | yes  | Versi judul yang dipakai saat defense |
| `type`             | string          | no   | `sempro` / `sidang`                   |
| `attempt_no`       | unsignedInteger | no   | Attempt ke berapa untuk type tersebut |
| `status`           | string          | no   | Status operasional defense            |
| `result`           | string          | no   | Hasil akhir defense                   |
| `scheduled_for`    | timestamp       | yes  | Tanggal dan waktu pelaksanaan         |
| `location`         | string          | yes  | Ruang atau media meeting              |
| `mode`             | string          | no   | `offline`, `online`, `hybrid`         |
| `created_by`       | foreignId       | yes  | Admin yang membuat record             |
| `decided_by`       | foreignId       | yes  | User yang menetapkan hasil final      |
| `decision_at`      | timestamp       | yes  |                                       |
| `notes`            | text            | yes  | Catatan umum defense                  |
| `created_at`       | timestamp       | no   |                                       |
| `updated_at`       | timestamp       | no   |                                       |

### Nilai enum yang direkomendasikan

- `type`
    - `sempro`
    - `sidang`
- `status`
    - `draft`
    - `scheduled`
    - `completed`
    - `cancelled`
- `result`
    - `pending`
    - `pass`
    - `pass_with_revision`
    - `fail`

### Constraint dan index

- unique `project_id, type, attempt_no`
- index `type, status, scheduled_for`
- index `project_id, type, status`
- validasi aplikasi:
    - satu project boleh punya banyak attempt untuk `sempro`
    - satu project boleh punya banyak attempt untuk `sidang`
    - `attempt_no` harus naik berurutan per `project_id + type`

## Penguji Defense: `thesis_defense_examiners`

Tabel ini menyimpan assignment dan hasil penilaian per penguji pada satu sempro/sidang tertentu.

### Kolom yang direkomendasikan

| Kolom              | Tipe                | Null | Catatan                    |
| ------------------ | ------------------- | ---- | -------------------------- |
| `id`               | bigint              | no   | PK                         |
| `defense_id`       | foreignId           | no   | FK ke `thesis_defenses.id` |
| `lecturer_user_id` | foreignId           | no   | FK ke `users.id`           |
| `role`             | string              | no   | Untuk future-proof sidang  |
| `order_no`         | unsignedTinyInteger | no   | Urutan tampil di UI        |
| `decision`         | string              | no   | Keputusan per penguji      |
| `score`            | decimal(5,2)        | yes  | Nilai 0-100                |
| `notes`            | text                | yes  | Catatan penilai            |
| `decided_at`       | timestamp           | yes  |                            |
| `assigned_by`      | foreignId           | yes  | Admin yang menetapkan      |
| `created_at`       | timestamp           | no   |                            |
| `updated_at`       | timestamp           | no   |                            |

### Nilai enum yang direkomendasikan

- `role`
    - `examiner`
    - `chair`
    - `secretary`
- `decision`
    - `pending`
    - `pass`
    - `pass_with_revision`
    - `fail`

### Constraint dan index

- unique `defense_id, lecturer_user_id`
- unique `defense_id, order_no`
- index `lecturer_user_id, decision`
- validasi aplikasi:
    - penguji harus ber-role `dosen`
    - aturan jumlah penguji ditangani di service layer agar bisa beda antara sempro dan sidang
    - default implementasi awal: sempro tetap 2 penguji

## Revisi: `thesis_revisions`

Tabel ini dipakai untuk tugas revisi formal yang harus diselesaikan sebelum phase berikutnya.

### Kolom yang direkomendasikan

| Kolom                  | Tipe      | Null | Catatan                                |
| ---------------------- | --------- | ---- | -------------------------------------- |
| `id`                   | bigint    | no   | PK                                     |
| `project_id`           | foreignId | no   | FK ke `thesis_projects.id`             |
| `defense_id`           | foreignId | yes  | Null bila revisi tidak terkait defense |
| `requested_by_user_id` | foreignId | yes  | Pengusul revisi                        |
| `status`               | string    | no   | Status revisi                          |
| `notes`                | text      | no   | Isi revisi                             |
| `due_at`               | timestamp | yes  | Deadline revisi                        |
| `submitted_at`         | timestamp | yes  | Kapan mahasiswa submit revisi          |
| `resolved_at`          | timestamp | yes  |                                        |
| `resolved_by_user_id`  | foreignId | yes  |                                        |
| `resolution_notes`     | text      | yes  |                                        |
| `created_at`           | timestamp | no   |                                        |
| `updated_at`           | timestamp | no   |                                        |

### Nilai enum yang direkomendasikan

- `status`
    - `open`
    - `submitted`
    - `resolved`
    - `cancelled`

### Constraint dan index

- index `project_id, status`
- index `defense_id, status`
- index `due_at, status`
- validasi aplikasi:
    - project tidak boleh maju ke phase berikutnya bila masih ada revisi `open` atau `submitted`

## Dokumen Proyek: `thesis_documents`

Tabel ini menggantikan pemecahan file tugas akhir ke banyak konteks yang tidak seragam.

### Kolom yang direkomendasikan

| Kolom                 | Tipe            | Null | Catatan                                         |
| --------------------- | --------------- | ---- | ----------------------------------------------- |
| `id`                  | bigint          | no   | PK                                              |
| `project_id`          | foreignId       | no   | FK ke `thesis_projects.id`                      |
| `title_version_id`    | foreignId       | yes  | Jika file terkait versi judul/proposal tertentu |
| `defense_id`          | foreignId       | yes  | Jika file terkait sempro/sidang tertentu        |
| `revision_id`         | foreignId       | yes  | Jika file bagian dari revisi tertentu           |
| `uploaded_by_user_id` | foreignId       | yes  |                                                 |
| `kind`                | string          | no   | Jenis dokumen                                   |
| `status`              | string          | no   | Status dokumen                                  |
| `version_no`          | unsignedInteger | no   | Versi file dalam kelompok yang sama             |
| `title`               | string          | no   | Judul tampil di UI                              |
| `notes`               | text            | yes  |                                                 |
| `storage_disk`        | string          | no   |                                                 |
| `storage_path`        | string          | no   |                                                 |
| `file_name`           | string          | no   |                                                 |
| `mime_type`           | string          | yes  |                                                 |
| `file_size_kb`        | unsignedInteger | yes  |                                                 |
| `uploaded_at`         | timestamp       | yes  |                                                 |
| `created_at`          | timestamp       | no   |                                                 |
| `updated_at`          | timestamp       | no   |                                                 |

### Nilai enum yang direkomendasikan

- `kind`
    - `proposal`
    - `proposal_revision`
    - `sempro_slides`
    - `sempro_minutes`
    - `research_draft`
    - `sidang_slides`
    - `final_thesis`
    - `administrative`
    - `other`
- `status`
    - `active`
    - `archived`
    - `rejected`

### Constraint dan index

- index `project_id, kind, status`
- index `defense_id, kind`
- index `revision_id`
- unique `project_id, kind, version_no, storage_path`

## Timeline dan Audit Trail: `thesis_project_events`

Tabel ini menjadi sumber data untuk tab timeline di admin.

### Kolom yang direkomendasikan

| Kolom           | Tipe      | Null | Catatan                    |
| --------------- | --------- | ---- | -------------------------- |
| `id`            | bigint    | no   | PK                         |
| `project_id`    | foreignId | no   | FK ke `thesis_projects.id` |
| `actor_user_id` | foreignId | yes  | Null bila event sistem     |
| `event_type`    | string    | no   | Kode event                 |
| `label`         | string    | no   | Label ringkas untuk UI     |
| `description`   | text      | yes  | Penjelasan lebih detail    |
| `payload`       | json      | yes  | Metadata tambahan          |
| `occurred_at`   | timestamp | no   | Waktu event bisnis         |
| `created_at`    | timestamp | no   |                            |
| `updated_at`    | timestamp | no   |                            |

### Contoh `event_type`

- `project_created`
- `title_submitted`
- `title_approved`
- `supervisor_assigned`
- `sempro_scheduled`
- `sempro_completed`
- `revision_opened`
- `revision_resolved`
- `sidang_scheduled`
- `sidang_completed`
- `project_closed`

### Constraint dan index

- index `project_id, occurred_at`
- index `event_type, occurred_at`

## State Model yang Direkomendasikan

### Fase proyek

1. `title_review`
2. `sempro`
3. `research`
4. `sidang`
5. `completed`
6. `cancelled`

### Transisi utama

```text
title_review
  -> sempro           ketika ada title/proposal approved

sempro
  -> research         ketika sempro result = pass atau pass_with_revision dan revisi selesai
  -> sempro           ketika sempro fail dan dibuat attempt baru

research
  -> sidang           ketika pembimbing menyatakan siap sidang

sidang
  -> completed        ketika sidang pass atau pass_with_revision dan revisi selesai
  -> sidang           ketika sidang fail dan dibuat attempt baru
```

## Aturan Bisnis yang Dibekukan

- satu mahasiswa boleh punya banyak proyek historis, tetapi hanya satu yang aktif
- satu proyek boleh punya banyak versi judul
- satu proyek maksimal punya 2 pembimbing aktif: `primary` dan `secondary`
- sempro dan sidang boleh diulang sebagai attempt baru
- penguji melekat ke attempt defense, bukan ke project
- revisi yang masih terbuka memblokir progres ke phase berikutnya
- default implementasi awal:
    - sempro menggunakan 2 penguji
    - aturan jumlah penguji sidang ditentukan di service layer agar mudah diubah
    - overlap pembimbing/penguji tidak ditegakkan di schema, tetapi di policy/service

## Mapping Legacy ke Struktur Baru

### `thesis_submissions`

- satu row `thesis_submissions` menjadi:
    - satu row `thesis_projects`
    - satu row awal `thesis_project_titles`

Mapping utama:

- `student_user_id` -> `thesis_projects.student_user_id`
- `program_studi_id` -> `thesis_projects.program_studi_id`
- `title_id`, `title_en`, `proposal_summary` -> `thesis_project_titles`
- `approved_by`, `approved_at` -> `thesis_project_titles.decided_by_user_id`, `decided_at`

### `sempros`

- satu row `sempros` menjadi satu row `thesis_defenses` dengan `type = sempro`

### `sempro_examiners`

- dipindahkan ke `thesis_defense_examiners`

### `sempro_revisions`

- dipindahkan ke `thesis_revisions`

### `mentorship_assignments`

- dipindahkan ke `thesis_supervisor_assignments`

## Mapping Status Legacy ke Model Baru

### `thesis_submissions.status`

- `menunggu_persetujuan`
    - project `phase = title_review`
    - project `state = active`
    - latest title `status = submitted`
- `sempro_dijadwalkan`
    - project `phase = sempro`
    - project `state = active`
    - latest title `status = approved`
    - sempro defense `status = scheduled`, `result = pending`
- `revisi_sempro`
    - project `phase = sempro`
    - project `state = active`
    - sempro defense `status = completed`, `result = pass_with_revision`
    - minimal satu `thesis_revisions.status = open`
- `sempro_selesai`
    - project `phase = research`
    - project `state = active`
    - sempro defense `status = completed`, `result = pass`
- `pembimbing_ditetapkan`
    - project `phase = research`
    - project `state = active`
    - sempro defense `status = completed`, `result = pass`
    - ada minimal satu pembimbing aktif

## Strategi Implementasi

### Phase 1 - Tambah struktur baru

- buat tabel baru tanpa menghapus tabel lama
- tambahkan kolom `legacy_*_id` pada tabel baru yang perlu backfill idempotent
- buat enum PHP baru untuk model baru
- bangun model dan relation baru

### Phase 2 - Backfill data

- migrasikan semua `thesis_submissions` ke `thesis_projects`
- migrasikan `sempros`, `sempro_examiners`, `sempro_revisions`
- migrasikan `mentorship_assignments`
- isi `thesis_project_events` dari state penting yang bisa disimpulkan

### Phase 3 - Admin cutover

- buat `ThesisProjectResource` baru di Filament
- semua action admin baru menulis ke struktur baru
- resource lama dijadikan read-only sementara bila perlu

### Phase 4 - Cleanup

- pindahkan sisa dependensi front-end/back-end ke model baru
- hapus enum lama yang sudah tidak dipakai
- hapus tabel legacy setelah data dan UI stabil

## Dampak ke Admin UX

Admin nantinya cukup membuka satu project dan melihat:

- mahasiswa
- judul aktif dan histori judul
- pembimbing aktif dan histori perubahan
- daftar sempro attempts
- daftar sidang attempts
- revisi yang masih terbuka
- dokumen
- timeline lengkap

Ini menggantikan pola sekarang yang memaksa admin berpindah-pindah antara user, submission, sempro, dan pembimbing.

## Batasan dan Catatan

- constraint untuk "satu active row" lebih aman ditangani di service layer agar tetap portable lintas engine
- jika nanti perlu enforce penuh di DB, implementasikan per engine database
- `mentorship_chat_threads`, `mentorship_schedules`, dan modul chat tidak perlu dirombak pada phase pertama
- modul kolaborasi bisa direlasikan ke `project_id` setelah root tugas akhir baru stabil
