<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Rules\PhilippineMobileNumber;
use App\Support\PhoneNumber;
use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTokenRequest extends FormRequest
{
    /**
     * Anyone may try to sign in. The route is rate limited instead.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new PhilippineMobileNumber],
            'code' => ['required', 'digits:6'],
            'role' => ['nullable', Rule::enum(UserRole::class)->only(UserRole::selfAssignable())],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.digits' => 'The code has 6 digits.',
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
     * The role a new person signs up with. Passenger when none is given.
     */
    public function signUpRole(): UserRole
    {
        return $this->enum('role', UserRole::class) ?? UserRole::Passenger;
    }
}
