import { Head, useForm, usePage } from '@inertiajs/react';
import { Camera, MessageCircle, Save } from 'lucide-react';
import { ChangeEvent, FormEvent, useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { ProfileDetailsSections } from '@/components/profile/profile-details-sections';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { makeSettingsBreadcrumbs } from '@/pages/settings/breadcrumbs';
import { type SharedData, type UserProfileDetail } from '@/types';

type ProfilePageProps = {
    profile: UserProfileDetail;
    status?: string | null;
};

type ProfileFormData = {
    name: string;
    email: string;
    phone_number: string;
    avatar: File | null;
    _method?: 'patch';
};

export default function ProfilePage() {
    const { auth, profile } = usePage<SharedData & ProfilePageProps>().props;
    const getInitials = useInitials();
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const objectUrlRef = useRef<string | null>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | null>(
        auth.user.avatar ?? null,
    );

    const breadcrumbs = makeSettingsBreadcrumbs('Profil', '/settings/profile');

    const form = useForm<ProfileFormData>({
        name: auth.user.name,
        email: auth.user.email,
        phone_number:
            typeof auth.user.phone_number === 'string'
                ? auth.user.phone_number
                : '',
        avatar: null,
    });

    useEffect(() => {
        return () => {
            if (objectUrlRef.current !== null) {
                URL.revokeObjectURL(objectUrlRef.current);
            }
        };
    }, []);

    function pickAvatar(event: ChangeEvent<HTMLInputElement>) {
        const nextFile = event.target.files?.[0] ?? null;

        form.setData('avatar', nextFile);

        if (objectUrlRef.current !== null) {
            URL.revokeObjectURL(objectUrlRef.current);
            objectUrlRef.current = null;
        }

        if (nextFile === null) {
            setAvatarPreview(auth.user.avatar ?? null);
            return;
        }

        const nextUrl = URL.createObjectURL(nextFile);
        objectUrlRef.current = nextUrl;
        setAvatarPreview(nextUrl);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            _method: 'patch',
        }));

        form.post('/settings/profile', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('avatar');

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }

                if (objectUrlRef.current !== null) {
                    URL.revokeObjectURL(objectUrlRef.current);
                    objectUrlRef.current = null;
                }
            },
        });
    }

    return (
        <AppLayout
            role={auth.activeRole === 'dosen' ? 'dosen' : auth.activeRole}
            breadcrumbs={breadcrumbs}
        >
            <Head title="Profil Saya" />

            <SettingsLayout>
                <div>
                    <h2 className="text-lg font-semibold">Perbarui profil</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Ubah nama, email, dan foto profil agar tampil konsisten
                        di seluruh aplikasi.
                    </p>
                </div>

                <Separator />

                <form
                    onSubmit={submit}
                    className="grid gap-6 lg:grid-cols-[220px_1fr]"
                >
                    <div className="flex flex-col items-center gap-4 rounded-xl border bg-muted/20 p-5 text-center">
                        <Avatar className="size-24 border">
                            <AvatarImage
                                src={
                                    avatarPreview ??
                                    auth.user.avatar ??
                                    undefined
                                }
                                alt={auth.user.name}
                            />
                            <AvatarFallback className="bg-primary/10 text-lg text-primary">
                                {getInitials(auth.user.name)}
                            </AvatarFallback>
                        </Avatar>

                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            className="hidden"
                            onChange={pickAvatar}
                        />

                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => fileInputRef.current?.click()}
                        >
                            <Camera className="mr-2 size-4" />
                            Ganti foto
                        </Button>

                        <div className="space-y-1 text-xs text-muted-foreground">
                            <p>JPG, PNG, atau WebP.</p>
                            <p>Maksimal 2 MB.</p>
                        </div>

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

                        <InputError
                            message={form.errors.avatar}
                            className="text-center text-xs"
                        />
                    </div>

                    <div className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama lengkap</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                autoComplete="name"
                                required
                            />
                            <InputError
                                message={form.errors.name}
                                className="text-xs"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                autoComplete="email"
                                required
                            />
                            <InputError
                                message={form.errors.email}
                                className="text-xs"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone_number">Nomor HP</Label>
                            <Input
                                id="phone_number"
                                value={form.data.phone_number}
                                onChange={(event) =>
                                    form.setData(
                                        'phone_number',
                                        event.target.value,
                                    )
                                }
                                placeholder="08xxxxxxxxxx"
                                inputMode="tel"
                            />
                            <InputError
                                message={form.errors.phone_number}
                                className="text-xs"
                            />
                        </div>

                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => {
                                    form.reset();
                                    setAvatarPreview(auth.user.avatar ?? null);

                                    if (fileInputRef.current) {
                                        fileInputRef.current.value = '';
                                    }

                                    if (objectUrlRef.current !== null) {
                                        URL.revokeObjectURL(
                                            objectUrlRef.current,
                                        );
                                        objectUrlRef.current = null;
                                    }
                                }}
                            >
                                Reset
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <Save className="mr-2 size-4" />
                                Simpan perubahan
                            </Button>
                        </div>
                    </div>
                </form>

                <Separator />

                <ProfileDetailsSections profile={profile} />
            </SettingsLayout>
        </AppLayout>
    );
}
