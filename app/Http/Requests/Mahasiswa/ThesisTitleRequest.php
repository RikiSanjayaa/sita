<?php

namespace App\Http\Requests\Mahasiswa;

use App\Services\LecturerSearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ThesisTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('mahasiswa') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCreating = $this->isMethod('POST');

        return [
            'title_id' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'proposal_summary' => ['required', 'string'],
            'proposal_file' => [$isCreating ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'proposed_primary_lecturer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'proposed_secondary_lecturer_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                'different:proposed_primary_lecturer_user_id',
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $programStudiId = $this->user()?->mahasiswaProfile?->program_studi_id;

                if ($programStudiId === null) {
                    return;
                }

                $proposed = collect([
                    'proposed_primary_lecturer_user_id',
                    'proposed_secondary_lecturer_user_id',
                ])->filter(fn(string $field): bool => $this->filled($field));

                if ($proposed->isEmpty()) {
                    return;
                }

                $eligible = app(LecturerSearchService::class)->eligibleIdsInProgramStudi(
                    (int) $programStudiId,
                    $proposed->map(fn(string $field): int => (int) $this->input($field))->all(),
                );

                $proposed->each(function (string $field) use ($validator, $eligible): void {
                    if (! in_array((int) $this->input($field), $eligible, true)) {
                        $validator->errors()->add($field, 'Dosen yang diusulkan harus dosen aktif di program studi Anda.');
                    }
                });
            },
        ];
    }
}
