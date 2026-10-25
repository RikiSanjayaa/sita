import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import { expect, type Page } from '@playwright/test';

import { playwrightEnvironment, repositoryRoot } from './db';

export type MahasiswaDashboardScenario =
    | 'not-started'
    | 'pending'
    | 'rejected'
    | 'cancelled'
    | 'populated'
    | 'scheduled'
    | 'completed'
    | 'archived';

export type MahasiswaDashboardExpectation = {
    progress: number;
    advisorCount: number;
    agendaCount: number;
    unreadMessages: number;
    canSubmitTitle: boolean;
    canScheduleMeeting: boolean;
    canUploadDocument: boolean;
};

export type MahasiswaDashboardFixture = {
    scenario: MahasiswaDashboardScenario;
    email: string;
    password: string;
    studentName: string;
    advisorName: string;
    secretExaminerName: string;
    projectTitle: string;
    expected: MahasiswaDashboardExpectation;
    /** Visible agenda titles and server-formatted dates, in chronological order. */
    agendaLabels: string[];
    agendaDates: string[];
    advisorProfilePath: string;
    cleanup: () => void;
};

const names = {
    studentName: 'Nadia Mahasiswa Dashboard',
    advisorName: 'Dr. Ratna Pembimbing Dashboard',
    secretExaminerName: 'Dr. Rahasia Penguji Dashboard',
    projectTitle: 'Sistem Pemantauan Penelitian Mahasiswa Berbasis Web',
};

const progressByScenario: Record<MahasiswaDashboardScenario, number> = {
    'not-started': 0,
    pending: 20,
    rejected: 0,
    cancelled: 0,
    populated: 75,
    scheduled: 90,
    completed: 100,
    archived: 100,
};

function php(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(),
        encoding: 'utf8',
        stdio: 'pipe',
    });
}

function setupCode(
    token: string,
    scenario: MahasiswaDashboardScenario,
    email: string,
): string {
    const config = Buffer.from(
        JSON.stringify({ token, scenario, email, ...names }),
    ).toString('base64');

    return `
        $cfg = json_decode(base64_decode('${config}'), true, 512, JSON_THROW_ON_ERROR);
        $metadata = Illuminate\\Support\\Facades\\DB::transaction(function () use ($cfg) {
            $scenario = $cfg['scenario'];
            $faculty = App\\Models\\Faculty::factory()->create(['name' => 'Fakultas Dashboard Mahasiswa']);
            $program = App\\Models\\ProgramStudi::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Teknik Informatika Dashboard', 'slug' => $cfg['token'], 'degree_levels' => ['s1']]);
            $identity = ['phone_number' => '081234567890', 'phone_number_confirmed_at' => now()];
            $student = App\\Models\\User::factory()->asMahasiswa()->create([...$identity, 'name' => $cfg['studentName'], 'email' => $cfg['email']]);
            App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id, 'degree_level' => 's1', 'is_obe_curriculum' => false, 'is_active' => true, 'angkatan' => 2022]);
            $advisor = App\\Models\\User::factory()->asDosen()->create([...$identity, 'name' => $cfg['advisorName'], 'email' => $cfg['token'].'-advisor@sita.test']);
            App\\Models\\DosenProfile::factory()->create(['user_id' => $advisor->id, 'program_studi_id' => $program->id, 'is_active' => true]);
            $metadata = ['advisorProfilePath' => '/profil/'.$advisor->id, 'agendaDates' => []];
            if ($scenario === 'not-started') {
                return $metadata;
            }
            $finished = in_array($scenario, ['completed', 'archived'], true);
            $cancelled = in_array($scenario, ['rejected', 'cancelled'], true);
            $supervised = in_array($scenario, ['populated', 'scheduled', 'completed', 'archived'], true);
            $completedAt = $scenario === 'archived' ? now()->subDays((int) config('sita.archive_grace_days', 30) + 5) : now()->subDays(2);
            $project = App\\Models\\ThesisProject::factory()->create([
                'student_user_id' => $student->id, 'program_studi_id' => $program->id,
                'phase' => $cancelled ? 'cancelled' : ($finished ? 'completed' : ($scenario === 'scheduled' ? 'sidang' : ($supervised ? 'research' : 'title_review'))),
                'state' => $cancelled ? 'cancelled' : ($finished ? 'completed' : 'active'),
                'started_at' => now()->subDays(120), 'created_by' => $student->id,
                'cancelled_at' => $cancelled ? now()->subDays(1) : null,
                'completed_at' => $finished ? $completedAt : null,
                'closed_by' => ($cancelled || $finished) ? $advisor->id : null,
                'mentorship_valid_from' => $supervised ? now()->subDays(60)->toDateString() : null,
                'mentorship_valid_until' => $supervised ? now()->addDays(120)->toDateString() : null,
            ]);
            $title = App\\Models\\ThesisProjectTitle::query()->create([
                'project_id' => $project->id, 'version_no' => 1, 'title_id' => $cfg['projectTitle'],
                'status' => $scenario === 'rejected' ? 'rejected' : ($scenario === 'pending' ? 'submitted' : 'approved'),
                'submitted_by_user_id' => $student->id, 'submitted_at' => now()->subDays(110),
                'decided_by_user_id' => $scenario === 'pending' ? null : $advisor->id,
                'decided_at' => $scenario === 'pending' ? null : now()->subDays(100),
                'decision_notes' => $scenario === 'rejected' ? 'Perjelas ruang lingkup penelitian sebelum mengajukan kembali.' : null,
            ]);
            if (! $supervised) {
                return $metadata;
            }
            App\\Models\\ThesisSupervisorAssignment::query()->create([
                'project_id' => $project->id, 'lecturer_user_id' => $advisor->id, 'role' => 'primary',
                'status' => $finished ? 'ended' : 'active', 'started_at' => now()->subDays(90),
                'ended_at' => $finished ? $completedAt : null, 'assigned_by' => $advisor->id,
            ]);
            $defend = function (string $type, string $status, $at) use ($project, $title, $advisor) {
                $defense = App\\Models\\ThesisDefense::query()->create([
                    'project_id' => $project->id, 'title_version_id' => $title->id, 'type' => $type,
                    'attempt_no' => 1, 'status' => $status, 'result' => $status === 'completed' ? 'pass' : 'pending',
                    'scheduled_for' => $at, 'decision_at' => $status === 'completed' ? $at : null,
                    'location' => $type === 'sidang' ? 'Ruang Sidang Dashboard' : 'Ruang Seminar Dashboard', 'mode' => 'offline',
                ]);
                App\\Models\\ThesisDefenseExaminer::query()->create([
                    'defense_id' => $defense->id, 'lecturer_user_id' => $advisor->id,
                    'role' => $type === 'sidang' ? 'primary_supervisor' : 'examiner', 'order_no' => 1,
                    'decision' => $status === 'completed' ? 'pass' : 'pending',
                    'decided_at' => $status === 'completed' ? $at : null, 'assigned_by' => $advisor->id,
                ]);
                return $defense;
            };
            $defend('sempro', 'completed', now()->subDays(95));
            $wita = fn (int $days, int $hour) => Illuminate\\Support\\Carbon::now('Asia/Makassar')->addDays($days)->setTime($hour, 0)->setTimezone(config('app.timezone'));
            $meetingAt = $finished ? $completedAt->copy()->subDays(1) : $wita(2, 10);
            App\\Models\\MentorshipSchedule::factory()->create([
                'student_user_id' => $student->id, 'lecturer_user_id' => $advisor->id,
                'topic' => 'Pembahasan hasil penelitian', 'status' => $finished ? 'completed' : 'approved',
                'requested_for' => $meetingAt, 'scheduled_for' => $meetingAt,
                'created_by_user_id' => $student->id, 'location' => 'Ruang Bimbingan Dashboard',
            ]);
            $thread = App\\Models\\MentorshipChatThread::factory()->create([
                'student_user_id' => $student->id, 'type' => 'pembimbing', 'label' => 'Bimbingan Skripsi',
            ]);
            foreach ([[$student, 'student'], [$advisor, 'advisor']] as [$member, $role]) {
                App\\Models\\MentorshipChatThreadParticipant::query()->create(['thread_id' => $thread->id, 'user_id' => $member->id, 'role' => $role]);
            }
            $messageAt = $finished ? $completedAt->copy()->subDays(1) : now()->subHours(2);
            foreach (['Silakan siapkan hasil pengujian untuk bimbingan.', 'Tambahkan pembahasan evaluasi pada bab hasil.'] as $message) {
                App\\Models\\MentorshipChatMessage::factory()->create([
                    'mentorship_chat_thread_id' => $thread->id, 'sender_user_id' => $advisor->id,
                    'message' => $message, 'sent_at' => $messageAt, 'created_at' => $messageAt, 'updated_at' => $messageAt,
                ]);
            }
            if ($finished) {
                App\\Models\\MentorshipChatRead::query()->create(['mentorship_chat_thread_id' => $thread->id, 'user_id' => $student->id, 'last_read_at' => $completedAt]);
                $defend('sidang', 'completed', $completedAt);
                if ($scenario === 'archived') {
                    $plan = app(App\\Services\\ThesisArchiveService::class)->archive($project);
                    if ($plan === null) {
                        throw new RuntimeException('Dashboard fixture could not archive its completed project.');
                    }
                }
                return $metadata;
            }
            $metadata['agendaDates'][] = $meetingAt->copy()->locale('id')->translatedFormat('d M Y, H:i');
            if ($scenario === 'scheduled') {
                $examiner = App\\Models\\User::factory()->asDosen()->create([...$identity, 'name' => $cfg['secretExaminerName'], 'email' => $cfg['token'].'-examiner@sita.test']);
                App\\Models\\DosenProfile::factory()->create(['user_id' => $examiner->id, 'program_studi_id' => $program->id, 'is_active' => true]);
                $defenseAt = $wita(4, 9);
                $defense = $defend('sidang', 'scheduled', $defenseAt);
                App\\Models\\ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $examiner->id, 'role' => 'examiner', 'order_no' => 2, 'decision' => 'pending', 'assigned_by' => $advisor->id]);
                $hiddenAt = $wita(1, 11);
                App\\Models\\MentorshipSchedule::factory()->create([
                    'student_user_id' => $student->id, 'lecturer_user_id' => $examiner->id,
                    'created_by_user_id' => $examiner->id, 'topic' => 'Konsultasi rahasia penguji',
                    'status' => 'approved', 'requested_for' => $hiddenAt, 'scheduled_for' => $hiddenAt,
                    'location' => 'Ruang Penguji Dashboard',
                ]);
                $metadata['agendaDates'][] = $defenseAt->copy()->locale('id')->translatedFormat('d M Y, H:i');
            }
            return $metadata;
        });
        echo 'MAHASISWA_DASHBOARD_METADATA:'.json_encode($metadata, JSON_THROW_ON_ERROR);
    `;
}

function cleanupCode(token: string): string {
    return `
        Illuminate\\Support\\Facades\\DB::transaction(function () {
            $users = App\\Models\\User::query()->where('email', 'like', '${token}%')->get();
            $ids = $users->modelKeys();
            Illuminate\\Support\\Facades\\DB::table('notifications')->where('notifiable_type', App\\Models\\User::class)->whereIn('notifiable_id', $ids)->delete();
            Illuminate\\Support\\Facades\\DB::table('system_audit_logs')->whereIn('user_id', $ids)->delete();
            $users->each->delete();
            $program = App\\Models\\ProgramStudi::query()->where('slug', '${token}')->first();
            $facultyId = $program?->faculty_id;
            $program?->delete();
            if ($facultyId !== null) {
                App\\Models\\Faculty::query()->whereKey($facultyId)->delete();
            }
        });
    `;
}

/**
 * Owns every identity and academic record it creates; never changes seed data.
 * Lifecycle rows match real rejection/completion rules. The archived scenario
 * executes the archive service, including its snapshot and owned-row deletion.
 * Stable visible names keep these fixtures reusable for screenshot capture.
 */
export function createMahasiswaDashboardFixture(
    scenario: MahasiswaDashboardScenario,
): MahasiswaDashboardFixture {
    const token = `mahasiswa-dashboard-${randomUUID()}`;
    const email = `${token}-student@sita.test`;
    let metadata: { advisorProfilePath: string; agendaDates: string[] };

    try {
        const output = php(setupCode(token, scenario, email));
        const match = output.match(
            /MAHASISWA_DASHBOARD_METADATA:(\{[^\r\n]*\})/,
        );
        if (!match) {
            throw new Error(
                `Dashboard fixture did not return metadata: ${output}`,
            );
        }
        metadata = JSON.parse(match[1]);
    } catch (error) {
        php(cleanupCode(token));
        throw error;
    }

    const active = scenario === 'populated' || scenario === 'scheduled';
    const hasProject = active || scenario === 'pending';
    const progress = progressByScenario[scenario];
    const agendaLabels = active ? ['Pembahasan hasil penelitian'] : [];
    if (scenario === 'scheduled') {
        agendaLabels.push('Sidang Skripsi terjadwal');
    }

    return {
        scenario,
        email,
        password: 'password',
        ...names,
        ...metadata,
        agendaLabels,
        expected: {
            progress,
            advisorCount: active ? 1 : 0,
            agendaCount: agendaLabels.length,
            unreadMessages: active ? 2 : 0,
            canSubmitTitle: !hasProject,
            canScheduleMeeting: active,
            canUploadDocument: active,
        },
        cleanup: () => {
            php(cleanupCode(token));
        },
    };
}

export async function loginAsFixtureMahasiswa(
    page: Page,
    fixture: MahasiswaDashboardFixture,
): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(fixture.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: /log in|masuk/i }).click();
    await expect(page).toHaveURL(/\/mahasiswa\/dashboard$/);
}

/** Sign in with a fresh fixture and clean up even if login or capture fails. */
export async function withMahasiswaDashboard(
    page: Page,
    scenario: MahasiswaDashboardScenario,
    run: (fixture: MahasiswaDashboardFixture) => Promise<void>,
): Promise<void> {
    const fixture = createMahasiswaDashboardFixture(scenario);

    try {
        await loginAsFixtureMahasiswa(page, fixture);
        await run(fixture);
    } finally {
        fixture.cleanup();
    }
}
