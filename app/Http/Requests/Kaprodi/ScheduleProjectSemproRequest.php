<?php

namespace App\Http\Requests\Kaprodi;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleProjectSemproRequest extends FormRequest
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
            'examiner_1_user_id' => ['required', 'integer', 'exists:users,id'],
            'examiner_2_user_id' => ['nullable', 'integer', 'exists:users,id', 'different:examiner_1_user_id'],
        ];
    }
}
