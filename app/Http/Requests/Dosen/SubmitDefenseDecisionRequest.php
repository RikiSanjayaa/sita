<?php

namespace App\Http\Requests\Dosen;

use App\Models\ThesisDefense;
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

        if (! $isSempro) {
            array_splice($revisionNotesRules, 1, 0, 'required_if:decision,pass_with_revision');
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
        ];
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
        ];
    }
}
