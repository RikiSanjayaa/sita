<?php

namespace App\Http\Requests\Kaprodi;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleProjectSidangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_for' => ['nullable', 'date'],
            'scheduled_date_start' => ['nullable', 'required_without:scheduled_for', 'date'],
            'scheduled_date_end' => ['nullable', 'required_with:scheduled_date_start', 'date', 'after_or_equal:scheduled_date_start'],
            'scheduled_time' => ['nullable', 'required_with:scheduled_date_start', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'in:offline,online,hybrid'],
            'additional_examiner_user_ids' => ['required', 'array', 'min:1'],
            'additional_examiner_user_ids.*' => ['integer', 'exists:users,id', 'distinct'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_date_start.required_without' => 'Rentang tanggal wajib diisi.',
            'scheduled_date_end.required_with' => 'Tanggal akhir wajib diisi saat tanggal mulai dipilih.',
            'scheduled_date_end.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
            'scheduled_time.required_with' => 'Jam wajib diisi saat rentang tanggal dipilih.',
            'scheduled_time.date_format' => 'Format jam tidak valid.',
            'location.required' => 'Lokasi wajib diisi.',
            'mode.required' => 'Mode ujian wajib dipilih.',
            'mode.in' => 'Mode ujian tidak valid.',
            'additional_examiner_user_ids.required' => 'Minimal satu penguji tambahan wajib dipilih.',
            'additional_examiner_user_ids.min' => 'Minimal satu penguji tambahan wajib dipilih.',
            'additional_examiner_user_ids.*.exists' => 'Penguji tambahan tidak valid.',
            'additional_examiner_user_ids.*.distinct' => 'Penguji tambahan tidak boleh duplikat.',
        ];
    }
}
