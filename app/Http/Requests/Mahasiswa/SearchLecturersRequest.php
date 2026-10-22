<?php

namespace App\Http\Requests\Mahasiswa;

use Illuminate\Foundation\Http\FormRequest;

class SearchLecturersRequest extends FormRequest
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
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'selected_ids' => ['nullable', 'array', 'max:10'],
            'selected_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
