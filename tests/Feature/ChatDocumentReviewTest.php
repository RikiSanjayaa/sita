<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\ChatMessagePayloadBuilder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Builds a student's upload delivered to two lecturers: one MentorshipDocument copy per
 * lecturer (shared document_group and version) and a document_event in the thread that
 * points at the first copy, as the attachment flow does.
 *
 * @return array{student: User, first: User, second: User, copies: array{first: MentorshipDocument, second: MentorshipDocument}, message: MentorshipChatMessage}
 */
function createSharedChatUpload(): array
{
    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $first = createUserWithRole(AppRole::Dosen->value);
    $second = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    createActiveMentorshipAssignment($student, $first, $admin, AdvisorType::Primary);
    createActiveMentorshipAssignment($student, $second, $admin, AdvisorType::Secondary);

    $group = fake()->uuid();

    $firstCopy = MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $first->id,
        'document_group' => $group,
        'version_number' => 1,
        'status' => 'submitted',
        'uploaded_by_user_id' => $student->id,
    ]);

    $secondCopy = MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $second->id,
        'document_group' => $group,
        'version_number' => 1,
        'status' => 'submitted',
        'uploaded_by_user_id' => $student->id,
    ]);

    $thread = MentorshipChatThread::factory()->create([
        'student_user_id' => $student->id,
    ]);

    $message = MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $student->id,
        'related_document_id' => $firstCopy->id,
        'message_type' => 'document_event',
        'message' => '',
        'attachment_name' => $firstCopy->file_name,
    ]);

    return [
        'student' => $student,
        'first' => $first,
        'second' => $second,
        'copies' => ['first' => $firstCopy, 'second' => $secondCopy],
        'message' => $message,
    ];
}

/**
 * The payload for one viewer, built the way the dosen index builds it: the viewer's review map first, then the message.
 *
 * @return array<string, mixed>
 */
function payloadFor(MentorshipChatMessage $message, ?User $viewer): array
{
    $builder = app(ChatMessagePayloadBuilder::class);
    $ownReviews = $viewer === null
        ? []
        : $builder->ownReviewsFor($viewer, collect([$message->relatedDocument])->filter()->values());

    return $builder->build($message, $ownReviews);
}

test('the lecturer who received the upload gets their own review copy in the payload', function () {
    ['first' => $first, 'copies' => $copies, 'message' => $message] = createSharedChatUpload();

    $payload = payloadFor($message, $first);

    expect($payload['ownReview'])->toBe([
        'id' => $copies['first']->id,
        'status' => 'submitted',
    ]);
});

test('another lecturer on the same upload gets only their own copy, never the other copy', function () {
    ['second' => $second, 'copies' => $copies, 'message' => $message] = createSharedChatUpload();

    $payload = payloadFor($message, $second);

    expect($payload['ownReview'])->toBe([
        'id' => $copies['second']->id,
        'status' => 'submitted',
    ]);
    expect($payload['ownReview']['id'])->not->toBe($copies['first']->id);
});

test('the payload carries no review for the student or for a broadcast without viewer', function (string $viewer) {
    ['student' => $student, 'message' => $message] = createSharedChatUpload();

    $user = $viewer === 'student' ? $student : null;

    $payload = payloadFor($message, $user);

    expect($payload)->toHaveKey('ownReview');
    expect($payload['ownReview'])->toBeNull();
})->with([
    'student viewer' => ['student'],
    'no viewer (broadcast)' => ['none'],
]);

test('a non-document message has no review copy even for a lecturer viewer', function () {
    ['first' => $first, 'message' => $message] = createSharedChatUpload();

    $message->update(['message_type' => 'text', 'related_document_id' => null]);

    $payload = payloadFor($message->fresh(), $first);

    expect($payload['ownReview'])->toBeNull();
});

test('the review copy keeps the lecturer own status after they review it', function () {
    ['first' => $first, 'second' => $second, 'copies' => $copies, 'message' => $message] = createSharedChatUpload();

    $copies['first']->update(['status' => 'needs_revision']);

    expect(payloadFor($message->fresh(), $first)['ownReview'])->toBe(['id' => $copies['first']->id, 'status' => 'needs_revision']);
    expect(payloadFor($message->fresh(), $second)['ownReview']['status'])->toBe('submitted');
});

test('document events are labelled as upload or review by their sender', function () {
    ['first' => $first, 'message' => $message] = createSharedChatUpload();

    expect(payloadFor($message, $first)['documentEventKind'])->toBe('upload');

    $message->update(['sender_user_id' => $first->id]);

    expect(payloadFor($message->fresh(), $first)['documentEventKind'])->toBe('review');
});

test('review and status events and lecturer-sent files carry no review copy', function (string $case) {
    ['first' => $first, 'student' => $student, 'message' => $message] = createSharedChatUpload();

    $message->update(match ($case) {
        'review event by the lecturer' => ['sender_user_id' => $first->id, 'message' => 'menandai lampiran perlu revisi.', 'attachment_name' => null],
        'file sent by the lecturer' => ['sender_user_id' => $first->id],
        'file sent by the other lecturer' => ['sender_user_id' => createUserWithRole(AppRole::Dosen->value)->id],
    });

    expect(payloadFor($message->fresh(), $first)['ownReview'])->toBeNull();
})->with([
    'review event by the lecturer',
    'file sent by the lecturer',
    'file sent by the other lecturer',
]);

test('a lecturer can review their copy from chat and the status is stored on that copy', function (string $status, ?string $notes) {
    ['first' => $first, 'copies' => $copies] = createSharedChatUpload();

    $this->actingAs($first)
        ->post(route('dosen.dokumen-revisi.review', $copies['first']), [
            'status' => $status,
            'revision_notes' => $notes,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($copies['first']->fresh()->status)->toBe($status);
    expect($copies['second']->fresh()->status)->toBe('submitted');
})->with([
    'approved' => ['approved', null],
    'needs revision with notes' => ['needs_revision', 'Perbaiki sitasi.'],
]);

test('the review request is validated by its form request', function (array $payload, string $field) {
    ['first' => $first, 'copies' => $copies] = createSharedChatUpload();

    $this->actingAs($first)
        ->post(route('dosen.dokumen-revisi.review', $copies['first']), $payload)
        ->assertSessionHasErrors($field);

    expect($copies['first']->fresh()->status)->toBe('submitted');
})->with([
    'status missing' => [['revision_notes' => 'x'], 'status'],
    'status unknown' => [['status' => 'rejected'], 'status'],
    'status submitted is not a review outcome' => [['status' => 'submitted'], 'status'],
    'notes must be a string' => [['status' => 'needs_revision', 'revision_notes' => ['x']], 'revision_notes'],
]);

test('a lecturer cannot review another lecturer copy of the same upload', function () {
    ['second' => $second, 'copies' => $copies] = createSharedChatUpload();

    $this->actingAs($second)
        ->post(route('dosen.dokumen-revisi.review', $copies['first']), [
            'status' => 'approved',
        ])
        ->assertForbidden();

    expect($copies['first']->fresh()->status)->toBe('submitted');
});

test('the dosen index looks up review copies in one query however many document events the thread has', function () {
    ['first' => $first, 'student' => $student, 'message' => $message] = createSharedChatUpload();

    $thread = MentorshipChatThread::query()->findOrFail($message->mentorship_chat_thread_id);
    $thread->update(['type' => 'pembimbing']);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $student->mahasiswaProfile->program_studi_id,
            'phase' => 'sempro',
            'state' => 'active',
            'started_at' => now()->subWeek(),
        ])->id,
        'lecturer_user_id' => $first->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'assigned_by' => $first->id,
        'started_at' => now(),
    ]);

    $documentQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$documentQueries): void {
        if (str_contains($query->sql, 'mentorship_documents')) {
            $documentQueries[] = $query->sql;
        }
    });

    $this->actingAs($first)
        ->get(route('dosen.pesan'))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 1)->has('threads.0.messages', 1));
    $queriesWithOneEvent = count($documentQueries);

    expect($queriesWithOneEvent)->toBeGreaterThan(0);

    for ($i = 0; $i < 4; $i++) {
        $copy = MentorshipDocument::factory()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $first->id,
            'document_group' => fake()->uuid(),
            'version_number' => 1,
            'status' => 'submitted',
            'uploaded_by_user_id' => $student->id,
        ]);

        MentorshipChatMessage::factory()->create([
            'mentorship_chat_thread_id' => $thread->id,
            'sender_user_id' => $student->id,
            'related_document_id' => $copy->id,
            'message_type' => 'document_event',
            'message' => '',
            'attachment_name' => $copy->file_name,
        ]);
    }

    $documentQueries = [];
    $this->actingAs($first)
        ->get(route('dosen.pesan'))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 1)->has('threads.0.messages', 5));

    expect($documentQueries)->toHaveCount($queriesWithOneEvent);
});
