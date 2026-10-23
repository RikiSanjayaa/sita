<?php

namespace App\Http\Requests\Dosen;

use App\Models\User;
use App\Services\MentorshipScheduleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJadwalBimbinganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(MentorshipScheduleService $mentorshipSchedules): array
    {
        $lecturer = $this->user();
        $supervisedStudentIds = $lecturer instanceof User
            ? $mentorshipSchedules->supervisedStudents($lecturer)->pluck('id')->all()
            : [];

        return [
            'student_user_id' => ['required', 'integer', Rule::in($supervisedStudentIds)],
            'topic' => ['required', 'string', 'max:255'],
            'requested_for' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'lecturer_note' => ['nullable', 'string', 'max:2000'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_pattern' => ['nullable', 'required_if:is_recurring,true', 'in:weekly,biweekly,monthly'],
            'recurring_count' => ['nullable', 'required_if:is_recurring,true', 'integer', 'min:2', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_user_id.in' => 'Pilih mahasiswa bimbingan Anda yang valid.',
        ];
    }
}
