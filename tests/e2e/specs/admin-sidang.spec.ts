import { test } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

test.describe('Admin sidang workflow', () => {
    test.use({ storageState: authStatePath('admin') });

    test.fixme('admin assigns supervisors after sempro pass and keeps thesis chat continuity intact', async () => {
        // Start from a sempro-passed student without supervisors.
        // Assign Pembimbing 1 and Pembimbing 2 from the matching concentration.
        // Verify mahasiswa sees both advisors on /mahasiswa/tugas-akhir.
        // Verify /mahasiswa/pesan and /dosen/pesan-bimbingan expose the pembimbing thread for the new advisor set.
    });

    test.fixme('admin schedules sidang with both supervisors plus one extra examiner', async () => {
        // Open the sidang-ready project.
        // Trigger Aksi Workflow -> Jadwalkan Sidang.
        // Keep both active supervisors in the panel and add one external examiner.
        // Verify mahasiswa sees the sidang panel on /mahasiswa/tugas-akhir.
        // Verify the sidang thread exists alongside the historical sempro thread.
    });

    test.fixme('admin updates the sidang panel and the sidang thread removes old participants', async () => {
        // Reschedule sidang with a different external examiner.
        // Verify the removed examiner no longer sees the student on /dosen/seminar-proposal.
        // Verify the new examiner sees the sidang assignment and thread.
    });

    test.fixme('admin records sidang failure and retry, then closes the project after a passing attempt', async () => {
        // Use a scheduled sidang scenario.
        // All sidang panel members submit their decisions.
        // Admin records fail and confirms the project stays active in phase sidang.
        // Admin schedules a new sidang attempt.
        // Panel submits passing decisions and admin records pass.
        // Verify the project state becomes completed and supervisors end cleanly.
    });
});
