import { Head, Link, usePage } from '@inertiajs/react';

import { ProfileDetailsSections } from '@/components/profile/profile-details-sections';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileDetail,
} from '@/types';

type ProfileShowProps = {
    profile: UserProfileDetail;
    canEditProfile: boolean;
};

const WhatsAppIcon = ({ className }: { className?: string }) => (
    <svg
        viewBox="0 0 24 24"
        fill="currentColor"
        className={className}
        aria-hidden="true"
    >
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
);

export default function ProfileShowPage() {
    const { auth, canEditProfile, profile } = usePage<
        SharedData & ProfileShowProps
    >().props;
    const getInitials = useInitials();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: profile.name, href: profile.profileUrl },
    ];

    return (
        <AppLayout
            role={auth.activeRole === 'dosen' ? 'dosen' : auth.activeRole}
            breadcrumbs={breadcrumbs}
            title={profile.name}
            subtitle={profile.subtitle ?? profile.description}
        >
            <Head title={`Profil ${profile.name}`} />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-8 md:px-6">
                <div>
                    <div className="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                        <div className="flex items-center gap-5">
                            <Avatar className="size-24 border">
                                <AvatarImage
                                    src={profile.avatar ?? undefined}
                                    alt={profile.name}
                                />
                                <AvatarFallback className="bg-primary/10 text-xl font-semibold text-primary">
                                    {getInitials(profile.name)}
                                </AvatarFallback>
                            </Avatar>

                            <div className="space-y-2">
                                <div>
                                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                        {profile.name}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        {profile.description} • {profile.email}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Badge>{profile.roleLabel}</Badge>
                                    {profile.programStudi ? (
                                        <Badge variant="outline">
                                            {profile.programStudi}
                                        </Badge>
                                    ) : null}
                                    {profile.concentration ? (
                                        <Badge variant="outline">
                                            {profile.concentration}
                                        </Badge>
                                    ) : null}
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {profile.whatsappUrl ? (
                                <Button
                                    asChild
                                    size="sm"
                                    className="bg-[#25D366] text-white hover:bg-[#20bd5a]"
                                >
                                    <a
                                        href={profile.whatsappUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <WhatsAppIcon className="mr-1.5 size-4" />
                                        WhatsApp
                                    </a>
                                </Button>
                            ) : null}
                            {canEditProfile ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link href="/settings/profile">
                                        Edit Profil
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </div>

                <ProfileDetailsSections profile={profile} />
            </div>
        </AppLayout>
    );
}
