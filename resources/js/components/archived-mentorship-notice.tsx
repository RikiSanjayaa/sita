import { Archive } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type ArchivedMentorshipNoticeProps = {
    /** Label of the student's final work, e.g. "Skripsi". */
    finalWork: string;
};

/** Shown where deleted mentorship data (schedules, documents) would be listed. */
export function ArchivedMentorshipNotice({
    finalWork,
}: ArchivedMentorshipNoticeProps) {
    return (
        <Alert data-testid="archived-mentorship-notice">
            <Archive aria-hidden="true" className="size-4" />
            <AlertTitle>Data bimbingan sudah diarsipkan</AlertTitle>
            <AlertDescription>
                {finalWork} Anda sudah selesai. Chat, jadwal, dan dokumen draft
                bimbingan telah dihapus; naskah akhir tetap tersimpan.
            </AlertDescription>
        </Alert>
    );
}
