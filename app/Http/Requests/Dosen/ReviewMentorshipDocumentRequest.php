<?php

namespace App\Http\Requests\Dosen;

use App\Models\MentorshipDocument;
use Illuminate\Foundation\Http\FormRequest;

class ReviewMentorshipDocumentRequest extends FormRequest
{
    /**
     * Only the lecturer who received this document copy may review it.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');
        $lecturer = $this->user();

        return $document instanceof MentorshipDocument
            && $lecturer !== null
            && $document->lecturer_user_id === $lecturer->id;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:needs_revision,approved'],
            'revision_notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status review wajib dipilih.',
            'status.in' => 'Status review tidak valid.',
        ];
    }
}
