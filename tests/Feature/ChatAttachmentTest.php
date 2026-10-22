<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createPembimbingChatThread(): array
{
    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $dosen = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    createActiveMentorshipAssignment($student, $dosen, $admin, AdvisorType::Primary);
    syncThesisSupervisor($student, $dosen, $admin, AdvisorType::Primary->value);

    $thread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'type' => 'pembimbing',
    ]);

    return [$admin, $student, $dosen, $thread];
}

test('chat attachment accepts only the allowed file types and size', function (string $name, string $mime, int $kilobytes, bool $accepted) {
    Storage::fake('public');

    [, $student, , $thread] = createPembimbingChatThread();

    $response = $this->actingAs($student)
        ->post("/mahasiswa/pesan/{$thread->id}/messages", [
            'message' => '',
            'attachment' => UploadedFile::fake()->create($name, $kilobytes, $mime),
        ]);

    if ($accepted) {
        $response->assertRedirect()->assertSessionHasNoErrors();
    } else {
        $response->assertSessionHasErrors('attachment');
    }
})->with([
    'pdf' => ['bab-3.pdf', 'application/pdf', 250, true],
    'doc' => ['bab-3.doc', 'application/msword', 250, true],
    'docx' => ['bab-3.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 250, true],
    'jpg' => ['foto.jpg', 'image/jpeg', 250, true],
    'png' => ['foto.png', 'image/png', 250, true],
    'webp' => ['foto.webp', 'image/webp', 250, true],
    'gif is rejected' => ['animasi.gif', 'image/gif', 250, false],
    'executable is rejected' => ['malware.exe', 'application/x-msdownload', 250, false],
    'oversized pdf is rejected' => ['besar.pdf', 'application/pdf', 10241, false],
]);

test('image attachment is stored as a chat attachment without creating a mentorship document', function () {
    Storage::fake('public');

    [, $student, , $thread] = createPembimbingChatThread();

    $this->actingAs($student)
        ->post("/mahasiswa/pesan/{$thread->id}/messages", [
            'message' => 'Ini foto bukti',
            'attachment' => UploadedFile::fake()->create('foto-bukti.jpg', 120, 'image/jpeg'),
        ])
        ->assertRedirect();

    expect(MentorshipDocument::query()->count())->toBe(0);

    $imageMessage = MentorshipChatMessage::query()
        ->where('mentorship_chat_thread_id', $thread->id)
        ->where('message_type', 'attachment')
        ->firstOrFail();

    expect($imageMessage->attachment_mime)->toStartWith('image/')
        ->and($imageMessage->attachment_name)->toBe('foto-bukti.jpg')
        ->and($imageMessage->related_document_id)->toBeNull();

    Storage::disk('public')->assertExists($imageMessage->attachment_path);
});

test('document title comes from the document name field and never from the caption', function (?string $documentName, string $expectedTitle) {
    Storage::fake('public');

    [, $student, , $thread] = createPembimbingChatThread();

    $this->actingAs($student)
        ->post("/mahasiswa/pesan/{$thread->id}/messages", [
            'message' => 'Mohon dicek revisi terbaru',
            'attachment' => UploadedFile::fake()->create('bab-3-final.pdf', 250, 'application/pdf'),
            'document_name' => $documentName,
        ])
        ->assertRedirect();

    $title = MentorshipDocument::query()->where('category', 'lampiran-chat')->value('title');

    expect($title)->toBe($expectedTitle)
        ->and($title)->not->toBe('Mohon dicek revisi terbaru');
})->with([
    'custom document name' => ['Bab 3 Final', 'Bab 3 Final'],
    'blank falls back to file name' => [null, 'bab-3-final.pdf'],
]);

test('chat attachment preview is limited to images and follows download authorization', function () {
    Storage::fake('public');

    [$admin, $student, $dosen, $thread] = createPembimbingChatThread();
    $otherStudent = createUserWithRole(AppRole::Mahasiswa->value);

    $this->actingAs($student)
        ->post("/mahasiswa/pesan/{$thread->id}/messages", [
            'message' => '',
            'attachment' => UploadedFile::fake()->create('foto.png', 120, 'image/png'),
        ])
        ->assertRedirect();

    $this->actingAs($student)
        ->post("/mahasiswa/pesan/{$thread->id}/messages", [
            'message' => '',
            'attachment' => UploadedFile::fake()->create('bab-3.pdf', 250, 'application/pdf'),
        ])
        ->assertRedirect();

    $imageMessage = MentorshipChatMessage::query()->where('attachment_mime', 'image/png')->firstOrFail();
    $documentMessage = MentorshipChatMessage::query()->where('attachment_mime', 'application/pdf')->firstOrFail();

    $this->actingAs($student)
        ->get(route('files.chat-attachments.preview', ['message' => $imageMessage->id]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($otherStudent)
        ->get(route('files.chat-attachments.preview', ['message' => $imageMessage->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('files.chat-attachments.preview', ['message' => $imageMessage->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('files.chat-attachments.preview', ['message' => $imageMessage->id, 'escalated' => 1]))
        ->assertOk();

    $this->actingAs($student)
        ->get(route('files.chat-attachments.preview', ['message' => $documentMessage->id]))
        ->assertNotFound();
});
