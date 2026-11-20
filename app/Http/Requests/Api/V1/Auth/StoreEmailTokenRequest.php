<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreEmailTokenRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
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
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address, for example name@gmail.com.',
            'code.digits' => 'The code has 6 digits.',
        ];
    }

    /**
     * The email address in lower case, without spaces around it.
     */
    public function emailAddress(): string
    {
        return Str::lower(trim($this->validated('email')));
    }

    /**
     * The role a new person signs up with. Passenger when none is given.
     */
    public function signUpRole(): UserRole
    {
        return $this->enum('role', UserRole::class) ?? UserRole::Passenger;
    }
}
