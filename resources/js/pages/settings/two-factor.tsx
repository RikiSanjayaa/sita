import { Form, Head } from '@inertiajs/react';
import { ShieldBan, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { makeSettingsBreadcrumbs } from '@/pages/settings/breadcrumbs';
import { disable, enable, show } from '@/routes/two-factor';

interface TwoFactorProps {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

const breadcrumbs = makeSettingsBreadcrumbs('Dua Faktor', show.url());

export default function TwoFactor({
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: TwoFactorProps) {
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-Factor Authentication" />

            <SettingsLayout>
                <div>
                    <h2 className="text-lg font-semibold">
                        Autentikasi dua faktor
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Tingkatkan keamanan akun Anda dengan verifikasi dua
                        langkah menggunakan aplikasi authenticator.
                    </p>
                </div>

                <Separator />

                {twoFactorEnabled ? (
                    <div className="flex flex-col items-start justify-start space-y-4">
                        <Badge className="bg-success-muted text-success-muted-foreground">
                            Enabled
                        </Badge>
                        <p className="text-sm text-muted-foreground">
                            With two-factor authentication enabled, you will be
                            prompted for a secure, random pin during login,
                            which you can retrieve from the TOTP-supported
                            application on your phone.
                        </p>

                        <TwoFactorRecoveryCodes
                            recoveryCodesList={recoveryCodesList}
                            fetchRecoveryCodes={fetchRecoveryCodes}
                            errors={errors}
                        />

                        <div className="relative inline">
                            <Form {...disable.form()}>
                                {({ processing }) => (
                                    <Button
                                        variant="destructive"
                                        type="submit"
                                        disabled={processing}
                                    >
                                        <ShieldBan /> Disable 2FA
                                    </Button>
                                )}
                            </Form>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col items-start justify-start space-y-4">
                        <Badge className="bg-danger-muted text-danger-muted-foreground">
                            Disabled
                        </Badge>
                        <p className="text-sm text-muted-foreground">
                            When you enable two-factor authentication, you will
                            be prompted for a secure pin during login. This pin
                            can be retrieved from a TOTP-supported application
                            on your phone.
                        </p>

                        <div>
                            {hasSetupData ? (
                                <Button
                                    className="max-md:h-11"
                                    onClick={() => setShowSetupModal(true)}
                                >
                                    <ShieldCheck />
                                    Continue Setup
                                </Button>
                            ) : (
                                <Form
                                    {...enable.form()}
                                    onSuccess={() => setShowSetupModal(true)}
                                >
                                    {({ processing }) => (
                                        <Button
                                            className="max-md:h-11"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <ShieldCheck />
                                            Enable 2FA
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </div>
                    </div>
                )}

                <TwoFactorSetupModal
                    isOpen={showSetupModal}
                    onClose={() => setShowSetupModal(false)}
                    requiresConfirmation={requiresConfirmation}
                    twoFactorEnabled={twoFactorEnabled}
                    qrCodeSvg={qrCodeSvg}
                    manualSetupKey={manualSetupKey}
                    clearSetupData={clearSetupData}
                    fetchSetupData={fetchSetupData}
                    errors={errors}
                />
            </SettingsLayout>
        </AppLayout>
    );
}
