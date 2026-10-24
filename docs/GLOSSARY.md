# Glossary

Academic terms shown in SITA. Every user-facing label comes from `App\Support\AcademicTerminology` (PHP) or `resources/js/lib/academic-terminology.ts` (TS); this file says which label to use and why. A Pest test (`tests/Unit/ForbiddenTerminologyTest.php`) fails when a retired phrase shows up in `app/` or `resources/js/`.

## Two audiences

- **Single student**: the mahasiswa portal, a dosen looking at one student, any detail page. Labels follow the student's degree level (`forStudent()`, `forProject()`, `forDegreeLevel()`).
- **Cross-degree**: admin and kaprodi lists, menus, dashboards, public pages, reports, anything that mixes D3, S1, and S2 students. Labels come from `neutral()`.

## Labels

| Concept             | Key                 | Single student                                                  | Cross-degree        |
| ------------------- | ------------------- | --------------------------------------------------------------- | ------------------- |
| Final work          | `finalWork`         | D3 "Tugas Akhir", S1 "Skripsi", S2 "Tesis"                      | "Tugas Akhir"       |
| Stage 1 exam        | `proposalExam`      | "Seminar Proposal" (OBE: "Ujian Sinopsis")                      | "Sempro / Sinopsis" |
| Stage 1 exam, short | `proposalExamShort` | "Sempro" (OBE: "Sinopsis")                                      | "Sempro / Sinopsis" |
| Stage 1 document    | `proposalDocument`  | "Naskah Proposal"                                               | "Naskah Proposal"   |
| Final exam          | `finalExam`         | D3 "Sidang Tugas Akhir", S1 "Sidang Skripsi", S2 "Sidang Tesis" | "Sidang Akhir"      |
| Final exam, short   | `finalExamShort`    | "Sidang"                                                        | "Sidang Akhir"      |

The OBE variant is chosen by `mahasiswa_profiles.is_obe_curriculum`, read only inside `AcademicTerminology::forStudent()` (or passed as `forDegreeLevel($level, $isObe)`); callers never pass the flag around. OBE students take **Ujian Sinopsis** with a score and a comment from every examiner; non-OBE students take **Seminar Proposal** without a score (the score field is hidden and a submitted score is rejected). Sidang always needs a score. `ThesisDefense::requiresScore()` is the single check: score-less non-OBE sempro counts as fully graded everywhere (public `/jadwal`, kaprodi, admin). Older non-OBE sempro rows that already have scores keep showing them.

Exam names never carry the degree's final work ("Sempro Tugas Akhir" and "Seminar Proposal Tesis" are gone). The word "Proposal" alone is ambiguous (document, phase, or exam), so say "Naskah Proposal", "Tahap Proposal", or the exam label.

## Phases

`thesis_projects.phase`, same label for every audience (`phaseLabel()`, or the `*Phase` keys).

| Value          | Key                | Label            |
| -------------- | ------------------ | ---------------- |
| `title_review` | `titleReviewPhase` | "Review Judul"   |
| `sempro`       | `proposalPhase`    | "Tahap Proposal" |
| `research`     | `researchPhase`    | "Riset"          |
| `sidang`       | `finalExamPhase`   | "Tahap Sidang"   |
| `completed`    | `completedPhase`   | "Selesai"        |

## Code equivalents

| Code                                                               | Value                               | Label                                                                                                                                                                                                                         |
| ------------------------------------------------------------------ | ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `thesis_projects` / `ThesisProject`                                | one row                             | the student's final work (`finalWork`); never "Proyek"                                                                                                                                                                        |
| `thesis_defenses.type`                                             | `sempro`                            | stage 1 exam (`proposalExam` / `proposalExamShort`)                                                                                                                                                                           |
| `thesis_defenses.type`                                             | `sidang`                            | final exam (`finalExam` / `finalExamShort`)                                                                                                                                                                                   |
| `ThesisDocument.kind`                                              | `proposal`                          | "Naskah Proposal" (`proposalDocument`)                                                                                                                                                                                        |
| `ThesisDocument.kind`                                              | `revision_submission`               | "Dokumen Revisi"                                                                                                                                                                                                              |
| `ThesisDocument.kind`                                              | `revision_annotation`               | "Naskah beranotasi" (dari penguji, versi = putaran)                                                                                                                                                                           |
| `ThesisDocument.kind`                                              | `revision_response`                 | "Naskah perbaikan" (dari mahasiswa, versi = putaran)                                                                                                                                                                          |
| `thesis_revision_items`                                            | one row per butir                   | "Butir revisi" (lokasi, uraian, prioritas Mayor/Minor)                                                                                                                                                                        |
| `ThesisDocument.kind`                                              | `final_manuscript`                  | "Naskah Akhir"                                                                                                                                                                                                                |
| `ThesisDocument.kind`                                              | `supporting_document`               | "Lampiran"                                                                                                                                                                                                                    |
| `thesis_projects.mentorship_valid_from` / `mentorship_valid_until` | one year from supervisor assignment | "Masa aktif bimbingan" (#21); lapsed: badge "Masa aktif habis", bimbingan suspended until admin renews ("Perpanjang masa aktif 1 tahun"). Code: `MentorshipTicketService`, `isMentorshipSuspended()`; never "Tiket" in the UI |

`defenseLabel($terms, $type, $short)` turns a `thesis_defenses.type` into the right label for either audience. Database values and enum names (`sempro`, `sidang`, `ThesisProject`) stay as they are; only labels changed.

## Last activity

A student's last activity is the single rule `StudentActivityService::latestByProject()`. It shows as "Terakhir aktif 3 hari yang lalu", or "Belum ada aktivitas" when nothing is recorded. There is no "Tidak aktif" flag and no inactivity threshold; the reader judges from the age.

## Retired phrases

Do not write these anywhere in the UI:

| Retired                                                                                                    | Use instead                                            |
| ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| "Proyek", "Proyek Tugas Akhir", "Proyek tesis"                                                             | `finalWork` ("Skripsi", "Tugas Akhir", ...)            |
| "Ujian Proposal", "Proposal" as an exam                                                                    | `proposalExam` / `proposalExamShort`                   |
| "Sempro Tugas Akhir", "Seminar Proposal Tesis", "Seminar Proposal Skripsi", "Seminar Proposal Tugas Akhir" | "Seminar Proposal" / "Sempro"                          |
| "Ujian Akhir", "Ujian Tesis"                                                                               | `finalExam` ("Sidang Skripsi", ..., or "Sidang Akhir") |
| "Proyek akhir"                                                                                             | `finalWork`                                            |
| "Tiket", "Tiket bimbingan"                                                                                 | "Masa aktif bimbingan"                                 |

Event labels already stored in `thesis_project_events.label` keep their old wording; only new events use these labels.
