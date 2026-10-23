# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Mahasiswa** (D3, S1, S2) writing a thesis. Mostly on a phone. They submit a title and proposal, request bimbingan sessions, upload documents, chat with supervisors and examiners, and follow their own progress through sempro, research, sidang and revisions.
- **Dosen** supervising and examining students. Mostly on a laptop. They accept or reschedule bimbingan, review uploaded documents, grade exams, verify revisions, and write notes on students.
- **Kaprodi** (head of study programme; also holds the dosen role). On a laptop. They monitor every student in the programme, assign supervisors, schedule sempro and sidang, and manage lecturer quotas.
- **Admin** (faculty staff). On a laptop, in the Filament panel at `/admin`. They manage users (including Excel import), study programmes, thesis records, announcements and the audit log.
- **Public visitors** are members of the Universitas Bumigora community looking up information without logging in: upcoming exam schedules, thesis topics, supervisors, and active students.

## Product Purpose

SiTA (Sistem Informasi Tugas Akhir) records each student's thesis journey at Universitas Bumigora, from title submission to graduation: bimbingan, documents, seminar proposal (sempro), sidang and revisions. Success means every party can see where a thesis stands and what the next step is, without asking someone on WhatsApp.

## Positioning

The thesis lifecycle of one faculty in one place: the same record drives the student's progress view, the lecturer's review queue, the head of programme's monitoring, and the public schedule. It documents progress; informal coordination (agreeing on dates, confirming attendance) stays on WhatsApp or in person, outside the app.

## Operating Context

- Lifecycle phases: `title_review` -> `sempro` -> `research` -> `sidang` -> `completed`. Exams are sempro (Ujian Sinopsis for OBE curricula) and sidang.
- Students have up to two supervisors (Pembimbing 1 and 2) and exam panels of examiners (Penguji).
- Real-time chat threads per student (bimbingan, sempro, sidang) with file attachments and document review events; notifications via Reverb.
- Lecturers hand out WhatsApp contact for coordination; the app links to it.
- Laravel 12 + Inertia v2 + React 19 + Tailwind v4 for the portals; Filament for the admin panel; deployed on a self-hosted Docker stack.

## Capabilities and Constraints

- Indonesian UI throughout.
- Academic labels differ per degree (D3/S1/S2) and per OBE curriculum. Every label comes from `App\Support\AcademicTerminology` / `resources/js/lib/academic-terminology.ts`; terms are defined in `docs/GLOSSARY.md`.
- Privacy: titles follow the public-title rule on public pages; lecturer notes and internal data never reach public pages.
- Light and dark themes. The UBG Blue theme is the default and the accessible one.
- Planned: a progress page of all students for every logged-in user (#71), to let students follow their peers' progress.

## Brand Commitments

- Name "SiTA", institution "Universitas Bumigora" (UBG), logo `public/Logo-UBG.svg`.
- UBG Blue as the primary brand colour.

## Evidence on Hand

- Seeded sample data only (`database/seeders/`); no real testimonials, statistics or institutional claims exist. Do not fabricate them.
- Screenshots of the current UI in `docs/screenshots/`.

## Product Principles

- Next step first: every screen answers "where does this thesis stand and what happens next".
- One record, many views: the same data reads consistently across roles; a status means the same thing everywhere.
- Fit the device of the role: phone-first for mahasiswa, laptop-first for dosen, kaprodi and admin, without breaking the other.
- Document, do not coordinate: build only the workflow the faculty asked for; leave scheduling chatter to WhatsApp.

## Accessibility & Inclusion

- WCAG 2.1 AA contrast for text and status colours in light and dark themes (see `DESIGN.md`).
