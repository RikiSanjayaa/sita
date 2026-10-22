<?php

namespace App\Http\Requests\Dosen;

use App\Enums\ThesisNoteVisibility;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreThesisProjectNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ThesisProject $project */
        $project = $this->route('project');

        return $this->user()?->can('create', [ThesisProjectNote::class, $project]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visibility' => ['required', Rule::enum(ThesisNoteVisibility::class)],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
