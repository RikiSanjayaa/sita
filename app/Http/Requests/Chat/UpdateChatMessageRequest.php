<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['present', 'nullable', 'string', 'max:2000'],
            'attachment' => ['prohibited'],
            'reply_to_id' => ['prohibited'],
        ];
    }
}
