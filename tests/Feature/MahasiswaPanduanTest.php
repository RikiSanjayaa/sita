<?php

use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('mahasiswa sees panduan content from their own prodi', function (): void {
    $prodi = ProgramStudi::factory()->create([
        'name' => 'Ilmu Komputer',
    ]);

    $prodi->update([
        'student_guide_content' => [
            'hero_title' => 'Panduan Ilmu Komputer',
            'hero_subtitle' => 'Panduan khusus mahasiswa Ilmu Komputer.',
            'search_hint' => 'Cari proposal, sempro, atau template',
            'guidance_cards' => [
                [
                    'id' => 'alur-ilkom',
                    'title' => 'Alur Ilkom',
                    'description' => 'Panduan inti untuk mahasiswa Ilkom.',
                    'badge' => 'Khusus Prodi',
                    'icon' => 'list-checks',
                    'action' => 'template',
                    'bullets' => ['Ikuti template resmi prodi.'],
                    'keywords' => ['template'],
                ],
            ],
            'faq_items' => [
                [
                    'id' => 'faq-ilkom',
                    'question' => 'Apakah template prodi wajib?',
                    'answer' => 'Ya, gunakan template resmi prodi.',
                    'tags' => ['template'],
                ],
            ],
            'template_docs' => [
                [
                    'id' => 'tpl-ilkom',
                    'title' => 'Template Proposal Ilkom',
                    'description' => 'Dokumen template resmi prodi.',
                    'format' => 'DOCX',
                    'badge' => 'Resmi',
                    'file_path' => 'guide-templates/program-studi-'.$prodi->id.'/template-ilkom.docx',
                    'file_name' => 'template-ilkom.docx',
                ],
            ],
            'help_title' => 'Butuh bantuan?',
            'help_description' => 'Baca panduan ini sebelum kontak admin.',
            'help_box_title' => 'Urutan aman',
            'help_box_description' => 'Cek panduan, cek FAQ, lalu kirim pesan.',
            'message_template_title' => 'Format pesan',
            'message_template_steps' => ['1) Tahap', '2) Kebutuhan'],
        ],
    ]);

    $student = User::factory()->asMahasiswa()->create();

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510999',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($student)
        ->get(route('mahasiswa.panduan'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('panduan')
            ->where('pageTitle', 'Panduan Ilmu Komputer')
            ->where('pageSubtitle', 'Panduan khusus mahasiswa Ilmu Komputer.')
            ->where('guidanceCards.0.title', 'Alur Ilkom')
            ->where('faqItems.0.question', 'Apakah template prodi wajib?')
            ->where('templateDocs.0.fileName', 'template-ilkom.docx')
            ->where('templateDocs.0.downloadUrl', Storage::disk('public')->url('guide-templates/program-studi-'.$prodi->id.'/template-ilkom.docx'))
            ->where('helpContent.title', 'Butuh bantuan?'));
});
