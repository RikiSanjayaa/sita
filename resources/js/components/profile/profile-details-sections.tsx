import { FileText, GraduationCap, type LucideIcon, Users } from 'lucide-react';
import { type ReactNode } from 'react';

import { CopyFieldButton } from '@/components/profile/copy-field-button';
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
                        className={`mt-1 flex min-w-0 items-center gap-2 text-sm font-semibold text-foreground ${tabular ? 'tabular-nums' : ''}`}
                    >
                        <span className="min-w-0 break-words">
                            {item.value}
                        </span>
                        {['NIM', 'Email', 'Nomor HP'].includes(item.label) &&
                        String(item.value).trim() !== '' &&
                        item.value !== '-' ? (
                            <CopyFieldButton
                                label={item.label}
                                value={String(item.value)}
                            />
                        ) : null}
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
}: {
    people: UserProfileSummary[];
    label?: (person: UserProfileSummary, index: number) => string;
    showCount?: boolean;
}) {
    return (
        <ul className="divide-y border-y">
            {people.map((person, index) => (
                <li key={`${person.id}-${index}`}>
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
                </li>
            ))}
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
}: {
    profile: UserProfileDetail;
}) {
    const finalWork =
        profile.academicTerminology?.finalWork ??
        neutralAcademicTerminology.finalWork;
    const examinerGroups = profile.thesis?.examinerGroups ?? [];

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
                        {profile.thesis.advisors.length > 0 ? (
                            <PeopleList
                                people={profile.thesis.advisors}
                                label={(_, index) => `Pembimbing ${index + 1}`}
                                showCount
                            />
                        ) : (
                            <EmptyLine>Belum ada pembimbing aktif.</EmptyLine>
                        )}
                    </PeopleBlock>

                    <PeopleBlock title="Dosen penguji" icon={Users}>
                        {examinerGroups.length > 0 ? (
                            <div className="space-y-4">
                                {examinerGroups.map((group) => (
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
