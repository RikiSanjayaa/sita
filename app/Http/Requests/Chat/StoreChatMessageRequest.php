<?php

namespace App\Http\Requests\Chat;

use App\Models\MentorshipChatMessage;
use App\Services\ChatMessagePayloadBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'client_message_id' => ['required', 'uuid'],
            'reply_to_id' => ['nullable', 'integer', Rule::exists('mentorship_chat_messages', 'id')->where('mentorship_chat_thread_id', $this->route('thread')?->id)],
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

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->has('reply_to_id') || ! $this->filled('reply_to_id')) {
                return;
            }
            $context = new MentorshipChatMessage([
                'mentorship_chat_thread_id' => $this->route('thread')->id,
                'reply_to_id' => $this->integer('reply_to_id'),
            ]);
            if (! app(ChatMessagePayloadBuilder::class)->replyContext($context)['available']) {
                $validator->errors()->add('reply_to_id', 'Pesan asal tidak tersedia.');
            }
        }];
    }
}
