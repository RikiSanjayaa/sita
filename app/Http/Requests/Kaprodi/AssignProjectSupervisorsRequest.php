<?php

namespace App\Http\Requests\Kaprodi;

use Illuminate\Foundation\Http\FormRequest;

class AssignProjectSupervisorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'primary_lecturer_user_id' => ['required', 'integer', 'exists:users,id'],
            'secondary_lecturer_user_id' => ['required', 'integer', 'exists:users,id', 'different:primary_lecturer_user_id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
