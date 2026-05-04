export type AcademicGrade = 'A' | 'B+' | 'B' | 'C+' | 'C' | 'D' | 'E';

function normalizeAcademicScore(
    score: number | string | null | undefined,
): number | null {
    const normalizedScore =
        typeof score === 'string' ? Number.parseFloat(score) : score;

    if (typeof normalizedScore !== 'number' || Number.isNaN(normalizedScore)) {
        return null;
    }

    return normalizedScore;
}

export function calculateAverageAcademicScore(
    scores: Array<number | string | null | undefined>,
): number | null {
    const normalizedScores = scores
        .map((score) => normalizeAcademicScore(score))
        .filter((score): score is number => score !== null);

    if (normalizedScores.length === 0) {
        return null;
    }

    return (
        normalizedScores.reduce((total, score) => total + score, 0) /
        normalizedScores.length
    );
}

export function resolveAcademicGrade(
    score: number | string | null | undefined,
): AcademicGrade | null {
    const normalizedScore = normalizeAcademicScore(score);

    if (normalizedScore === null) {
        return null;
    }

    if (normalizedScore <= 20) {
        return 'E';
    }

    if (normalizedScore <= 40) {
        return 'D';
    }

    if (normalizedScore <= 50) {
        return 'C';
    }

    if (normalizedScore <= 60) {
        return 'C+';
    }

    if (normalizedScore <= 70) {
        return 'B';
    }

    if (normalizedScore <= 80) {
        return 'B+';
    }

    return 'A';
}

export function academicGradeClassName(grade: AcademicGrade | null): string {
    switch (grade) {
        case 'A':
            return 'bg-emerald-600/10 text-emerald-700 hover:bg-emerald-600/20 dark:text-emerald-400';
        case 'B+':
            return 'bg-green-600/10 text-green-700 hover:bg-green-600/20 dark:text-green-400';
        case 'B':
            return 'bg-lime-600/10 text-lime-700 hover:bg-lime-600/20 dark:text-lime-400';
        case 'C+':
            return 'bg-amber-600/10 text-amber-700 hover:bg-amber-600/20 dark:text-amber-400';
        case 'C':
            return 'bg-orange-600/10 text-orange-700 hover:bg-orange-600/20 dark:text-orange-400';
        case 'D':
            return 'bg-red-500/10 text-red-700 hover:bg-red-500/20 dark:text-red-400';
        case 'E':
            return 'bg-red-700/10 text-red-800 hover:bg-red-700/20 dark:text-red-300';
        default:
            return 'bg-muted text-muted-foreground hover:bg-muted';
    }
}
