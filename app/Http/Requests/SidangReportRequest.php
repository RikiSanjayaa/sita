<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\ExcludeIf;

class SidangReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'admin', 'kaprodi']) ?? false;
    }

    /**
     * @return array<string, list<string|ExcludeIf>>
     */
    public function rules(): array
    {
        $storedPeriod = Rule::excludeIf($this->filled('period_id'));

        return [
            'period_id' => ['nullable', 'integer', 'min:1'],
            'from' => [$storedPeriod, 'nullable', 'required_with:until', 'date_format:Y-m-d'],
            'until' => [$storedPeriod, 'nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'period' => [$storedPeriod, 'nullable', 'string', 'max:80'],
            'selection' => ['nullable', 'uuid'],
            'ids' => ['nullable', 'string', 'max:500000', 'regex:/^\\d+(,\\d+)*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.required_with' => 'Isi tanggal awal rekap.',
            'until.required_with' => 'Isi tanggal akhir rekap.',
            'from.date_format' => 'Tanggal awal rekap tidak valid.',
            'until.date_format' => 'Tanggal akhir rekap tidak valid.',
            'until.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
        ];
    }
}
