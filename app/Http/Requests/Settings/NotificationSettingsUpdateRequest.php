<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class NotificationSettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'browserNotifications' => ['required', 'boolean'],
            'pesanBaru' => ['required', 'boolean'],
            'statusTugasAkhir' => ['required', 'boolean'],
            'jadwalBimbingan' => ['required', 'boolean'],
            'feedbackDokumen' => ['required', 'boolean'],
            'reminderDeadline' => ['required', 'boolean'],
            'pengumumanSistem' => ['required', 'boolean'],
            'konfirmasiBimbingan' => ['required', 'boolean'],
        ];
    }
}
