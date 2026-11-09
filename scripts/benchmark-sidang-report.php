<?php

use App\Models\DosenProfile;
use App\Models\Faculty;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use App\Services\SidangReportWriter;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @return Generator<int, ThesisDefense> */
function syntheticSidangDefenses(int $count): Generator
{
    $programs = collect(['Ilmu Komputer', 'Teknologi Informasi'])->map(function (string $name, int $index): ProgramStudi {
        $program = new ProgramStudi([
            'name' => $name,
            'sidang_settings' => ['dean_name' => 'Dekan Demo', 'staff_name' => 'Staf Demo'],
        ]);
        $program->id = $index + 1;

        return $program->setRelation('faculty', new Faculty(['name' => 'Fakultas Demo']))
            ->setRelation('kaprodiAssignments', collect());
    });
    $panel = (new ThesisDefenseExaminer)->newCollection();
    foreach ([88.5, 70, 80] as $index => $value) {
        $id = $index + 1;
        $lecturer = (new User(['name' => 'Dosen Demo '.$id]))
            ->setRelation('dosenProfile', new DosenProfile(['sidang_nidn' => str_pad((string) $id, 10, '0', STR_PAD_LEFT)]));
        $member = new ThesisDefenseExaminer([
            'role' => $index === 2 ? 'primary_supervisor' : 'examiner',
            'order_no' => $id,
            'score' => $value,
        ]);
        $member->id = $id;
        $panel->push($member->setRelation('lecturer', $lecturer));
    }

    for ($number = 1; $number <= $count; $number++) {
        $program = $programs[($number - 1) % 2];
        $student = (new User(['name' => 'Mahasiswa Demo '.$number]))
            ->setRelation('mahasiswaProfile', new MahasiswaProfile([
                'nim' => str_pad((string) $number, 10, '0', STR_PAD_LEFT),
                'degree_level' => 'S1',
            ]));
        $project = (new ThesisProject(['program_studi_id' => $program->id]))
            ->setRelation('programStudi', $program)->setRelation('student', $student);
        $defense = new ThesisDefense(['type' => 'sidang', 'status' => 'completed', 'result' => 'pass']);
        $defense->sidang_sk = sprintf('%03d/ADUM/DEMO', intdiv($number - 1, 100) + 1);

        yield $defense->setRelation('project', $project)
            ->setRelation('titleVersion', new ThesisProjectTitle(['title_id' => 'Judul Demo '.$number]))
            ->setRelation('examiners', $panel);
    }
}

$count = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);
if ($count === false) {
    fwrite(STDERR, "Usage: php scripts/benchmark-sidang-report.php <defenses (2+)> [output.xlsx]\n");
    exit(1);
}

$writer = $app->make(SidangReportWriter::class);
$baseline = memory_get_usage();
memory_reset_peak_usage();
$start = hrtime(true);
$path = $writer->prepare(syntheticSidangDefenses($count), 'SEMESTER DEMO');
$elapsed = (hrtime(true) - $start) / 1e9;
$peak = memory_get_peak_usage() - $baseline;
try {
    if (isset($argv[2]) && ! copy($path, $argv[2])) {
        throw new RuntimeException('Failed to copy the benchmark workbook.');
    }

    echo json_encode([
        'defenses' => $count,
        'programs' => 2,
        'additional_peak_php_bytes' => $peak,
        'elapsed_seconds' => round($elapsed, 3),
        'workbook_bytes' => filesize($path),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    unlink($path);
}
