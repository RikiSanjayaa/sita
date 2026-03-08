import { FileText, GraduationCap, Users } from 'lucide-react';

import { PersonCardLink } from '@/components/profile/person-card-link';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { type UserProfileDetail } from '@/types';

export function ProfileDetailsSections({
    profile,
}: {
    profile: UserProfileDetail;
}) {
    return (
        <div className="grid gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Informasi Utama</CardTitle>
                    <CardDescription>
                        Ringkasan identitas dan detail akademik yang relevan.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {profile.meta.map((item) => (
                            <div
                                key={item.label}
                                className="rounded-xl border p-4"
                            >
                                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                    {item.label}
                                </p>
                                <p className="mt-2 text-sm font-medium text-foreground">
                                    {item.value}
                                </p>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {profile.stats.length > 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Ringkasan</CardTitle>
                        <CardDescription>
                            Gambaran singkat aktivitas dan peran saat ini.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                            {profile.stats.map((item) => (
                                <div
                                    key={item.label}
                                    className="rounded-xl border bg-muted/20 p-4"
                                >
                                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                        {item.label}
                                    </p>
                                    <p className="mt-2 text-lg font-semibold text-foreground">
                                        {item.value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            ) : null}

            {profile.thesis ? (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="size-4" />
                            Tugas Akhir
                        </CardTitle>
                        <CardDescription>
                            Status terkini dan dosen yang sedang terlibat.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="rounded-xl border p-4">
                            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                Judul Saat Ini
                            </p>
                            <p className="mt-2 text-sm font-medium text-foreground">
                                {profile.thesis.title ??
                                    'Belum ada judul aktif'}
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Status: {profile.thesis.statusLabel}
                            </p>
                        </div>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <div className="space-y-3">
                                <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <GraduationCap className="size-4" />
                                    Dosen Pembimbing
                                </div>
                                {profile.thesis.advisors.length > 0 ? (
                                    <div className="grid gap-3">
                                        {profile.thesis.advisors.map(
                                            (person, index) => (
                                                <PersonCardLink
                                                    key={person.id}
                                                    person={person}
                                                    label={`Pembimbing ${index + 1}`}
                                                />
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada pembimbing aktif.
                                    </p>
                                )}
                            </div>

                            <div className="space-y-3">
                                <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <Users className="size-4" />
                                    Dosen Penguji
                                </div>
                                {profile.thesis.examiners.length > 0 ? (
                                    <div className="grid gap-3">
                                        {profile.thesis.examiners.map(
                                            (person, index) => (
                                                <PersonCardLink
                                                    key={`${person.id}-${index}`}
                                                    person={person}
                                                    label={`Penguji ${index + 1}`}
                                                />
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada penguji aktif.
                                    </p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            ) : null}

            {profile.relatedUsers.map((group) => (
                <Card key={group.title}>
                    <CardHeader>
                        <CardTitle>{group.title}</CardTitle>
                        <CardDescription>
                            Profil pengguna terkait yang bisa dibuka langsung.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {group.users.length > 0 ? (
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {group.users.map((person) => (
                                    <PersonCardLink
                                        key={person.id}
                                        person={person}
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {group.emptyMessage}
                            </p>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
