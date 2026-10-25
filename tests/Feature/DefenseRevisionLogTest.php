<?php

use App\Models\KaprodiAssignment;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use App\Models\ThesisRevisionRound;
use App\Models\User;
use App\Support\DefenseRevisionLog;
use Illuminate\Support\Facades\Storage;

function revisionLogExam(string $type = 'sempro', string $status = 'completed'): ThesisDefense
{
    return ThesisDefense::query()->create([
        'project_id' => ThesisProject::factory()->create()->id,
        'type' => $type,
        'attempt_no' => 1,
        'status' => $status,
        'result' => 'pass_with_revision',
    ]);
}

function revisionLogDocument(ThesisDefense $defense, ThesisRevision $revision, User $uploader, int $round, string $kind): ThesisDocument
{
    return ThesisDocument::query()->create([
        'project_id' => $defense->project_id,
        'defense_id' => $defense->id,
        'revision_id' => $revision->id,
        'uploaded_by_user_id' => $uploader->id,
        'kind' => $kind,
        'title' => 'Naskah revisi putaran '.$round,
        'status' => 'active',
        'version_no' => $round,
        'storage_disk' => 'public',
        'storage_path' => 'thesis/'.$revision->id.'/'.$kind.'-'.$round.'.pdf',
        'file_name' => $kind.'-'.$round.'.pdf',
    ]);
}

it('groups the complete log by examiner and chronological round', function (array $expectedRounds, array $expectedExaminers, bool $override): void {
    $defense = revisionLogExam();
    $admin = User::factory()->asAdmin()->create(['name' => 'Admin akademik']);

    // Insert the newer examiner request and later rounds first so DB insertion
    // order cannot accidentally satisfy chronological presentation.
    foreach (array_reverse($expectedExaminers) as $index => $name) {
        $lecturer = User::factory()->asDosen()->create(['name' => $name]);
        $revision = ThesisRevision::factory()->create([
            'project_id' => $defense->project_id,
            'defense_id' => $defense->id,
            'requested_by_user_id' => $lecturer->id,
            'created_at' => now()->subDays($index + 1),
            'status' => $expectedRounds === [1, 2] ? 'submitted' : 'resolved',
            'submitted_at' => now()->subHour(),
            'resolved_at' => $expectedRounds === [1, 2] ? null : now(),
            'resolved_by_user_id' => $override ? $admin->id : $lecturer->id,
            'resolution_notes' => $override ? 'Diselesaikan melalui keputusan administratif.' : 'Butir diterima.',
        ]);

        foreach (array_reverse($expectedRounds) as $roundNo) {
            $manuscript = revisionLogDocument($defense, $revision, $defense->project->student, $roundNo, 'revision_response');
            revisionLogDocument($defense, $revision, $lecturer, $roundNo, 'revision_annotation');
            ThesisRevisionRound::factory()->create([
                'revision_id' => $revision->id,
                'round_no' => $roundNo,
                'status' => $roundNo === 2 ? 'awaiting_review' : 'closed',
                'opened_at' => now()->subDays(4 - $roundNo),
                'student_submitted_at' => now()->subDays(3 - $roundNo),
                'reviewed_at' => $roundNo === 2 ? null : now()->subDay(),
                'student_document_id' => $manuscript->id,
            ]);
            ThesisRevisionItem::factory()->create([
                'revision_id' => $revision->id,
                'round_opened' => $roundNo,
                'description' => $roundNo === 1 ? 'Perjelas metode evaluasi.' : 'Tambahkan sumber terbaru.',
                'status' => $roundNo === 1 ? 'accepted' : 'addressed',
                'student_response' => 'Metode dan referensi sudah diperbaiki.',
                'student_response_location' => 'Bab 3, halaman 45',
                'verification_comment' => $roundNo === 1 ? 'Metode sudah memadai.' : null,
                'verified_by_user_id' => $roundNo === 1 ? $lecturer->id : null,
                'verified_at' => $roundNo === 1 ? now()->subDay() : null,
            ]);
        }
    }

    if ($override) {
        foreach ([$defense->id + 100, $defense->id] as $defenseId) {
            ThesisProjectEvent::query()->create([
                'project_id' => $defense->project_id,
                'actor_user_id' => $admin->id,
                'event_type' => 'revision_resolved',
                'label' => 'Revisi disetujui',
                'description' => 'Diselesaikan melalui keputusan administratif.',
                'payload' => ['source' => 'admin_override', 'defense_id' => $defenseId],
                'occurred_at' => now(),
            ]);
        }
    }

    $log = DefenseRevisionLog::forDefense($defense->fresh());
    expect(array_column($log['examiners'], 'name'))->toBe($expectedExaminers);

    foreach ($log['examiners'] as $examiner) {
        $entry = $examiner['revisions'][0];
        expect(array_column($entry['rounds'], 'roundNo'))->toBe($expectedRounds)
            ->and($entry['rounds'][0]['items'][0])->toMatchArray([
                'description' => 'Perjelas metode evaluasi.',
                'status' => 'accepted',
                'studentResponse' => 'Metode dan referensi sudah diperbaiki.',
                'studentResponseLocation' => 'Bab 3, halaman 45',
                'verificationComment' => 'Metode sudah memadai.',
                'verifiedBy' => $examiner['name'],
            ])
            ->and($entry['rounds'][0]['reviewedAt'])->toEndWith(' WITA')
            ->and($entry['rounds'][0]['annotations'][0]['fileName'])->toBe('revision_annotation-1.pdf')
            ->and($entry['rounds'][0]['manuscript']['fileName'])->toBe('revision_response-1.pdf');

        if ($expectedRounds === [1, 2]) {
            expect($entry['rounds'][1]['items'][0]['description'])->toBe('Tambahkan sumber terbaru.')
                ->and($entry['rounds'][1]['status'])->toBe('awaiting_review')
                ->and($entry['rounds'][1]['reviewedAt'])->toBeNull()
                ->and($entry['rounds'][1]['annotations'][0]['fileName'])->toBe('revision_annotation-2.pdf')
                ->and($entry['rounds'][1]['manuscript']['fileName'])->toBe('revision_response-2.pdf');
        }
    }

    if ($override) {
        expect($log['overrides'])->toHaveCount(1)
            ->and($log['overrides'][0])->toMatchArray(['actor' => 'Admin akademik', 'description' => 'Diselesaikan melalui keputusan administratif.']);
    } else {
        expect($log['overrides'])->toBe([]);
    }
})->with([
    'without revisions' => [[], [], false],
    'one completed round' => [[1], ['Penguji awal'], false],
    'two rounds with current responses' => [[1, 2], ['Penguji awal'], false],
    'several examiners' => [[1], ['Penguji awal', 'Penguji berikutnya'], false],
    'administrative override' => [[1], ['Penguji awal'], true],
]);

it('does not disclose a secret Sidang examiner through the log or manuscript links', function (string $status, array $expectedNames): void {
    $defense = revisionLogExam('sidang', $status);
    foreach (['Pembimbing terlihat' => 'primary_supervisor', 'Penguji rahasia' => 'examiner'] as $name => $role) {
        $lecturer = User::factory()->asDosen()->create(['name' => $name]);
        ThesisDefenseExaminer::query()->create([
            'defense_id' => $defense->id,
            'lecturer_user_id' => $lecturer->id,
            'role' => $role,
            'order_no' => $role === 'examiner' ? 2 : 1,
        ]);
        $revision = ThesisRevision::factory()->create([
            'project_id' => $defense->project_id,
            'defense_id' => $defense->id,
            'requested_by_user_id' => $lecturer->id,
            'notes' => 'Catatan '.$name,
        ]);
        revisionLogDocument($defense, $revision, $lecturer, 1, 'revision_annotation');
    }

    $log = DefenseRevisionLog::forDefense($defense->fresh(), forStudent: true);
    expect(array_column($log['examiners'], 'name'))->toBe($expectedNames);
    if ($status !== 'completed') {
        expect(json_encode($log))->not->toContain('Penguji rahasia');
    }
    expect(DefenseRevisionLog::forDefense($defense)['examiners'])->toHaveCount(2);
})->with([
    'scheduled' => ['scheduled', ['Pembimbing terlihat']],
    'awaiting finalization' => ['awaiting_finalization', ['Pembimbing terlihat']],
    'cancelled' => ['cancelled', ['Pembimbing terlihat']],
    'completed' => ['completed', ['Pembimbing terlihat', 'Penguji rahasia']],
]);

it('allows a dual-role kaprodi to download revision documents only with capability and prodi scope', function (bool $sameProdi, bool $canDownload, bool $allowed): void {
    Storage::fake('public');
    $defense = revisionLogExam();
    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create([
        'user_id' => $kaprodi->id,
        'program_studi_id' => $sameProdi ? $defense->project->program_studi_id : ProgramStudi::factory()->create()->id,
        'capabilities' => [KaprodiAssignment::CAPABILITY_DOWNLOAD_DOCUMENTS => $canDownload],
    ]);
    $lecturer = User::factory()->asDosen()->create();
    $revision = ThesisRevision::factory()->create([
        'project_id' => $defense->project_id,
        'defense_id' => $defense->id,
        'requested_by_user_id' => $lecturer->id,
    ]);
    $document = revisionLogDocument($defense, $revision, $lecturer, 1, 'revision_annotation');
    Storage::disk('public')->put($document->storage_path, 'Seeded revision manuscript.');

    $response = $this->actingAs($kaprodi->refresh())->get(route('files.thesis-documents.download', $document));
    if ($allowed) {
        $response->assertOk()->assertDownload('revision_annotation-1.pdf');
    } else {
        $response->assertForbidden();
    }
})->with([
    'own prodi and enabled capability' => [true, true, true],
    'different prodi' => [false, true, false],
    'disabled download capability' => [true, false, false],
]);
