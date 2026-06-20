<?php

namespace App\Http\Requests\Kaprodi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchLecturersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('kaprodi') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:thesis_projects,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'purpose' => ['required', Rule::in(['supervisor', 'examiner'])],
            'selected_ids' => ['nullable', 'array', 'max:10'],
            'selected_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
