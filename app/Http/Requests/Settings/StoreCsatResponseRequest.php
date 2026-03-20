<?php

namespace App\Http\Requests\Settings;

use App\Enums\AppRole;
use App\Models\CsatResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCsatResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $activeRole = $user->resolveActiveRole($this->session()->get('active_role'));

        return in_array($activeRole, [AppRole::Mahasiswa->value, AppRole::Dosen->value], true);
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'between:1,5'],
            'kritik' => ['nullable', 'string', 'max:2000'],
            'saran' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user === null) {
                return;
            }

            $latestResponse = CsatResponse::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->first();

            if (! $latestResponse instanceof CsatResponse) {
                return;
            }

            $nextAvailableAt = $latestResponse->cooldownEndsAt();

            if ($nextAvailableAt === null || ! $nextAvailableAt->isFuture()) {
                return;
            }

            $validator->errors()->add(
                'score',
                sprintf(
                    'Anda sudah mengirim CSAT. Silakan kirim lagi setelah %s.',
                    $nextAvailableAt->locale('id')->translatedFormat('d M Y'),
                ),
            );
        });
    }
}
