<?php

namespace App\Http\Requests;

use App\Policies\SidangPaperPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreSidangPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        $defense = $this->route('defense');

        return $defense->type === 'sidang'
            && app(SidangPaperPolicy::class)->manage($this->user(), (int) $defense->project->program_studi_id);
    }

    public function rules(): array
    {
        return [
            'moderator_user_id' => ['nullable', 'integer'],
            'sidang_sk' => ['nullable', 'string', 'max:150'],
            'scores' => ['sometimes', 'array:examiner_1,examiner_2,supervisor'],
            'scores.*' => ['required', 'array:panel_id,value,expected_value'],
            'scores.*.panel_id' => ['required', 'integer', 'distinct'],
            'scores.*.value' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'scores.*.expected_value' => ['sometimes', 'nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }
}
