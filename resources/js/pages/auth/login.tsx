import { Form, Head } from '@inertiajs/react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

import { register } from '@/routes';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

export default function Login({
    status,
    canResetPassword,
    canRegister,
}: LoginProps) {
    return (
        <AuthLayout
            title="Masuk"
            description="Masukkan email dan kata sandi untuk melanjutkan"
        >
            <Head title="Masuk" />

            {status && (
                <Alert className="mb-6">
                    <AlertTitle>Berhasil</AlertTitle>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col"
            >
                {({ processing, errors }) => (
                    <Card>
                        <CardHeader>
                            <CardTitle>Masuk ke akun Anda</CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="nama@kampus.ac.id"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Kata sandi</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request().url}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Lupa kata sandi?
                                        </TextLink>
                                    )}
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Masukkan kata sandi"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Ingat saya</Label>
                            </div>
                        </CardContent>

                        <CardFooter className="flex flex-col gap-4">
                            <Button
                                type="submit"
                                className="w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Masuk
                            </Button>

                            {canRegister && (
                                <div className="text-center text-sm text-muted-foreground">
                                    Belum punya akun?{' '}
                                    <TextLink
                                        href={register().url}
                                        tabIndex={5}
                                    >
                                        Daftar
                                    </TextLink>
                                </div>
                            )}
                        </CardFooter>
                    </Card>
                )}
            </Form>
        </AuthLayout>
    );
}
