import { runArtisan } from './db';

/** Data the admin dashboard and thesis project detail specs need on top of the seeded scenarios. */
export function lapseMentorship(studentEmail: string): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $project->forceFill(["mentorship_valid_until" => now()->subDays(3)->toDateString()])->save();`,
    ]);
}

export function seedDocuments(studentEmail: string): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); foreach ([["proposal", "Proposal Skripsi Sistem Rekomendasi Topik Bimbingan"], ["revision_submission", "Revisi Proposal Setelah Seminar Hasil Perbaikan Bab Tiga"], ["final_manuscript", "Naskah Akhir Lengkap Dengan Lampiran Pendukung"]] as $index => [$kind, $title]) { App\\Models\\ThesisDocument::query()->updateOrCreate(["project_id" => $project->id, "kind" => $kind, "version_no" => 1], ["uploaded_by_user_id" => $project->student_user_id, "status" => "active", "title" => $title, "storage_disk" => "public", "storage_path" => "thesis/e2e-" . $index . ".pdf", "file_name" => "dokumen-panjang-nama-berkas-" . $index . ".pdf", "mime_type" => "application/pdf", "file_size_kb" => 1, "uploaded_at" => now()->subDays($index + 1)]); }`,
    ]);
}
