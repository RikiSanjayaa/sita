<?php

namespace App\Http\Requests\Dosen;

use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyRevisionRequest extends FormRequest
{
    /**
     * An examiner only verifies the items of their own revision.
     */
    public function authorize(): bool
    {
        /** @var ThesisRevision $revision */
        $revision = $this->route('revision');

        return $revision->requested_by_user_id === $this->user()?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verdicts' => ['required', 'array'],
            'verdicts.*.decision' => ['required', Rule::in([ThesisRevisionItem::STATUS_ACCEPTED, ThesisRevisionItem::STATUS_REOPENED])],
            'verdicts.*.comment' => ['nullable', 'string', 'max:2000', 'required_if:verdicts.*.decision,'.ThesisRevisionItem::STATUS_REOPENED],
            'new_items' => ['nullable', 'array', 'max:50'],
            'new_items.*.location' => ['nullable', 'string', 'max:255'],
            'new_items.*.description' => ['required', 'string', 'max:2000'],
            'new_items.*.priority' => ['nullable', Rule::in(ThesisRevisionItem::PRIORITIES)],
            'annotation' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verdicts.*.decision.required' => 'Pilih diterima atau belum untuk butir ini.',
            'verdicts.*.decision.in' => 'Pilih diterima atau belum untuk butir ini.',
            'verdicts.*.comment.required_if' => 'Tulis komentar untuk butir yang belum diterima.',
            'new_items.*.description.required' => 'Uraian butir baru wajib diisi.',
        ];
    }

    /**
     * Every answered item must get a verdict, and nothing else may.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ThesisRevision $revision */
                $revision = $this->route('revision');

                $answered = $revision->items()
                    ->where('status', ThesisRevisionItem::STATUS_ADDRESSED)
                    ->pluck('id');

                $judged = collect(array_keys((array) $this->input('verdicts', [])));

                foreach ($answered->diff($judged) as $itemId) {
                    $validator->errors()->add("verdicts.{$itemId}.decision", 'Pilih diterima atau belum untuk butir ini.');
                }

                if ($judged->diff($answered)->isNotEmpty()) {
                    $validator->errors()->add('verdicts', 'Ada butir yang tidak menunggu verifikasi.');
                }
            },
        ];
    }

    /**
     * @return array<int, array{decision: string, comment: ?string}>
     */
    public function verdicts(): array
    {
        return collect($this->validated('verdicts'))
            ->mapWithKeys(fn (array $verdict, int|string $itemId): array => [
                (int) $itemId => [
                    'decision' => (string) $verdict['decision'],
                    'comment' => $verdict['comment'] ?? null,
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array{location?: ?string, description: string, priority?: ?string}>
     */
    public function newItems(): array
    {
        return array_values($this->validated('new_items') ?? []);
    }
}
