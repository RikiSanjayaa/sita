<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentGuideContent
{
    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'list-checks' => 'Checklist',
            'calendar-clock' => 'Jadwal',
            'upload-cloud' => 'Upload',
            'message-square-text' => 'Pesan',
            'file-text' => 'Dokumen',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actionOptions(): array
    {
        return [
            'none' => 'Tanpa tombol',
            'template' => 'Lihat template',
            'schedule' => 'Ajukan bimbingan',
            'upload' => 'Buka upload dokumen',
            'message' => 'Buka pesan',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'hero_title' => 'Panduan',
            'hero_subtitle' => 'Ringkas, bisa dicari, dan siap membantu kamu bergerak',
            'search_hint' => 'Cari kata kunci: revisi, bimbingan, upload...',
            'guidance_cards' => [
                [
                    'id' => 'alur',
                    'title' => 'Alur Pengajuan',
                    'description' => 'Dari ide sampai disetujui, tanpa bolak-balik.',
                    'badge' => 'Langkah awal',
                    'icon' => 'list-checks',
                    'action' => 'template',
                    'bullets' => [
                        'Siapkan judul, ringkasan 3-5 paragraf, dan rencana metode singkat.',
                        'Ajukan judul terlebih dulu, lalu tunggu status (Disetujui/Revisi).',
                        'Jika revisi, fokus ke poin catatan: perbaiki dan ajukan versi berikutnya.',
                        'Jaga penamaan versi: v1, v2, v3 agar riwayat rapi.',
                    ],
                    'keywords' => ['judul', 'pengajuan', 'revisi', 'status', 'versi'],
                ],
                [
                    'id' => 'jadwal',
                    'title' => 'Jadwal & Bimbingan',
                    'description' => 'Cara minta bimbingan yang cepat disetujui.',
                    'badge' => 'Biar cepat',
                    'icon' => 'calendar-clock',
                    'action' => 'schedule',
                    'bullets' => [
                        'Tawarkan 2-3 opsi waktu dan cantumkan topik yang spesifik.',
                        'Lampirkan progres terakhir (tautan atau dokumen) sebelum mengajukan.',
                        'Tulis tujuan pertemuan: butuh keputusan, review, atau diskusi.',
                        'Catat hasil bimbingan, lalu tindak lanjuti maksimal 1-3 hari.',
                    ],
                    'keywords' => ['jadwal', 'bimbingan', 'pertemuan', 'topik', 'waktu'],
                ],
                [
                    'id' => 'upload',
                    'title' => 'Upload & Revisi',
                    'description' => 'Unggah dokumen dengan rapi dan mudah ditinjau.',
                    'badge' => 'Dokumen',
                    'icon' => 'upload-cloud',
                    'action' => 'upload',
                    'bullets' => [
                        'Pastikan format sesuai (mis. PDF) dan ukuran file wajar.',
                        'Gunakan nama file konsisten: Bab_1_Nama_v2.pdf.',
                        'Tulis ringkasan perubahan singkat pada revisi (3-5 poin).',
                        'Unggah hanya yang relevan; jangan campur dokumen mentah di final.',
                    ],
                    'keywords' => ['upload', 'unggah', 'dokumen', 'pdf', 'nama file'],
                ],
                [
                    'id' => 'komunikasi',
                    'title' => 'Komunikasi',
                    'description' => 'Sopan, jelas, dan enak ditindaklanjuti pembimbing.',
                    'badge' => 'Etika',
                    'icon' => 'message-square-text',
                    'action' => 'message',
                    'bullets' => [
                        'Mulai dengan konteks 1 kalimat: tahap apa dan tujuan pesan.',
                        'Ajukan pertanyaan yang bisa dijawab ya/tidak atau pilihan A/B.',
                        'Sertakan tautan/dokumen yang dirujuk, jangan hanya "sudah saya kirim".',
                        'Akhiri dengan permintaan tindakan dan tenggat yang realistis.',
                    ],
                    'keywords' => ['pesan', 'komunikasi', 'pembimbing', 'tenggat', 'konteks'],
                ],
            ],
            'faq_items' => [
                [
                    'id' => 'faq-1',
                    'question' => 'Saya harus mulai dari mana?',
                    'answer' => 'Mulai dari Alur Pengajuan. Siapkan judul dan ringkasan singkat, lalu ajukan judul. Setelah status jelas, baru susun dokumen bab per bab dan ajukan bimbingan berkala.',
                    'tags' => ['alur', 'judul'],
                ],
                [
                    'id' => 'faq-2',
                    'question' => 'Berapa sering sebaiknya bimbingan?',
                    'answer' => 'Idealnya 1-2 minggu sekali, atau setiap ada perubahan besar yang butuh keputusan. Yang penting: selalu bawa progres yang bisa ditinjau agar pertemuan efektif.',
                    'tags' => ['bimbingan', 'jadwal'],
                ],
                [
                    'id' => 'faq-3',
                    'question' => 'Apa yang perlu ditulis saat mengunggah revisi?',
                    'answer' => 'Tulis ringkasan perubahan 3-5 poin: bagian apa yang diubah, alasan singkat, dan apa yang ingin ditinjau. Ini membuat pembimbing cepat menemukan perbaikan.',
                    'tags' => ['revisi', 'upload'],
                ],
                [
                    'id' => 'faq-4',
                    'question' => 'Bagaimana kalau pembimbing lama merespons?',
                    'answer' => 'Kirim follow-up singkat setelah 2-3 hari kerja dengan konteks dan tautan dokumen. Jika tetap belum ada respons, ajukan jadwal bimbingan dengan opsi waktu yang jelas.',
                    'tags' => ['komunikasi', 'follow-up'],
                ],
                [
                    'id' => 'faq-5',
                    'question' => 'Nama file yang rapi seperti apa?',
                    'answer' => 'Gunakan pola konsisten: Tahap_Bab_Nama_vX.ext, misalnya Bab_2_MuhammadAkbar_v3.pdf. Hindari spasi ganda dan gunakan versi agar riwayat tidak membingungkan.',
                    'tags' => ['dokumen', 'versi'],
                ],
            ],
            'template_docs' => [
                [
                    'id' => 'tpl-1',
                    'title' => 'Template Proposal',
                    'description' => 'Kerangka proposal sesuai format kampus.',
                    'format' => 'DOCX',
                    'badge' => 'Wajib',
                    'file_path' => null,
                    'file_name' => null,
                ],
                [
                    'id' => 'tpl-2',
                    'title' => 'Template Bab 1-3',
                    'description' => 'Struktur penulisan awal (Pendahuluan sampai Metode).',
                    'format' => 'DOCX',
                    'badge' => null,
                    'file_path' => null,
                    'file_name' => null,
                ],
                [
                    'id' => 'tpl-3',
                    'title' => 'Template Logbook Bimbingan',
                    'description' => 'Catatan pertemuan dan tindak lanjut.',
                    'format' => 'XLSX',
                    'badge' => null,
                    'file_path' => null,
                    'file_name' => null,
                ],
                [
                    'id' => 'tpl-4',
                    'title' => 'Template Slide Seminar',
                    'description' => 'Slide ringkas untuk presentasi proposal/sidang.',
                    'format' => 'PPTX',
                    'badge' => null,
                    'file_path' => null,
                    'file_name' => null,
                ],
            ],
            'help_title' => 'Bantuan',
            'help_description' => 'Jika masih bingung, mulai dari sini.',
            'help_box_title' => 'Cek dulu yang paling dekat',
            'help_box_description' => 'Biasanya masalah selesai setelah: cari kata kunci, baca FAQ, lalu kirim pesan dengan konteks.',
            'message_template_title' => 'Format pesan yang efektif',
            'message_template_steps' => [
                '1) Tahap: Bab 2 / Proposal / Revisi',
                '2) Tujuan: minta review atau keputusan',
                '3) Tautan: dokumen yang dirujuk',
                '4) Pertanyaan: pilihan A/B atau ya/tidak',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $content
     * @return array<string, mixed>
     */
    public static function normalize(?array $content): array
    {
        $content ??= [];
        $defaults = self::defaults();

        return [
            'hero_title' => self::cleanString($content['hero_title'] ?? $defaults['hero_title']),
            'hero_subtitle' => self::cleanString($content['hero_subtitle'] ?? $defaults['hero_subtitle']),
            'search_hint' => self::cleanString($content['search_hint'] ?? $defaults['search_hint']),
            'guidance_cards' => self::normalizeGuidanceCards($content['guidance_cards'] ?? $defaults['guidance_cards']),
            'faq_items' => self::normalizeFaqItems($content['faq_items'] ?? $defaults['faq_items']),
            'template_docs' => self::normalizeTemplateDocs($content['template_docs'] ?? $defaults['template_docs']),
            'help_title' => self::cleanString($content['help_title'] ?? $defaults['help_title']),
            'help_description' => self::cleanString($content['help_description'] ?? $defaults['help_description']),
            'help_box_title' => self::cleanString($content['help_box_title'] ?? $defaults['help_box_title']),
            'help_box_description' => self::cleanString($content['help_box_description'] ?? $defaults['help_box_description']),
            'message_template_title' => self::cleanString($content['message_template_title'] ?? $defaults['message_template_title']),
            'message_template_steps' => self::cleanStringList($content['message_template_steps'] ?? $defaults['message_template_steps']),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $content
     * @return array<string, mixed>
     */
    public static function toFormData(?array $content): array
    {
        return self::normalize($content);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromFormData(array $data): array
    {
        return self::normalize(Arr::only($data, array_keys(self::defaults())));
    }

    /**
     * @param  array<string, mixed>|null  $content
     * @return array<string, mixed>
     */
    public static function toPageProps(?array $content): array
    {
        $normalized = self::normalize($content);

        return [
            'pageTitle' => $normalized['hero_title'],
            'pageSubtitle' => $normalized['hero_subtitle'],
            'searchHint' => $normalized['search_hint'],
            'guidanceCards' => $normalized['guidance_cards'],
            'faqItems' => $normalized['faq_items'],
            'templateDocs' => collect($normalized['template_docs'])
                ->map(fn(array $template): array => [
                    'id' => $template['id'],
                    'title' => $template['title'],
                    'description' => $template['description'],
                    'format' => $template['format'],
                    'badge' => $template['badge'],
                    'fileName' => $template['file_name'],
                    'downloadUrl' => self::templateDownloadUrl($template['file_path'] ?? null),
                ])
                ->values()
                ->all(),
            'helpContent' => [
                'title' => $normalized['help_title'],
                'description' => $normalized['help_description'],
                'boxTitle' => $normalized['help_box_title'],
                'boxDescription' => $normalized['help_box_description'],
                'messageTemplateTitle' => $normalized['message_template_title'],
                'messageTemplateSteps' => $normalized['message_template_steps'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $content
     * @return array<string, int>
     */
    public static function summary(?array $content): array
    {
        $normalized = self::normalize($content);

        return [
            'guidance_cards' => count($normalized['guidance_cards']),
            'faq_items' => count($normalized['faq_items']),
            'template_docs' => count($normalized['template_docs']),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeGuidanceCards(mixed $items): array
    {
        $defaults = self::defaults()['guidance_cards'];

        $normalized = collect(is_array($items) ? $items : [])
            ->values()
            ->map(function (mixed $item, int $index) use ($defaults): array {
                $default = $defaults[$index] ?? $defaults[0];
                $item = is_array($item) ? $item : [];
                $title = self::cleanString($item['title'] ?? $default['title']);

                return [
                    'id' => self::identifier($item['id'] ?? $title, 'guide-'.($index + 1)),
                    'title' => $title,
                    'description' => self::cleanString($item['description'] ?? $default['description']),
                    'badge' => self::cleanString($item['badge'] ?? $default['badge']),
                    'icon' => self::normalizeIcon($item['icon'] ?? $default['icon']),
                    'action' => self::normalizeAction($item['action'] ?? $default['action']),
                    'bullets' => self::cleanStringList($item['bullets'] ?? $default['bullets']),
                    'keywords' => self::cleanStringList($item['keywords'] ?? $default['keywords']),
                ];
            })
            ->filter(fn(array $item): bool => $item['title'] !== '')
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $defaults;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeFaqItems(mixed $items): array
    {
        $defaults = self::defaults()['faq_items'];

        $normalized = collect(is_array($items) ? $items : [])
            ->values()
            ->map(function (mixed $item, int $index) use ($defaults): array {
                $default = $defaults[$index] ?? $defaults[0];
                $item = is_array($item) ? $item : [];
                $question = self::cleanString($item['question'] ?? $default['question']);

                return [
                    'id' => self::identifier($item['id'] ?? $question, 'faq-'.($index + 1)),
                    'question' => $question,
                    'answer' => self::cleanString($item['answer'] ?? $default['answer']),
                    'tags' => self::cleanStringList($item['tags'] ?? $default['tags']),
                ];
            })
            ->filter(fn(array $item): bool => $item['question'] !== '')
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $defaults;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeTemplateDocs(mixed $items): array
    {
        $defaults = self::defaults()['template_docs'];

        $normalized = collect(is_array($items) ? $items : [])
            ->values()
            ->map(function (mixed $item, int $index) use ($defaults): array {
                $default = $defaults[$index] ?? $defaults[0];
                $item = is_array($item) ? $item : [];
                $title = self::cleanString($item['title'] ?? $default['title']);

                return [
                    'id' => self::identifier($item['id'] ?? $title, 'tpl-'.($index + 1)),
                    'title' => $title,
                    'description' => self::cleanString($item['description'] ?? $default['description']),
                    'format' => self::cleanString($item['format'] ?? $default['format']),
                    'badge' => self::nullableString($item['badge'] ?? $default['badge']),
                    'file_path' => self::normalizeUploadString($item['file_path'] ?? $default['file_path']),
                    'file_name' => self::normalizeUploadString($item['file_name'] ?? $default['file_name']),
                ];
            })
            ->filter(fn(array $item): bool => $item['title'] !== '')
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $defaults;
    }

    private static function normalizeIcon(mixed $value): string
    {
        $icon = self::cleanString($value);

        return array_key_exists($icon, self::iconOptions()) ? $icon : 'file-text';
    }

    private static function normalizeAction(mixed $value): string
    {
        $action = self::cleanString($value);

        return array_key_exists($action, self::actionOptions()) ? $action : 'none';
    }

    /**
     * @return array<int, string>
     */
    private static function cleanStringList(mixed $values): array
    {
        return collect(is_array($values) ? $values : [])
            ->map(fn(mixed $value): string => self::cleanString($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function cleanString(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = self::cleanString($value);

        return $string === '' ? null : $string;
    }

    private static function identifier(mixed $value, string $fallback): string
    {
        $identifier = Str::slug((string) $value);

        return $identifier !== '' ? $identifier : $fallback;
    }

    private static function normalizeUploadString(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = Arr::first($value);
        }

        return self::nullableString($value);
    }

    private static function templateDownloadUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }
}
