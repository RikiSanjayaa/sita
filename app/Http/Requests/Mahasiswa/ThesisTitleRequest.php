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
            'discussion_lecturer_user_id' => ['required', 'integer', 'exists:users,id'],
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

                $discussionLecturerId = $this->input('discussion_lecturer_user_id');

                if (! is_numeric($discussionLecturerId)) {
                    return;
                }

                $eligible = app(LecturerSearchService::class)->eligibleIdsInProgramStudi(
                    (int) $programStudiId,
                    [(int) $discussionLecturerId],
                );

                if (! in_array((int) $discussionLecturerId, $eligible, true)) {
                    $validator->errors()->add('discussion_lecturer_user_id', 'Dosen diskusi proposal harus dosen aktif di program studi Anda.');
                }
            },
        ];
    }
}
