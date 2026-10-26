import { FileText, GraduationCap, type LucideIcon, Users } from 'lucide-react';
import { type ReactNode } from 'react';

import { type MentorshipSessionNotesContext } from '@/components/mentorship-session-notes';
import { MonitoringSessionNotes } from '@/components/profile/monitoring-session-notes';
import { PersonCardLink } from '@/components/profile/person-card-link';
import { neutralAcademicTerminology } from '@/lib/academic-terminology';
import { type UserProfileDetail, type UserProfileSummary } from '@/types';

/** One bordered surface; its parts are separated by dividers, never by inner boxes. */
function ProfileSection({
    title,
    description,
    icon: Icon,
    children,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    children: ReactNode;
}) {
    return (
        <section className="divide-y overflow-hidden rounded-xl border bg-card">
            <div className="space-y-1 px-4 py-5 md:px-6">
                <h2 className="flex items-center gap-2 text-lg font-semibold text-balance">
                    {Icon ? (
                        <Icon aria-hidden="true" className="size-5 shrink-0" />
                    ) : null}
                    {title}
                </h2>
                {description ? (
                    <p className="text-sm text-pretty text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {children}
        </section>
    );
}

function FactGrid({
    items,
    tabular = false,
    className,
}: {
    items: ReadonlyArray<{ label: string; value: string | number }>;
    tabular?: boolean;
    className: string;
}) {
    return (
        <dl className={`grid gap-x-8 gap-y-5 px-4 py-5 md:px-6 ${className}`}>
            {items.map((item) => (
                <div key={item.label}>
                    <dt className="text-xs font-medium text-muted-foreground">
                        {item.label}
                    </dt>
                    <dd
                        className={`mt-1 text-sm font-semibold text-foreground ${tabular ? 'tabular-nums' : ''}`}
                    >
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/** Full-bleed divided list of people inside a section. */
function PeopleList({
    people,
    label,
    showCount = false,
    sessionsByLecturer,
}: {
    people: UserProfileSummary[];
    label?: (person: UserProfileSummary, index: number) => string;
    showCount?: boolean;
    sessionsByLecturer?: Map<number, MentorshipSessionNotesContext[]>;
}) {
    return (
        <ul className="divide-y border-y">
            {people.map((person, index) => {
                const personLink = (
                    <PersonCardLink
                        variant="row"
                        person={person}
                        label={label?.(person, index)}
                        mentorshipCount={
                            showCount ? person.mentorshipCount : undefined
                        }
                        progress={person.progress}
                        className="px-4 md:px-6"
                    />
                );
                const sessions = sessionsByLecturer?.get(person.id);

                return (
                    <li key={`${person.id}-${index}`}>
                        {sessions !== undefined ? (
                            <MonitoringSessionNotes
                                lecturerId={person.id}
                                lecturerName={person.name}
                                sessions={sessions}
                            >
                                {personLink}
                            </MonitoringSessionNotes>
                        ) : (
                            personLink
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

function PeopleBlock({
    title,
    icon: Icon,
    children,
}: {
    title: string;
    icon?: LucideIcon;
    children: ReactNode;
}) {
    return (
        <div className="space-y-3 pt-5">
            <h3 className="flex items-center gap-2 px-4 text-sm font-semibold text-foreground md:px-6">
                {Icon ? (
                    <Icon aria-hidden="true" className="size-4 shrink-0" />
                ) : null}
                {title}
            </h3>
            {children}
        </div>
    );
}

function EmptyLine({ children }: { children: ReactNode }) {
    return (
        <p className="px-4 pb-5 text-sm text-muted-foreground md:px-6">
            {children}
        </p>
    );
}

export function ProfileDetailsSections({
    profile,
    monitoringSessionNotes,
    monitoringLecturers = [],
}: {
    profile: UserProfileDetail;
    monitoringSessionNotes?: MentorshipSessionNotesContext[];
    monitoringLecturers?: UserProfileSummary[];
}) {
    const finalWork =
        profile.academicTerminology?.finalWork ??
        neutralAcademicTerminology.finalWork;
    const examinerGroups = profile.thesis?.examinerGroups ?? [];
    const currentLecturerIds = new Set([
        ...(profile.thesis?.advisors ?? []).map((person) => person.id),
        ...(profile.thesis?.examiners ?? []).map((person) => person.id),
        ...examinerGroups.flatMap((group) =>
            group.users.map((person) => person.id),
        ),
    ]);
    const advisors = [
        ...(profile.thesis?.advisors ?? []),
        ...monitoringLecturers.filter(
            (person) => !currentLecturerIds.has(person.id),
        ),
    ];
    const sessionLocations = new Map<number, string>();
    const locateSessions = (people: UserProfileSummary[], location: string) => {
        for (const person of people) {
            if (!sessionLocations.has(person.id))
                sessionLocations.set(person.id, location);
        }
    };
    locateSessions(advisors, 'advisors');
    if (examinerGroups.length > 0) {
        examinerGroups.forEach((group, index) =>
            locateSessions(group.users, `examiner-${index}`),
        );
    } else {
        locateSessions(profile.thesis?.examiners ?? [], 'examiners');
    }
    const sessionsByLecturer = new Map<
        number,
        MentorshipSessionNotesContext[]
    >();
    for (const session of monitoringSessionNotes ?? []) {
        const sessions = sessionsByLecturer.get(session.lecturerUserId) ?? [];
        sessions.push(session);
        sessionsByLecturer.set(session.lecturerUserId, sessions);
    }
    const sessionsForList = (location: string) => {
        if (monitoringSessionNotes === undefined) return undefined;

        return new Map(
            [...sessionLocations]
                .filter(([, rowLocation]) => rowLocation === location)
                .map(([id]) => [id, sessionsByLecturer.get(id) ?? []]),
        );
    };

    return (
        <div className="space-y-6">
            <ProfileSection
                title="Detail akademik dan peran"
                description="Ringkasan identitas dan status bimbingan."
            >
                <FactGrid
                    items={profile.meta}
                    className="sm:grid-cols-2 xl:grid-cols-3"
                />
                {profile.stats.length > 0 ? (
                    <FactGrid
                        items={profile.stats}
                        tabular
                        className="sm:grid-cols-2 lg:grid-cols-3"
                    />
                ) : null}
            </ProfileSection>

            {profile.thesis ? (
                <ProfileSection
                    title={finalWork}
                    description="Status terkini dan dosen yang sedang terlibat."
                    icon={FileText}
                >
                    <div className="space-y-2 px-4 py-5 md:px-6">
                        <div>
                            <p className="text-xs font-medium text-muted-foreground">
                                Judul saat ini
                            </p>
                            <p className="mt-1 text-sm font-semibold text-foreground">
                                {profile.thesis.title ??
                                    (profile.thesis.titleVisible
                                        ? 'Belum ada judul aktif'
                                        : 'Judul belum tampil untuk publik')}
                            </p>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Status:{' '}
                            <span className="font-medium text-foreground">
                                {profile.thesis.statusLabel}
                            </span>
                        </p>
                    </div>

                    <PeopleBlock title="Dosen pembimbing" icon={GraduationCap}>
                        {advisors.length > 0 ? (
                            <PeopleList
                                people={advisors}
                                label={(_, index) =>
                                    index <
                                    (profile.thesis?.advisors.length ?? 0)
                                        ? `Pembimbing ${index + 1}`
                                        : 'Riwayat sesi bimbingan'
                                }
                                showCount
                                sessionsByLecturer={sessionsForList('advisors')}
                            />
                        ) : (
                            <EmptyLine>Belum ada pembimbing aktif.</EmptyLine>
                        )}
                    </PeopleBlock>

                    <PeopleBlock title="Dosen penguji" icon={Users}>
                        {examinerGroups.length > 0 ? (
                            <div className="space-y-4">
                                {examinerGroups.map((group, groupIndex) => (
                                    <div key={group.title}>
                                        <p className="px-4 pb-2 text-xs font-medium text-muted-foreground md:px-6">
                                            {group.title}
                                        </p>
                                        {group.users.length > 0 ? (
                                            <>
                                                <PeopleList
                                                    people={group.users}
                                                    label={(_, index) =>
                                                        `${group.title} ${index + 1}`
                                                    }
                                                    sessionsByLecturer={sessionsForList(
                                                        `examiner-${groupIndex}`,
                                                    )}
                                                />
                                                {group.note ? (
                                                    <EmptyLine>
                                                        {group.note}
                                                    </EmptyLine>
                                                ) : null}
                                            </>
                                        ) : (
                                            <EmptyLine>
                                                {group.emptyMessage}
                                            </EmptyLine>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : profile.thesis.examiners.length > 0 ? (
                            <PeopleList
                                people={profile.thesis.examiners}
                                label={(_, index) => `Penguji ${index + 1}`}
                                sessionsByLecturer={sessionsForList(
                                    'examiners',
                                )}
                            />
                        ) : (
                            <EmptyLine>Belum ada penguji aktif.</EmptyLine>
                        )}
                    </PeopleBlock>
                </ProfileSection>
            ) : null}

            {profile.relatedUsers.map((group) => (
                <ProfileSection key={group.title} title={group.title}>
                    {group.users.length > 0 ? (
                        <PeopleList people={group.users} showCount />
                    ) : (
                        <EmptyLine>{group.emptyMessage}</EmptyLine>
                    )}
                </ProfileSection>
            ))}
        </div>
    );
}
