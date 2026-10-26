import fs from 'node:fs';
import path from 'node:path';

import { type Browser, type BrowserContext, type Page } from '@playwright/test';

import { repositoryRoot, runArtisan } from './db';
import {
    goldenPathStudent,
    newGoldenStudentContext,
    removeGoldenPathStudent,
} from './golden-path';
import { authStatePath } from './thesis-fixtures';

export type DefenseType = 'sempro' | 'sidang';
export type ExamFile = {
    title: string;
    fileName: string;
    category: string;
    version: number;
    kindLabel: string;
    bytes: Buffer;
};

const pdf = fs.readFileSync(
    path.join(repositoryRoot, 'tests/fixtures/panduan-template.pdf'),
);

function examFile(
    title: string,
    fileName: string,
    category: string,
    version: number,
    kindLabel: string,
): ExamFile {
    return {
        title,
        fileName,
        category,
        version,
        kindLabel,
        bytes: Buffer.concat([
            pdf,
            Buffer.from(`\n% defense-documents: ${fileName}\n`),
        ]),
    };
}

export function defenseDocumentFiles(type: DefenseType): ExamFile[] {
    const main =
        type === 'sempro'
            ? examFile(
                  'Proposal pilihan mahasiswa',
                  'proposal-pilihan-v3.pdf',
                  'proposal',
                  3,
                  'Naskah Proposal',
              )
            : examFile(
                  'Naskah akhir pilihan mahasiswa',
                  'naskah-akhir-pilihan-v3.pdf',
                  'final-manuscript',
                  3,
                  'Naskah Akhir',
              );
    return type === 'sempro'
        ? [main]
        : [
              main,
              examFile(
                  'Lampiran data penelitian pilihan mahasiswa',
                  'lampiran-data-pilihan-v6.pdf',
                  'lampiran-sidang',
                  6,
                  'Lampiran',
              ),
          ];
}

export const defenseDocumentFixture = {
    studentName: goldenPathStudent.name,
    title: 'Pelacakan Riset untuk Pemulihan Alur Skripsi',
    uploadedAt: '14 Sep 2026 09:15',
    groupMessage: 'Diskusi berkas ujian pada grup yang sudah tersedia.',
    priorFileName: 'berkas-percobaan-sebelumnya.pdf',
    annotationFileName: 'anotasi-penguji-bukan-berkas-ujian.pdf',
    revisionFileName: 'perbaikan-bukan-berkas-ujian.pdf',
} as const;

export function newerFileName(file: ExamFile): string {
    return file.fileName.replace(`v${file.version}`, `v${file.version + 1}`);
}

// Only initial conditions use Tinker. The student selects all snapshots through
// the real thesis form, and the examiner follows the sheet's real file links.
function seedDefenseDocuments(type: DefenseType): void {
    const files = defenseDocumentFiles(type).map((file) => ({
        ...file,
        bytes: file.bytes.toString('base64'),
        newerFileName: newerFileName(file),
    }));
    const payload = Buffer.from(JSON.stringify(files)).toString('base64');
    runArtisan([
        'tinker',
        `--execute=
Illuminate\\Support\\Carbon::setTestNow('2026-10-26 08:00:00');
app(Database\\Seeders\\RecoveryScenarioSeeder::class)->run('${type}');
$student = App\\Models\\User::query()->where('email', '${goldenPathStudent.email}')->firstOrFail();
$project = App\\Models\\ThesisProject::query()->where('student_user_id', $student->id)->firstOrFail();
$defense = $project->defenses()->where('type', '${type}')->firstOrFail();
$defense->forceFill(['attempt_no' => 2, 'scheduled_for' => '2030-01-14 01:00:00'])->save();
$files = json_decode(base64_decode('${payload}'), true, flags: JSON_THROW_ON_ERROR);
foreach ($files as $file) {
    foreach ([false, true] as $newer) {
        $name = $newer ? $file['newerFileName'] : $file['fileName'];
        $storagePath = 'documents/mahasiswa/'.$student->id.'/defense-documents/'.$name;
        Illuminate\\Support\\Facades\\Storage::disk('public')->put($storagePath, base64_decode($file['bytes']).($newer ? '\\n% newer unselected version' : ''));
        $workspace = App\\Models\\MentorshipDocument::query()->create([
            'student_user_id' => $student->id,
            'title' => $file['title'],
            'category' => $file['category'],
            'document_group' => $student->id.':defense-documents:'.$file['category'],
            'version_number' => $file['version'] + (int) $newer,
            'file_name' => $name,
            'storage_disk' => 'public',
            'storage_path' => $storagePath,
            'stored_file_name' => $name,
            'mime_type' => 'application/pdf',
            'file_size_kb' => 1,
            'status' => 'approved',
            'uploaded_by_user_id' => $student->id,
            'uploaded_by_role' => 'mahasiswa',
        ])->forceFill([
            'created_at' => Carbon\\CarbonImmutable::parse($newer ? '2026-09-15T01:15:00Z' : '2026-09-14T01:15:00Z')->setTimezone(config('app.timezone')),
            'updated_at' => Carbon\\CarbonImmutable::parse($newer ? '2026-09-15T01:15:00Z' : '2026-09-14T01:15:00Z')->setTimezone(config('app.timezone')),
        ]);
        $workspace->save();
        $reviewer = App\\Models\\User::query()->where('email', '${type === 'sempro' ? 'dosen3' : 'dosen5'}@sita.test')->firstOrFail();
        $workspace->replicate()->forceFill(['lecturer_user_id' => $reviewer->id])->save();
    }
}
$prior = App\\Models\\ThesisDefense::query()->create([
    'project_id' => $project->id,
    'title_version_id' => $defense->title_version_id,
    'type' => '${type}',
    'attempt_no' => 1,
    'status' => 'completed',
    'result' => 'fail',
    'scheduled_for' => '2026-09-01 01:00:00',
    'created_by' => $defense->created_by,
]);
foreach ($defense->examiners as $examiner) {
    App\\Models\\ThesisDefenseExaminer::query()->create([
        'defense_id' => $prior->id,
        'lecturer_user_id' => $examiner->lecturer_user_id,
        'role' => $examiner->role,
        'order_no' => $examiner->order_no,
        'decision' => 'fail',
        'assigned_by' => $defense->created_by,
    ]);
}
foreach ([
    [$prior, '${type === 'sempro' ? 'proposal' : 'final_manuscript'}', '${defenseDocumentFixture.priorFileName}'],
    [$defense, 'revision_annotation', '${defenseDocumentFixture.annotationFileName}'],
    [$defense, 'revision_response', '${defenseDocumentFixture.revisionFileName}'],
] as [$owner, $kind, $name]) {
    $storagePath = 'thesis/defense-documents/'.$student->id.'/'.$name;
    Illuminate\\Support\\Facades\\Storage::disk('public')->put($storagePath, file_get_contents(base_path('tests/fixtures/panduan-template.pdf')).'\\n% '.$name);
    App\\Models\\ThesisDocument::query()->create([
        'project_id' => $project->id,
        'defense_id' => $owner->id,
        'title_version_id' => $owner->title_version_id,
        'uploaded_by_user_id' => $student->id,
        'kind' => $kind,
        'version_no' => 1,
        'status' => 'active',
        'title' => $name,
        'storage_disk' => 'public',
        'storage_path' => $storagePath,
        'stored_file_name' => $name,
        'file_name' => $name,
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'uploaded_at' => '2026-09-01 01:15:00',
    ]);
}
$thread = App\\Models\\MentorshipChatThread::query()->where('student_user_id', $student->id)->where('type', '${type}')->where('context_id', $defense->id)->firstOrFail();
App\\Models\\MentorshipChatMessage::query()->create([
    'mentorship_chat_thread_id' => $thread->id,
    'sender_user_id' => $student->id,
    'message_type' => 'text',
    'message' => '${defenseDocumentFixture.groupMessage}',
    'sent_at' => '2026-10-26 07:00:00',
]);`,
    ]);
}

export function replaceSelectedWorkspaceBytes(type: DefenseType): void {
    const names = defenseDocumentFiles(type).map((file) => file.fileName);
    const payload = Buffer.from(JSON.stringify(names)).toString('base64');
    runArtisan([
        'tinker',
        `--execute=$student = App\\Models\\User::query()->where('email', '${goldenPathStudent.email}')->firstOrFail(); $names = json_decode(base64_decode('${payload}'), true); foreach (App\\Models\\MentorshipDocument::query()->where('student_user_id', $student->id)->whereIn('file_name', $names)->get() as $document) { Illuminate\\Support\\Facades\\Storage::disk($document->storage_disk)->put($document->storage_path, file_get_contents(base_path('tests/fixtures/panduan-template.pdf')).'\\n% workspace changed after snapshot selection'); }`,
    ]);
}

export async function withDefenseDocuments(
    browser: Browser,
    type: DefenseType,
    run: (workspace: {
        student: Page;
        examiner: Page;
        examinerContext: BrowserContext;
    }) => Promise<void>,
): Promise<void> {
    const contexts: BrowserContext[] = [];
    try {
        seedDefenseDocuments(type);
        const studentContext = await newGoldenStudentContext(browser);
        contexts.push(studentContext);
        const examinerContext = await browser.newContext({
            storageState: authStatePath(
                type === 'sempro' ? 'dosen3' : 'dosen5',
            ),
            viewport: { width: 1280, height: 900 },
            locale: 'id-ID',
            timezoneId: 'Asia/Makassar',
            reducedMotion: 'reduce',
        });
        contexts.push(examinerContext);
        await run({
            student: await studentContext.newPage(),
            examiner: await examinerContext.newPage(),
            examinerContext,
        });
    } finally {
        try {
            await Promise.all(contexts.map((context) => context.close()));
        } finally {
            try {
                runArtisan([
                    'tinker',
                    `--execute=foreach (App\\Models\\MentorshipChatThread::query()->where('type', 'private')->whereHas('participants.user', fn($query) => $query->where('email', '${goldenPathStudent.email}'))->get() as $thread) { $thread->messages()->delete(); $thread->delete(); }`,
                ]);
            } finally {
                removeGoldenPathStudent();
            }
        }
    }
}
