<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    /**
     * Access to the thread is enforced by the controller that owns it.
     */
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
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:2000'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
                'max:10240',
            ],
            'document_name' => ['nullable', 'string', 'max:150'],
        ];
    }
}
