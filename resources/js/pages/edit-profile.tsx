import { Head, usePage } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { type FormEvent } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { dashboard, editProfile } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Edit Profil',
        href: editProfile().url,
    },
];

type Pembimbing = {
    nama: string;
    email: string;
};

export default function EditProfile() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();

    const userAny = auth.user as unknown as Record<string, unknown>;
    const nim =
        typeof userAny.nim === 'string'
            ? userAny.nim
            : typeof userAny.nim === 'number'
                ? String(userAny.nim)
                : String(auth.user.id).padStart(9, '0');

    const akademik = {
        nim,
        programStudi:
            typeof userAny.program_studi === 'string'
                ? userAny.program_studi
                : 'Ilmu Komputer',
        fakultas:
            typeof userAny.fakultas === 'string'
                ? userAny.fakultas
                : 'Fakultas Teknik',
        status:
            typeof userAny.status_mahasiswa === 'string'
                ? userAny.status_mahasiswa
                : 'Aktif',
        tahunAngkatan:
            typeof userAny.tahun_angkatan === 'string'
                ? userAny.tahun_angkatan
                : '2023',
    };
    const statusIsPositive = /aktif|disetujui|selesai|terjadwal/i.test(
        akademik.status,
    );

    const pembimbing1: Pembimbing = {
        nama: 'Dr. Budi Santoso, M.Kom.',
        email: 'budi.santoso@univ.ac.id',
    };

    const pembimbing2: Pembimbing = {
        nama: 'Dr. Siti Aminah, M.T.',
        email: 'siti.aminah@univ.ac.id',
    };

    const tugasAkhir = {
        judul: 'Implementasi Machine Learning untuk Prediksi Kelulusan Mahasiswa Menggunakan Algoritma Random Forest',
        pembimbing1,
        pembimbing2,
    };

    function onSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
    }

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            subtitle={`Selamat datang kembali, ${auth.user.name}`}
        >
            <Head title="Edit Profil" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-xl font-semibold">Edit Profil</h1>
                    <p className="text-sm text-muted-foreground">
                        Kelola informasi profil Anda
                    </p>
                </div>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Informasi Pribadi</CardTitle>
                        <CardDescription>
                            Data yang dapat Anda perbarui
                        </CardDescription>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <form
                            id="edit-profile-form"
                            className="grid gap-6"
                            onSubmit={onSubmit}
                        >
                            <div className="grid gap-4 md:grid-cols-[220px_1fr] md:items-center">
                                <div className="flex items-start gap-4">
                                    <div className="relative">
                                        <Avatar className="h-20 w-20">
                                            <AvatarImage
                                                src={auth.user.avatar}
                                                alt={auth.user.name}
                                            />
                                            <AvatarFallback>
                                                {getInitials(auth.user.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="absolute -right-2 -bottom-2 h-9 w-9 rounded-full bg-background"
                                            aria-label="Ubah foto profil"
                                        >
                                            <Camera className="size-4" />
                                        </Button>
                                    </div>

                                    <div className="min-w-0">
                                        <div className="text-sm font-medium">
                                            Foto Profil
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            Klik ikon kamera untuk mengubah foto
                                            profil
                                        </div>
                                        <div className="mt-2 text-xs text-muted-foreground">
                                            Rekomendasi: JPG/PNG, maksimal 2 MB
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="nama">Nama Lengkap</Label>
                                    <Input
                                        id="nama"
                                        name="nama"
                                        autoComplete="name"
                                        defaultValue={auth.user.name}
                                        placeholder="Nama sesuai identitas"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Nama ini akan tampil di profil dan
                                        beberapa dokumen.
                                    </p>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Email Personal
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            autoComplete="email"
                                            defaultValue={auth.user.email}
                                            placeholder="nama@email.com"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Digunakan untuk pengingat dan
                                            notifikasi.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="telp">
                                            Nomor Telepon
                                        </Label>
                                        <Input
                                            id="telp"
                                            name="telp"
                                            inputMode="tel"
                                            autoComplete="tel"
                                            placeholder="08xxxxxxxxxx"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Pastikan nomor aktif untuk
                                            verifikasi cepat.
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="alamat">Alamat</Label>
                                    <Input
                                        id="alamat"
                                        name="alamat"
                                        placeholder="Jl. Contoh No. 123, Jakarta Selatan"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Gunakan alamat domisili saat ini.
                                    </p>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                    <Separator />
                    <CardFooter className="justify-end gap-2 py-4">
                        <Button
                            type="reset"
                            form="edit-profile-form"
                            variant="outline"
                        >
                            Batalkan
                        </Button>
                        <Button
                            type="submit"
                            form="edit-profile-form"
                            className="bg-primary text-primary-foreground hover:bg-primary/90"
                        >
                            Simpan Perubahan
                        </Button>
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Informasi Akademik</CardTitle>
                        <CardDescription>
                            Data dari sistem akademik (tidak dapat diubah)
                        </CardDescription>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="grid gap-1">
                                <div className="text-xs text-muted-foreground">
                                    NIM
                                </div>
                                <div className="text-sm font-medium">
                                    {akademik.nim}
                                </div>
                            </div>

                            <div className="grid gap-1">
                                <div className="text-xs text-muted-foreground">
                                    Status
                                </div>
                                <div>
                                    <Badge
                                        className={
                                            statusIsPositive
                                                ? 'bg-emerald-600 text-white hover:bg-emerald-600/90 dark:bg-emerald-500 dark:hover:bg-emerald-500/90'
                                                : 'bg-primary text-primary-foreground hover:bg-primary/90'
                                        }
                                    >
                                        {akademik.status}
                                    </Badge>
                                </div>
                            </div>

                            <div className="grid gap-1">
                                <div className="text-xs text-muted-foreground">
                                    Program Studi
                                </div>
                                <div className="text-sm font-medium">
                                    {akademik.programStudi}
                                </div>
                            </div>

                            <div className="grid gap-1">
                                <div className="text-xs text-muted-foreground">
                                    Tahun Angkatan
                                </div>
                                <div className="text-sm font-medium">
                                    {akademik.tahunAngkatan}
                                </div>
                            </div>

                            <div className="grid gap-1">
                                <div className="text-xs text-muted-foreground">
                                    Fakultas
                                </div>
                                <div className="text-sm font-medium">
                                    {akademik.fakultas}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Informasi Tugas Akhir</CardTitle>
                        <CardDescription>
                            Detail pembimbingan tugas akhir Anda
                        </CardDescription>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <div className="grid gap-6">
                            <div className="grid gap-1">
                                <div className="text-xs text-muted-foreground">
                                    Judul Tugas Akhir
                                </div>
                                <div className="text-sm font-medium">
                                    {tugasAkhir.judul}
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="rounded-lg border bg-background p-4">
                                    <div className="text-xs text-muted-foreground">
                                        Dosen Pembimbing 1
                                    </div>
                                    <div className="mt-1 text-sm font-medium">
                                        {tugasAkhir.pembimbing1.nama}
                                    </div>
                                    <div className="mt-1 text-sm text-muted-foreground">
                                        {tugasAkhir.pembimbing1.email}
                                    </div>
                                </div>

                                <div className="rounded-lg border bg-background p-4">
                                    <div className="text-xs text-muted-foreground">
                                        Dosen Pembimbing 2
                                    </div>
                                    <div className="mt-1 text-sm font-medium">
                                        {tugasAkhir.pembimbing2.nama}
                                    </div>
                                    <div className="mt-1 text-sm text-muted-foreground">
                                        {tugasAkhir.pembimbing2.email}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

