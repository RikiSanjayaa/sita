import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

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
    const [passwordVisible, setPasswordVisible] = useState(false);
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
                                        tabIndex={6}
                                    >
                                        Lupa kata sandi?
                                    </TextLink>
                                )}
                            </div>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={passwordVisible ? 'text' : 'password'}
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Masukkan kata sandi"
                                    className="h-10 pr-12 max-md:h-11"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    tabIndex={3}
                                    aria-label={
                                        passwordVisible
                                            ? 'Sembunyikan kata sandi'
                                            : 'Tampilkan kata sandi'
                                    }
                                    aria-controls="password"
                                    aria-pressed={passwordVisible}
                                    className="absolute inset-y-0 right-0 size-10 text-muted-foreground max-md:size-11"
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() =>
                                        setPasswordVisible((value) => !value)
                                    }
                                >
                                    {passwordVisible ? (
                                        <EyeOff
                                            aria-hidden
                                            className="size-4"
                                        />
                                    ) : (
                                        <Eye aria-hidden className="size-4" />
                                    )}
                                </Button>
                            </div>
                            <InputError message={errors.password} />
                        </div>

                        {/* Remember me */}
                        <div className="flex items-center gap-2.5">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={4}
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
                            tabIndex={5}
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
