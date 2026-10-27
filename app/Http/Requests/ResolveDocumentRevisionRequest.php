<?php

namespace App\Http\Requests;

use App\Models\MentorshipDocument;
use App\Services\DocumentRevisionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResolveDocumentRevisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof MentorshipDocument && $this->user() !== null
            && app(DocumentRevisionService::class)->canResolve($this->user(), $document);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resolved' => ['required', 'boolean'],
        ];
    }
}
