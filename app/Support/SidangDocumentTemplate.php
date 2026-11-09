<?php

namespace App\Support;

use App\Models\ProgramStudi;
use Illuminate\Support\Facades\View;

final class SidangDocumentTemplate
{
    /** @return array{label: string, word: string, view: string, status: string}|null */
    public static function forProgram(?ProgramStudi $program): ?array
    {
        $programs = config('sidang-templates.programs', []);
        $custom = array_key_exists($program?->slug ?? '', $programs);
        $template = $custom ? $programs[$program->slug] : config('sidang-templates.standard');
        if (! is_array($template) || ! is_string($template['word'] ?? null) || ! is_string($template['view'] ?? null)) {
            return null;
        }

        $root = realpath(resource_path('templates/sidang'));
        $path = realpath(resource_path('templates/sidang/'.$template['word']));
        if ($root === false || $path === false || ! is_file($path) || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)
            || pathinfo($path, PATHINFO_EXTENSION) !== 'docx' || ! View::exists($template['view'])) {
            return null;
        }

        return [
            'label' => (string) ($template['label'] ?? $program?->name ?? ''),
            'word' => $template['word'], 'view' => $template['view'],
            'status' => $custom ? 'Template khusus' : 'Template standar',
        ];
    }

    public static function description(?ProgramStudi $program): string
    {
        $template = self::forProgram($program);

        return $template === null ? 'Template belum tersedia' : $template['status'].' - '.$template['label'];
    }
}
