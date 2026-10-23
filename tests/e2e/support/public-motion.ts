import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import { playwrightEnvironment, repositoryRoot } from './db';

export type PublicMotionTopics = {
    prefix: string;
    /** Public topic titles (Indonesian), in creation order. */
    titles: string[];
    programName: string;
    programSlug: string;
    /** Deletes only the rows this call created. */
    cleanup: () => void;
};

const model = (name: string): string => `App\\Models\\${name}`;
const topicCount = 12;

function runTinker(script: string, marker: string, failure: string): string {
    const output = execFileSync(
        'php',
        ['artisan', 'tinker', `--execute=${script}`],
        {
            cwd: repositoryRoot,
            env: playwrightEnvironment(),
            encoding: 'utf8',
        },
    );

    // Tinker can report a PHP exception while still exiting successfully.
    if (!output.split(/\r?\n/).includes(marker)) {
        throw new Error(`${failure}: ${output}`);
    }

    return output;
}

/**
 * Creates 12 published topics owned by one throwaway program: six whose
 * seminar was held this year and six held last year, so the year filter has two
 * choices and a search for the prefix paginates at 10 per page. Every row is
 * prefixed, so cleanup never touches shared seed data.
 */
export function createPublicMotionTopics(): PublicMotionTopics {
    const prefix = `motion-${randomUUID().slice(0, 8)}`;
    const programName = `${prefix} Program`;
    const created = `${prefix}:topics-created`;
    const removed = `${prefix}:topics-removed`;

    const output = runTinker(
        `
        $faculty = ${model('Faculty')}::factory()->create(['slug' => '${prefix}', 'code' => '${prefix}']);
        $ps = ${model('ProgramStudi')}::factory()->create(['faculty_id' => $faculty->id, 'slug' => '${prefix}', 'name' => '${programName}', 'concentrations' => ['Sistem'], 'degree_levels' => ['s1']]);
        $titles = [];
        for ($i = 0; $i < ${topicCount}; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $student = ${model('User')}::factory()->asMahasiswa()->create(['name' => '${prefix} Mahasiswa '.$n, 'email' => '${prefix}-student'.$i.'@sita.test']);
            ${model('MahasiswaProfile')}::factory()->create(['user_id' => $student->id, 'program_studi_id' => $ps->id, 'concentration' => 'Sistem', 'is_active' => true]);
            $project = ${model('ThesisProject')}::factory()->create(['student_user_id' => $student->id, 'program_studi_id' => $ps->id, 'phase' => 'research', 'state' => 'active', 'started_at' => now()->subYears(2)]);
            $title = ${model('ThesisProjectTitle')}::query()->create(['project_id' => $project->id, 'version_no' => 1, 'title_id' => '${prefix} Topik '.$n.($i % 3 === 0 ? ' dengan judul yang jauh lebih panjang agar baris detail memiliki tinggi berbeda' : ''), 'title_en' => '${prefix} Topic '.$n, 'proposal_summary' => 'Ringkasan ${prefix} nomor '.$n.str_repeat(' dengan uraian metode penelitian nomor '.$n.'.', ($i % 4) + 1), 'status' => 'approved', 'submitted_by_user_id' => $student->id, 'submitted_at' => now()->subYears(2)]);
            $heldAt = $i < 6 ? now()->startOfYear()->addMinutes($i) : now()->subYear()->startOfYear()->addDays(10)->addMinutes($i);
            ${model('ThesisDefense')}::query()->create(['project_id' => $project->id, 'title_version_id' => $title->id, 'type' => 'sempro', 'attempt_no' => 1, 'status' => 'completed', 'result' => 'pass', 'scheduled_for' => $heldAt, 'location' => '${prefix} Ruang', 'mode' => 'offline', 'decision_at' => $heldAt->copy()->addHours(2)]);
            $titles[] = $title->title_id;
        }
        echo '${created}'.PHP_EOL;
        echo json_encode(['titles' => $titles, 'programName' => $ps->name, 'programSlug' => $ps->slug]).PHP_EOL;
    `,
        created,
        'Could not create public motion topics',
    );

    const json = output
        .split(/\r?\n/)
        .find((line) => line.startsWith('{"titles"'));
    if (!json) {
        throw new Error(
            `Public motion topics returned no identifiers: ${output}`,
        );
    }
    const data = JSON.parse(json) as {
        titles: string[];
        programName: string;
        programSlug: string;
    };

    return {
        prefix,
        titles: data.titles,
        programName: data.programName,
        programSlug: data.programSlug,
        cleanup: () => {
            runTinker(
                `
                $ps = ${model('ProgramStudi')}::query()->where('slug', '${prefix}')->first();
                if ($ps) { ${model('ThesisProject')}::query()->where('program_studi_id', $ps->id)->delete(); }
                ${model('User')}::query()->where('email', 'like', '${prefix}-%@sita.test')->delete();
                if ($ps) { $ps->delete(); }
                ${model('Faculty')}::query()->where('slug', '${prefix}')->delete();
                echo '${removed}'.PHP_EOL;
            `,
                removed,
                'Could not remove public motion topics',
            );
        },
    };
}
