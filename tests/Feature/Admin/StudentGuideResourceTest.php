<?php

use App\Filament\Resources\StudentGuides\Pages\EditStudentGuide;
use App\Filament\Resources\StudentGuides\Pages\ListStudentGuides;
use App\Filament\Resources\StudentGuides\StudentGuideResource;
use App\Models\AdminProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('admin only sees and edits student guide for their own prodi', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Teknologi Informasi']);

    $admin = User::factory()->asAdmin()->create();

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodiA->id,
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::test(ListStudentGuides::class)
        ->assertCanSeeTableRecords([$prodiA])
        ->assertCanNotSeeTableRecords([$prodiB]);

    $this->get(StudentGuideResource::getUrl('edit', ['record' => $prodiA]))
        ->assertOk()
        ->assertSee('Edit Panduan Mahasiswa - Ilmu Komputer');

    $this->get(StudentGuideResource::getUrl('edit', ['record' => $prodiB]))
        ->assertNotFound();
});

test('admin can update student guide content and audit log is written', function (): void {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $admin = User::factory()->asAdmin()->create(['name' => 'Admin Panduan']);

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::test(EditStudentGuide::class, ['record' => $prodi->getRouteKey()])
        ->fillForm([
            'hero_title' => 'Panduan Ilmu Komputer',
            'hero_subtitle' => 'Panduan akademik resmi untuk mahasiswa Ilmu Komputer.',
            'search_hint' => 'Cari sempro, template, atau revisi',
            'guidance_cards' => [
                [
                    'id' => 'alur-ilkom',
                    'title' => 'Alur Sempro Ilkom',
                    'description' => 'Urutan proses sempro khusus Ilmu Komputer.',
                    'badge' => 'Sempro',
                    'icon' => 'list-checks',
                    'action' => 'template',
                    'bullets' => [
                        'Lengkapi syarat administrasi sempro.',
                        'Pastikan proposal memakai template prodi.',
                    ],
                    'keywords' => ['sempro', 'proposal'],
                ],
            ],
            'faq_items' => [
                [
                    'id' => 'faq-ilkom-1',
                    'question' => 'Kapan batas revisi proposal?',
                    'answer' => 'Ikuti tenggat resmi yang diumumkan prodi.',
                    'tags' => ['revisi', 'proposal'],
                ],
            ],
            'template_docs' => [
                [
                    'id' => 'tpl-ilkom-1',
                    'title' => 'Template Proposal Ilkom',
                    'description' => 'Template proposal resmi prodi Ilmu Komputer.',
                    'format' => 'DOCX',
                    'badge' => 'Resmi',
                    'file_path' => ['guide-templates/program-studi-'.$prodi->id.'/template-ilkom.docx'],
                    'file_name' => 'template-ilkom.docx',
                ],
            ],
            'help_title' => 'Butuh bantuan?',
            'help_description' => 'Mulai dari panduan ini sebelum menghubungi admin.',
            'help_box_title' => 'Langkah aman',
            'help_box_description' => 'Baca panduan, cek FAQ, lalu kirim pesan dengan konteks.',
            'message_template_title' => 'Format pesan admin',
            'message_template_steps' => [
                '1) Sebutkan tahap',
                '2) Sebutkan kebutuhan',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(StudentGuideResource::getUrl('index'));

    $prodi->refresh();

    expect(data_get($prodi->student_guide_content, 'hero_title'))->toBe('Panduan Ilmu Komputer')
        ->and(data_get($prodi->student_guide_content, 'guidance_cards.0.title'))->toBe('Alur Sempro Ilkom')
        ->and(data_get($prodi->student_guide_content, 'template_docs.0.file_name'))->toBe('template-ilkom.docx')
        ->and($prodi->student_guide_updated_by)->toBe($admin->id)
        ->and($prodi->student_guide_updated_at)->not->toBeNull();

    $this->assertDatabaseHas('system_audit_logs', [
        'user_id' => $admin->id,
        'event_type' => 'student_guide_updated',
        'label' => 'Panduan mahasiswa diperbarui',
    ]);
});
