<?php

use App\Enums\AppRole;
use App\Models\KaprodiAssignment;
use App\Models\MentorshipChatThread;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;

it('opens a private chat with a lecturer from Dosen Prodi and keeps the kaprodi workspace', function (): void {
    $kaprodi = User::factory()->create(['last_active_role' => AppRole::Kaprodi->value]);
    $kaprodi->roles()->sync([
        Role::query()->firstOrCreate(['name' => AppRole::Kaprodi->value])->id,
        Role::query()->firstOrCreate(['name' => AppRole::Dosen->value])->id,
    ]);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => ProgramStudi::factory()->create()->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing']);

    $response = $this->actingAs($kaprodi)
        ->post(route('dosen.pesan.private.store'), ['recipient_id' => $lecturer->id]);

    $thread = MentorshipChatThread::query()
        ->where('type', 'private')
        ->firstOrFail();

    $response->assertRedirect(route('dosen.pesan', ['thread' => $thread->id]));

    expect($kaprodi->refresh()->last_active_role)->toBe(AppRole::Kaprodi->value);
});
