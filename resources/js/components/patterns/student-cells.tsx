import { Link } from '@inertiajs/react';

import { StatusBadge } from '@/components/patterns/status-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

/**
 * Cell contents shared by the student tables (kaprodi Mahasiswa Prodi and
 * Progres Mahasiswa), so both read the same way.
 */

/** Avatar, name and NIM; the whole block links to the student's profile. */
export function StudentIdentity({
    name,
    nim,
    avatar,
    profileUrl,
}: {
    name: string;
    nim: string | null;
    avatar: string | null;
    profileUrl: string;
}) {
    const getInitials = useInitials();

    return (
        <Link href={profileUrl} className="group flex items-center gap-3">
            <Avatar className="size-8 shrink-0 border">
                <AvatarImage src={avatar ?? undefined} alt="" />
                <AvatarFallback className="bg-primary-muted text-xs text-primary-muted-foreground">
                    {getInitials(name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <p className="font-medium group-hover:text-primary">{name}</p>
                <p className="text-muted-foreground tabular-nums">
                    {nim ?? '-'}
                </p>
            </div>
        </Link>
    );
}

/** Degree and angkatan, then program studi (when given) or concentration, and the OBE badge. */
export function StudentCohort({
    degreeLevel,
    angkatan,
    concentration,
    isObeCurriculum,
    prodi,
}: {
    degreeLevel: string;
    angkatan: string | number | null;
    concentration: string | null;
    isObeCurriculum: boolean;
    prodi?: string | null;
}) {
    // From md the parts stack in the cell; on phones they run as one meta line
    // ("S1 · 2022 · Jaringan"), with the empty parts dropped.
    return (
        <div className="max-md:flex max-md:flex-wrap max-md:items-center max-md:gap-x-1.5">
            <p className="font-medium max-md:font-normal">
                {degreeLevel} · {angkatan ?? '-'}
            </p>
            {prodi !== undefined ? (
                <p
                    className={cn(
                        'text-xs text-muted-foreground',
                        prodi === null && 'max-md:hidden',
                    )}
                >
                    <span className="hidden max-md:mr-1.5 max-md:inline">
                        ·
                    </span>
                    {prodi ?? '-'}
                </p>
            ) : null}
            <p
                className={cn(
                    'text-xs text-muted-foreground',
                    concentration === null && 'max-md:hidden',
                )}
            >
                <span className="hidden max-md:mr-1.5 max-md:inline">·</span>
                {concentration ?? '-'}
            </p>
            {isObeCurriculum && (
                <StatusBadge
                    domain="curriculum"
                    status="obe"
                    className="mt-1 max-md:mt-0"
                >
                    OBE
                </StatusBadge>
            )}
        </div>
    );
}

/** Title (two lines at most) with the supervisors' names underneath. */
export function StudentTitleAdvisors({
    title,
    advisors,
}: {
    title: string | null;
    advisors: string[];
}) {
    return (
        <>
            {title ? (
                <p
                    className={cn(
                        'line-clamp-2 text-pretty',
                        title === '-' && 'max-md:hidden',
                    )}
                    title={title}
                >
                    {title}
                </p>
            ) : (
                <span className="text-muted-foreground max-md:hidden">-</span>
            )}
            {advisors.length > 0 ? (
                <p
                    className="mt-1 text-xs text-pretty text-muted-foreground max-md:mt-0.5 max-md:line-clamp-1"
                    title={advisors.join(', ')}
                >
                    {advisors.join(', ')}
                </p>
            ) : null}
        </>
    );
}
