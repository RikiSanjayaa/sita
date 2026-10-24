<?php

namespace App\Http\Requests\Dosen;

use App\Models\ThesisDefense;
use App\Models\ThesisRevisionItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitDefenseDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sidang and OBE sempro (Ujian Sinopsis) need score + comment; non-OBE
     * sempro is decision-only and rejects a score.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ThesisDefense $defense */
        $defense = $this->route('defense');
        $isSempro = $defense->type === 'sempro';

        $revisionNotesRules = ['nullable', 'string', 'max:2000'];

        // Sidang "lulus dengan syarat" needs something to revise: a note or at least one item.
        if (! $isSempro) {
            $revisionNotesRules[] = Rule::requiredIf(
                fn (): bool => $this->input('decision') === 'pass_with_revision' && ! $this->hasRevisionItems(),
            );
        }

        if (! $defense->requiresScore()) {
            $scoreRules = ['prohibited'];
        } else {
            $scoreRules = ['required', 'numeric', 'min:0', 'max:100'];
        }

        return [
            'decision' => [
                'required',
                Rule::in($isSempro ? ['pass', 'fail'] : ['pass', 'pass_with_revision', 'fail']),
            ],
            'score' => $scoreRules,
            'decision_notes' => $isSempro && $defense->requiresScore()
                ? ['required', 'string', 'max:2000']
                : ['nullable', 'string', 'max:2000'],
            'revision_notes' => $revisionNotesRules,
            'revision_items' => ['nullable', 'array', 'max:50'],
            'revision_items.*.location' => ['nullable', 'string', 'max:255'],
            'revision_items.*.description' => ['required', 'string', 'max:2000'],
            'revision_items.*.priority' => ['nullable', Rule::in(ThesisRevisionItem::PRIORITIES)],
            'revision_annotation' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    public function hasRevisionItems(): bool
    {
        return filled($this->input('revision_items'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'score.required' => 'Nilai wajib diisi.',
            'score.prohibited' => 'Ujian ini tidak menggunakan nilai.',
            'decision_notes.required' => 'Komentar wajib diisi.',
            'revision_notes.required' => 'Isi catatan revisi atau tambahkan minimal satu butir revisi.',
            'revision_items.max' => 'Maksimal 50 butir revisi.',
            'revision_items.*.description.required' => 'Uraian butir revisi wajib diisi.',
            'revision_annotation.mimes' => 'Naskah beranotasi harus berupa file PDF.',
            'revision_annotation.max' => 'Ukuran naskah beranotasi maksimal 20 MB.',
        ];
    }
}
