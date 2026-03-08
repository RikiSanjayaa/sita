import { Head, Link, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';

import { ProfileDetailsSections } from '@/components/profile/profile-details-sections';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-center gap-4">
                                <Avatar className="size-20 border">
                                    <AvatarImage
                                        src={profile.avatar ?? undefined}
                                        alt={profile.name}
                                    />
                                    <AvatarFallback className="bg-primary/10 text-lg text-primary">
                                        {getInitials(profile.name)}
                                    </AvatarFallback>
                                </Avatar>

                                <div className="space-y-2">
                                    <div>
                                        <CardTitle className="text-xl">
                                            {profile.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {profile.description}
                                        </CardDescription>
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

                            <div className="flex flex-wrap gap-2">
                                {profile.whatsappUrl ? (
                                    <Button asChild variant="secondary">
                                        <a
                                            href={profile.whatsappUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <MessageCircle className="mr-2 size-4" />
                                            Chat WhatsApp
                                        </a>
                                    </Button>
                                ) : null}
                                {canEditProfile ? (
                                    <Button asChild variant="outline">
                                        <Link href="/settings/profile">
                                            Edit Profil Saya
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            Email kontak: {profile.email}
                        </p>
                    </CardContent>
                </Card>

                <ProfileDetailsSections profile={profile} />
            </div>
        </AppLayout>
    );
}
