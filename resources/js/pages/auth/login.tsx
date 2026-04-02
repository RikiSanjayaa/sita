import { Form, Head } from '@inertiajs/react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    return (
        <AuthLayout
            title="Selamat datang kembali"
            description="Masukkan email dan kata sandi untuk melanjutkan"
        >
            <Head title="Masuk" />

            {status && (
                <Alert className="mb-4">
                    <AlertTitle>Berhasil</AlertTitle>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        {/* Email */}
                        <div className="grid gap-1.5">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="alamat-email@ubg.ac.id"
                                className="h-10"
                            />
                            <InputError message={errors.email} />
                        </div>

                        {/* Password */}
                        <div className="grid gap-1.5">
                            <div className="flex items-center">
                                <Label htmlFor="password">Kata sandi</Label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request().url}
                                        className="ml-auto text-xs"
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
                                className="h-10"
                            />
                            <InputError message={errors.password} />
                        </div>

                        {/* Remember me */}
                        <div className="flex items-center gap-2.5">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            <Label
                                htmlFor="remember"
                                className="cursor-pointer text-sm font-normal"
                            >
                                Ingat saya
                            </Label>
                        </div>

                        {/* Submit */}
                        <Button
                            type="submit"
                            className="mt-1 h-10 w-full"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Masuk
                        </Button>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
