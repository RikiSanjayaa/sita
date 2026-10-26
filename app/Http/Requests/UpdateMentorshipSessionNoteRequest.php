<?php

namespace App\Http\Requests;

use App\Models\MentorshipSchedule;
use App\Services\MentorshipSessionNoteService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMentorshipSessionNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $schedule = $this->route('schedule');
        $viewer = $this->user();

        $participantId = $this->routeIs('mahasiswa.*') ? $schedule?->student_user_id : $schedule?->lecturer_user_id;

        return $schedule instanceof MentorshipSchedule && $viewer !== null
            && (int) $participantId === (int) $viewer->id
            && app(MentorshipSessionNoteService::class)->readOnlyReason($schedule, $viewer) === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['body.required' => 'Isi catatan hasil bimbingan sebelum menyimpan.'];
    }
}
