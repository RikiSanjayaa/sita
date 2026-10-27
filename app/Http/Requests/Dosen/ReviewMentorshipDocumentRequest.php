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
        $items = $this->input('revision_items');
        if (is_array($items)) {
            $this->merge(['revision_items' => array_map(fn ($item) => is_array($item)
                ? [...$item, 'description' => is_string($item['description'] ?? null) ? trim($item['description']) : ($item['description'] ?? null)]
                : $item, $items)]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:needs_revision,approved'],
            'revision_notes' => [$this->input('status') === 'needs_revision' && empty($this->input('revision_items')) ? 'required' : 'nullable', 'nullable', 'string'],
            'revision_items' => ['nullable', 'array', 'max:50'],
            'revision_items.*.id' => ['nullable', 'uuid', 'distinct'],
            'revision_items.*.description' => ['required', 'string'],
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
            'revision_notes.required' => 'Catatan revisi wajib diisi saat meminta revisi.',
            'revision_items.max' => 'Maksimal 50 butir revisi.',
            'revision_items.*.description.required' => 'Isi uraian butir revisi.',
        ];
    }
}
