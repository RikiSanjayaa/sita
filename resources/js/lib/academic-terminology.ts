// Mirror of App\Support\AcademicTerminology. See docs/GLOSSARY.md.
// Pages normally receive these labels from the server (shared prop
// `academicTerminology`, or a per-row `terminology` prop); use this module for
// static config such as navigation and for labels that are the same everywhere.

export type AcademicTerminology = {
    degreeLevel: string;
    finalWork: string;
    finalWorkLower: string;
    proposalDocument: string;
    proposalExam: string;
    proposalExamShort: string;
    finalExam: string;
    finalExamShort: string;
    titleReviewPhase: string;
    proposalPhase: string;
    researchPhase: string;
    finalExamPhase: string;
    completedPhase: string;
};

export type ThesisPhase =
    | 'title_review'
    | 'sempro'
    | 'research'
    | 'sidang'
    | 'completed';

export const phaseLabels: Record<ThesisPhase, string> = {
    title_review: 'Review Judul',
    sempro: 'Tahap Proposal',
    research: 'Riset',
    sidang: 'Tahap Sidang',
    completed: 'Selesai',
};

export function phaseLabel(phase: string | null | undefined): string {
    return phase && phase in phaseLabels
        ? phaseLabels[phase as ThesisPhase]
        : (phase ?? '');
}

export function defenseLabel(
    terms: AcademicTerminology,
    type: string | null | undefined,
    short = false,
): string {
    if (type === 'sidang') {
        return short ? terms.finalExamShort : terms.finalExam;
    }

    return short ? terms.proposalExamShort : terms.proposalExam;
}

function labels(
    degreeLevel: string,
    finalWork: string,
    proposalExam: string,
    proposalExamShort: string,
    finalExam: string,
    finalExamShort: string,
): AcademicTerminology {
    return {
        degreeLevel,
        finalWork,
        finalWorkLower: finalWork.toLowerCase(),
        proposalDocument: 'Naskah Proposal',
        proposalExam,
        proposalExamShort,
        finalExam,
        finalExamShort,
        titleReviewPhase: phaseLabels.title_review,
        proposalPhase: phaseLabels.sempro,
        researchPhase: phaseLabels.research,
        finalExamPhase: phaseLabels.sidang,
        completedPhase: phaseLabels.completed,
    };
}

export function academicTerminology(
    degreeLevel?: string | null,
    isObeCurriculum = false,
): AcademicTerminology {
    const proposalExam = isObeCurriculum
        ? 'Ujian Sinopsis'
        : 'Seminar Proposal';
    const proposalExamShort = isObeCurriculum ? 'Sinopsis' : 'Sempro';

    switch (degreeLevel?.toLowerCase()) {
        case 'd3':
            return labels(
                'D3',
                'Tugas Akhir',
                proposalExam,
                proposalExamShort,
                'Sidang Tugas Akhir',
                'Sidang',
            );
        case 's2':
            return labels(
                'S2',
                'Tesis',
                proposalExam,
                proposalExamShort,
                'Sidang Tesis',
                'Sidang',
            );
        default:
            return labels(
                'S1',
                'Skripsi',
                proposalExam,
                proposalExamShort,
                'Sidang Skripsi',
                'Sidang',
            );
    }
}

export const neutralAcademicTerminology: AcademicTerminology = labels(
    '',
    'Tugas Akhir',
    'Sempro / Sinopsis',
    'Sempro / Sinopsis',
    'Sidang Akhir',
    'Sidang Akhir',
);
