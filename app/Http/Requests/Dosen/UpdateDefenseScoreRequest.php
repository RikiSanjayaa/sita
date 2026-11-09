<?php

namespace App\Http\Requests\Dosen;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDefenseScoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('dosen')
            && $this->route('defense')->type === 'sidang'
            && $this->route('defense')->examiners()->where('lecturer_user_id', $this->user()->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'expected_score' => ['present', 'nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }
}
