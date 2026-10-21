import { Form } from '@inertiajs/react';

import PhoneConfirmationController from '@/actions/App/Http/Controllers/Settings/PhoneConfirmationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type PhonePrompt } from '@/types';


export default function PhonePromptDialog({ prompt }: { prompt: PhonePrompt }) {
    const isRequired = prompt.mode === 'required';

    return (
        <Dialog open>
            <DialogContent
                showCloseButton={false}
                onEscapeKeyDown={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
            >
                <DialogTitle>
                    {isRequired ? 'Lengkapi nomor HP' : 'Konfirmasi nomor HP'}
                </DialogTitle>
                <DialogDescription>
                    {isRequired
                        ? 'Nomor HP Anda belum tercatat. Isi nomor yang aktif untuk melanjutkan.'
                        : 'Nomor HP ini berasal dari data kampus. Pastikan masih aktif, lalu konfirmasi.'}
                </DialogDescription>

                <Form {...PhoneConfirmationController.update.form()}>
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="phone_number">Nomor HP</Label>
                                <Input
                                    id="phone_number"
                                    name="phone_number"
                                    defaultValue={prompt.phoneNumber ?? ''}
                                    placeholder="08xxxxxxxxxx"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    autoFocus
                                    required
                                />
                                <InputError
                                    message={errors.phone_number}
                                    className="text-xs"
                                />
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {isRequired
                                        ? 'Simpan nomor'
                                        : 'Konfirmasi nomor'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
