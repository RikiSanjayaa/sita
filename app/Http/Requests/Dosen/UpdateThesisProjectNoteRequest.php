<?php

namespace App\Http\Requests\Dosen;

use App\Enums\ThesisNoteVisibility;
use App\Models\ThesisProjectNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThesisProjectNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ThesisProjectNote $note */
        $note = $this->route('note');

        return $this->user()?->can('update', $note) === true;
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
