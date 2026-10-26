import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { MessageSquareText } from 'lucide-react';

import { ProfileDetailsSections } from '@/components/profile/profile-details-sections';
import { StudentProgressPeek } from '@/components/profile/student-progress-peek';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { WhatsAppIcon } from '@/components/whatsapp-icon';
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
    canStartPrivateChat: boolean;
    privateChatEndpoint: string | null;
};

export default function ProfileShowPage() {
    const {
        auth,
        canEditProfile,
        canStartPrivateChat,
        privateChatEndpoint,
        profile,
    } = usePage<SharedData & ProfileShowProps>().props;
    const getInitials = useInitials();
    const privateChatForm = useForm<{ recipient_id: number }>({
        recipient_id: profile.id,
    });

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
                <div>
                    <div className="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                        <div className="flex items-center gap-4 md:gap-5">
                            <Avatar className="size-16 border md:size-24">
                                <AvatarImage
                                    src={profile.avatar ?? undefined}
                                    alt={profile.name}
                                />
                                <AvatarFallback className="bg-primary-muted text-xl font-semibold text-primary-muted-foreground">
                                    {getInitials(profile.name)}
                                </AvatarFallback>
                            </Avatar>

                            <div className="space-y-2">
                                <div>
                                    <h1 className="text-2xl font-semibold tracking-tight text-balance text-foreground">
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
                                    {profile.degreeLevel ? (
                                        <Badge variant="secondary">
                                            {profile.degreeLevel}
                                        </Badge>
                                    ) : null}
                                    {profile.concentration ? (
                                        <Badge variant="outline">
                                            {profile.concentration}
                                        </Badge>
                                    ) : null}
                                    {profile.expertiseFields?.map((field) => (
                                        <Badge key={field} variant="secondary">
                                            {field}
                                        </Badge>
                                    ))}
                                </div>
                                {profile.progress ? (
                                    <StudentProgressPeek
                                        progress={profile.progress}
                                    />
                                ) : null}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {canStartPrivateChat && privateChatEndpoint ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="max-md:h-11"
                                    disabled={privateChatForm.processing}
                                    onClick={() =>
                                        privateChatForm.post(
                                            privateChatEndpoint,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <MessageSquareText className="mr-1.5 size-4" />
                                    Chat Pribadi
                                </Button>
                            ) : null}
                            {profile.whatsappUrl ? (
                                <Button
                                    asChild
                                    size="sm"
                                    className="bg-success text-success-foreground hover:bg-success/90 max-md:h-11"
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
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="max-md:h-11"
                                >
                                    <Link href="/settings/profile">
                                        Ubah profil
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
