<?php

namespace App\Http\Requests\Dosen;

use App\Models\MentorshipDocument;
use App\Services\DocumentAccessService;
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
            && app(DocumentAccessService::class)->reviewReadOnlyReason($lecturer, $document) === null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('revision_notes'))) {
            $this->merge(['revision_notes' => trim($this->input('revision_notes'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:needs_revision,approved'],
            'revision_notes' => ['required_if:status,needs_revision', 'nullable', 'string'],
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
            'revision_notes.required_if' => 'Catatan revisi wajib diisi saat meminta revisi.',
        ];
    }
}
