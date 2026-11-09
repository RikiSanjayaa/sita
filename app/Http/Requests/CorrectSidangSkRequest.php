<?php

namespace App\Http\Requests;

use App\Policies\SidangPaperPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CorrectSidangSkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $defense = $this->route('defense');

        return $defense->type === 'sidang'
            && app(SidangPaperPolicy::class)->manage($this->user(), (int) $defense->project->program_studi_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sidang_sk' => ['required', 'string', 'max:150'],
            'expected_version' => ['required', 'string', 'size:64'],
        ];
    }

    public function attributes(): array
    {
        return ['sidang_sk' => 'No SK baru'];
    }
}
