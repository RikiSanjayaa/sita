<?php

namespace App\Http\Requests;

use App\Support\AcademicTerminology;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentProgressIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'prodi' => ['nullable', 'integer', Rule::exists('program_studis', 'id')],
            'phase' => ['nullable', 'string', Rule::in(array_keys(AcademicTerminology::phaseLabels()))],
            'angkatan' => ['nullable', 'integer', 'digits:4'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50, 100])],
        ];
    }
}
