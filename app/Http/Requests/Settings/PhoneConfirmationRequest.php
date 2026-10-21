<?php

namespace App\Http\Requests\Settings;

use App\Rules\IndonesianPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class PhoneConfirmationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalize the phone number so only the canonical 08 form is stored.
     */
    protected function prepareForValidation(): void
    {
        $phoneNumber = $this->input('phone_number');

        if (is_string($phoneNumber)) {
            $this->merge(['phone_number' => PhoneNumber::normalize($phoneNumber)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', new IndonesianPhoneNumber],
        ];
    }
}
