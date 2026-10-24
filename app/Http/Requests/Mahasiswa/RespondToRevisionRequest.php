<?php

namespace App\Http\Requests\Mahasiswa;

use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RespondToRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ThesisRevision $revision */
        $revision = $this->route('revision');
        $revision->loadMissing('project');

        return $revision->project?->student_user_id === $this->user()?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'responses' => ['required', 'array'],
            'responses.*.addressed' => ['required', 'accepted'],
            'responses.*.response' => ['required', 'string', 'max:2000'],
            'responses.*.location' => ['nullable', 'string', 'max:255'],
            'manuscript' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'responses.*.addressed.required' => 'Tandai butir ini sudah diperbaiki.',
            'responses.*.addressed.accepted' => 'Tandai butir ini sudah diperbaiki.',
            'responses.*.response.required' => 'Tulis tanggapan untuk butir ini.',
            'manuscript.required' => 'Unggah naskah perbaikan.',
            'manuscript.mimes' => 'Naskah perbaikan harus berupa PDF atau Word.',
            'manuscript.max' => 'Naskah perbaikan maksimal 20 MB.',
        ];
    }

    /**
     * Every item the student still owes an answer for must be answered, and
     * nothing else: the service enforces the same, this gives per-item errors.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ThesisRevision $revision */
                $revision = $this->route('revision');

                $pending = $revision->items()
                    ->whereIn('status', [ThesisRevisionItem::STATUS_OPEN, ThesisRevisionItem::STATUS_REOPENED])
                    ->pluck('id');

                $answered = collect(array_keys((array) $this->input('responses', [])));

                foreach ($pending->diff($answered) as $itemId) {
                    $validator->errors()->add("responses.{$itemId}.response", 'Tulis tanggapan untuk butir ini.');
                }

                if ($answered->diff($pending)->isNotEmpty()) {
                    $validator->errors()->add('responses', 'Ada butir yang tidak perlu ditanggapi.');
                }
            },
        ];
    }

    /**
     * @return array<int, array{response: string, location: ?string}>
     */
    public function responses(): array
    {
        return collect($this->validated('responses'))
            ->mapWithKeys(fn (array $answer, int|string $itemId): array => [
                (int) $itemId => [
                    'response' => (string) $answer['response'],
                    'location' => $answer['location'] ?? null,
                ],
            ])
            ->all();
    }
}
