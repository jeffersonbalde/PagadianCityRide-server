<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\PhilippineMobileNumber;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Write the phone number the way it is stored before it is checked, so the same number typed in
     * two ways is the same number.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone) && PhoneNumber::normalize($phone) !== null) {
            $this->merge(['phone' => PhoneNumber::normalize($phone)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'phone' => ['sometimes', 'required', 'string', new PhilippineMobileNumber, Rule::unique('users', 'phone')->ignore($this->user()?->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'name.min' => 'Your name needs at least 2 letters.',
            'name.max' => 'Your name can have at most 60 characters.',
            'phone.required' => 'Enter your mobile number.',
            'phone.unique' => 'This mobile number is already used by another account.',
        ];
    }
}
