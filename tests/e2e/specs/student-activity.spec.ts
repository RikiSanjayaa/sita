import { execFileSync } from 'node:child_process';

import { expect, test, type Page } from '@playwright/test';

import { playwrightEnvironment, repositoryRoot } from '../support/db';

function php(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(),
        encoding: 'utf8',
    }).trim();
}

async function login(page: Page, email: string, role: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: /log in|masuk/i }).click();
    await expect(page).toHaveURL(new RegExp(`/${role}/dashboard`));
}

test('student chat and lecturer-completed mentorship advance latest activity beyond old documents', async ({
    browser,
}) => {
    const token = `activity-${Date.now()}`;
    const studentEmail = `${token}-student@sita.test`;
    const lecturerEmail = `${token}-lecturer@sita.test`;
    const otherLecturerEmail = `${token}-other-lecturer@sita.test`;
    const studentName = `Aktivitas Mahasiswa ${token}`;
    const topic = `Konfirmasi selesai ${token}`;
    const fixtureCode = `
        $program = App\\Models\\ProgramStudi::query()->firstOrFail();
        $student = App\\Models\\User::factory()->asMahasiswa()->create(['name' => '${studentName}', 'email' => '${studentEmail}', 'phone_number' => '081234567890', 'phone_number_confirmed_at' => now()]);
        $lecturer = App\\Models\\User::factory()->asDosen()->create(['email' => '${lecturerEmail}', 'phone_number' => '081234567891', 'phone_number_confirmed_at' => now()]);
        App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id, 'is_active' => true]);
        App\\Models\\DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $program->id, 'is_active' => true]);
        $project = App\\Models\\ThesisProject::query()->create(['student_user_id' => $student->id, 'program_studi_id' => $program->id, 'phase' => 'research', 'state' => 'active', 'started_at' => now()->subMonth(), 'created_by' => $student->id]);
        App\\Models\\ThesisSupervisorAssignment::query()->create(['project_id' => $project->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'primary', 'status' => 'active', 'started_at' => now()->subMonth(), 'assigned_by' => $lecturer->id]);
        App\\Models\\MentorshipDocument::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'uploaded_by_user_id' => $student->id, 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);
        $thread = App\\Models\\MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);
        App\\Models\\MentorshipSchedule::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'created_by_user_id' => $lecturer->id, 'status' => 'approved', 'topic' => '${topic}', 'requested_for' => now()->addDay(), 'scheduled_for' => now()->addDay(), 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);
        echo 'ACTIVITY_THREAD='.$thread->id;
    `;
    const studentContext = await browser.newContext({
        storageState: { cookies: [], origins: [] },
    });
    const lecturerContext = await browser.newContext({
        storageState: { cookies: [], origins: [] },
    });
    let privateThreadId: string | undefined;
    let otherPrivateThreadId: string | undefined;

    try {
        const output = php(fixtureCode);
        const threadId = output.match(/ACTIVITY_THREAD=(\d+)/)?.[1];
        expect(threadId).toBeTruthy();
        const studentPage = await studentContext.newPage();
        const lecturerPage = await lecturerContext.newPage();
        await login(studentPage, studentEmail, 'mahasiswa');
        await login(lecturerPage, lecturerEmail, 'dosen');
        await lecturerPage.goto('/dosen/mahasiswa-bimbingan');
        const row = lecturerPage
            .getByRole('row')
            .filter({ hasText: studentName });
        const latest = row.locator('[data-label="Aktivitas Terbaru"]');
        await expect(latest).toHaveText(/\S/);
        await expect(latest).not.toHaveText('Belum ada aktivitas');
        const oldDocumentActivity = (await latest.innerText()).trim();
        const recentActivity = /detik|menit/;
        await expect(latest).not.toContainText(recentActivity);
        await expect(row.locator('[data-label="Bimbingan"]')).toHaveText(
            '0 bimbingan',
        );

        await studentPage.goto(`/mahasiswa/pesan?thread=${threadId}`);
        const message = `Perkembangan naskah ${token}`;
        await studentPage.getByPlaceholder('Tulis pesan...').fill(message);
        await studentPage.getByPlaceholder('Tulis pesan...').press('Enter');
        await expect(
            studentPage.getByText(message, { exact: true }).last(),
        ).toBeVisible();
        await lecturerPage.reload();
        await expect(latest).not.toHaveText(oldDocumentActivity);
        await expect(latest).toContainText(recentActivity);

        // Age only this scenario's chat so completing the session has a
        // distinguishable visible timestamp without sleeping for a minute.
        php(
            `$thread = App\\Models\\MentorshipChatThread::query()->findOrFail(${threadId}); $thread->messages()->update(['sent_at' => now()->subDays(3)]);`,
        );
        await lecturerPage.reload();
        await expect(latest).not.toHaveText(oldDocumentActivity);
        await expect(latest).not.toContainText(recentActivity);
        const agedChatActivity = (await latest.innerText()).trim();
        await lecturerPage.goto('/dosen/jadwal-bimbingan');
        await lecturerPage
            .getByRole('button', { name: 'Buka kalender' })
            .click();
        await lecturerPage
            .getByRole('button', { name: 'Daftar', exact: true })
            .click();
        await lecturerPage
            .getByRole('row')
            .filter({ hasText: topic })
            .first()
            .click();
        await Promise.all([
            lecturerPage.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    /\/dosen\/jadwal-bimbingan\/\d+\/decision$/.test(
                        response.url(),
                    ),
            ),
            lecturerPage
                .getByRole('button', { name: 'Tandai Selesai' })
                .click(),
        ]);
        await expect(
            lecturerPage.getByRole('button', { name: 'Tandai Selesai' }),
        ).toHaveCount(0);
        await lecturerPage.goto('/dosen/mahasiswa-bimbingan');
        await expect(latest).not.toHaveText(oldDocumentActivity);
        await expect(latest).not.toHaveText(agedChatActivity);
        await expect(latest).toContainText(recentActivity);
        await expect(row.locator('[data-label="Bimbingan"]')).toHaveText(
            '1 bimbingan',
        );

        // Keep the completed session newer than the group chat, but old enough
        // that a private student message must visibly advance activity again.
        const privateOutput = php(`
            $student = App\\Models\\User::query()->where('email', '${studentEmail}')->firstOrFail();
            $lecturer = App\\Models\\User::query()->where('email', '${lecturerEmail}')->firstOrFail();
            App\\Models\\MentorshipSchedule::query()->where('student_user_id', $student->id)->where('topic', '${topic}')->update(['updated_at' => now()->subDays(2)]);
            $private = app(App\\Services\\PrivateChatService::class)->findOrCreateThread($lecturer, $student);
            $otherLecturer = App\\Models\\User::factory()->asDosen()->create(['email' => '${otherLecturerEmail}']);
            App\\Models\\DosenProfile::factory()->create(['user_id' => $otherLecturer->id, 'program_studi_id' => $lecturer->dosenProfile->program_studi_id, 'is_active' => true]);
            $otherPrivate = app(App\\Services\\PrivateChatService::class)->findOrCreateThread($otherLecturer, $student);
            echo 'OTHER_PRIVATE_THREAD='.$otherPrivate->id.';';
            echo 'PRIVATE_WITHOUT_STUDENT='.($private->student_user_id === null ? 'yes' : 'no').';';
            echo 'PRIVATE_THREAD='.$private->id;
        `);
        privateThreadId = privateOutput.match(
            /(?:^|;)PRIVATE_THREAD=(\d+)/,
        )?.[1];
        otherPrivateThreadId = privateOutput.match(
            /OTHER_PRIVATE_THREAD=(\d+)/,
        )?.[1];
        expect(privateThreadId).toBeTruthy();
        expect(otherPrivateThreadId).toBeTruthy();
        expect(privateOutput).toContain('PRIVATE_WITHOUT_STUDENT=yes;');
        await lecturerPage.reload();
        await expect(latest).not.toContainText(recentActivity);
        const completedSessionActivity = (await latest.innerText()).trim();

        php(`
            $student = App\\Models\\User::query()->where('email', '${studentEmail}')->firstOrFail();
            App\\Models\\MentorshipChatMessage::factory()->create(['mentorship_chat_thread_id' => ${privateThreadId}, 'sender_user_id' => $student->id, 'message_type' => 'system', 'message' => 'System activity ${token}', 'sent_at' => now()]);
        `);
        await lecturerPage.reload();
        await expect(latest).toHaveText(completedSessionActivity);

        await studentPage.goto(
            `/mahasiswa/pesan?thread=${otherPrivateThreadId}`,
        );
        const unrelatedMessage = `Pesan dosen lain ${token}`;
        await studentPage
            .getByPlaceholder('Tulis pesan...')
            .fill(unrelatedMessage);
        await Promise.all([
            studentPage.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    response
                        .url()
                        .endsWith(
                            `/mahasiswa/pesan/${otherPrivateThreadId}/messages`,
                        ),
            ),
            studentPage.getByPlaceholder('Tulis pesan...').press('Enter'),
        ]);
        await expect(
            studentPage.getByText(unrelatedMessage, { exact: true }).last(),
        ).toBeVisible();
        await lecturerPage.reload();
        await expect(latest).toHaveText(completedSessionActivity);

        await studentPage.goto(`/mahasiswa/pesan?thread=${privateThreadId}`);
        const privateMessage = `Perkembangan pribadi ${token}`;
        await studentPage
            .getByPlaceholder('Tulis pesan...')
            .fill(privateMessage);
        await Promise.all([
            studentPage.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    response
                        .url()
                        .endsWith(
                            `/mahasiswa/pesan/${privateThreadId}/messages`,
                        ),
            ),
            studentPage.getByPlaceholder('Tulis pesan...').press('Enter'),
        ]);
        await expect(
            studentPage.getByText(privateMessage, { exact: true }).last(),
        ).toBeVisible();
        await lecturerPage.reload();
        await expect(latest).not.toHaveText(oldDocumentActivity);
        await expect(latest).not.toHaveText(completedSessionActivity);
        await expect(latest).toContainText(recentActivity);
        await expect(row.locator('[data-label="Bimbingan"]')).toHaveText(
            '1 bimbingan',
        );
    } finally {
        await studentContext.close();
        await lecturerContext.close();
        const privateThreadIds = [privateThreadId, otherPrivateThreadId].filter(
            (id): id is string => id !== undefined,
        );
        if (privateThreadIds.length > 0) {
            php(
                `App\\Models\\MentorshipChatThread::query()->whereIn('id', [${privateThreadIds.join(',')}])->delete();`,
            );
        }
        php(
            `App\\Models\\User::query()->whereIn('email', ['${studentEmail}', '${lecturerEmail}', '${otherLecturerEmail}'])->get()->each->delete();`,
        );
    }
});
