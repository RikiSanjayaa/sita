<?php

namespace App\Http\Requests\Kaprodi;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleProjectSidangRequest extends FormRequest
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
            'scheduled_for' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'in:offline,online,hybrid'],
            'additional_examiner_user_ids' => ['required', 'array', 'min:1'],
            'additional_examiner_user_ids.*' => ['integer', 'exists:users,id', 'distinct'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
