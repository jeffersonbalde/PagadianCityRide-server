<?php

namespace App\Http\Requests\Api\V1;

use App\Models\EmergencyContact;
use App\Rules\PhilippineMobileNumber;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreEmergencyContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:2', 'max:60',
                // Checked with the first rule, so the person is told about the limit before anything else.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->user()->emergencyContacts()->count() >= EmergencyContact::MAX_PER_USER) {
                        $fail('You can have at most '.EmergencyContact::MAX_PER_USER.' emergency contacts.');
                    }
                },
            ],
            'relationship' => ['nullable', 'string', 'max:30'],
            'phone' => ['required', 'string', new PhilippineMobileNumber],
        ];
    }

    /**
     * The phone number in the +639XXXXXXXXX format.
     */
    public function phoneNumber(): string
    {
        return PhoneNumber::normalize($this->validated('phone'));
    }

    /**
     * Stop a number that is already in the list.
     */
    protected function passedValidation(): void
    {
        $exists = $this->user()->emergencyContacts()->where('phone', $this->phoneNumber())->exists();

        if ($exists) {
            throw ValidationException::withMessages(['phone' => ['This number is already in your list.']]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us the name of your contact.',
            'name.min' => 'The name needs at least 2 letters.',
            'phone.required' => 'Please enter the mobile number of your contact.',
        ];
    }
}
