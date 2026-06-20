export type AcademicTerminology = {
    degreeLevel: string;
    finalWork: string;
    finalWorkLower: string;
    proposalExam: string;
    proposalExamShort: string;
    finalExam: string;
};

export function academicTerminology(
    degreeLevel?: string | null,
): AcademicTerminology {
    switch (degreeLevel?.toLowerCase()) {
        case 'd3':
            return {
                degreeLevel: 'D3',
                finalWork: 'Tugas Akhir',
                finalWorkLower: 'tugas akhir',
                proposalExam: 'Seminar Proposal Tugas Akhir',
                proposalExamShort: 'Sempro Tugas Akhir',
                finalExam: 'Sidang Tugas Akhir',
            };
        case 's2':
            return {
                degreeLevel: 'S2',
                finalWork: 'Tesis',
                finalWorkLower: 'tesis',
                proposalExam: 'Seminar Proposal Tesis',
                proposalExamShort: 'Seminar Proposal Tesis',
                finalExam: 'Ujian Tesis',
            };
        default:
            return {
                degreeLevel: 'S1',
                finalWork: 'Skripsi',
                finalWorkLower: 'skripsi',
                proposalExam: 'Seminar Proposal Skripsi',
                proposalExamShort: 'Sempro',
                finalExam: 'Sidang Skripsi',
            };
    }
}

export const neutralAcademicTerminology: AcademicTerminology = {
    degreeLevel: '',
    finalWork: 'Tugas Akhir',
    finalWorkLower: 'tugas akhir',
    proposalExam: 'Ujian Proposal',
    proposalExamShort: 'Proposal',
    finalExam: 'Ujian Akhir',
};
