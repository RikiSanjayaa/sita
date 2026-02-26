<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertMentorshipAssignmentRequest extends FormRequest
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
            'student_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'primary_lecturer_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'secondary_lecturer_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
