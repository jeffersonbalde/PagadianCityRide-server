<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Rules\PhilippineMobileNumber;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoginCodeRequest extends FormRequest
{
    /**
     * Anyone may ask for a code. The route is rate limited instead.
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
        ];
    }

    /**
     * The phone number in the +639XXXXXXXXX format.
     */
    public function phoneNumber(): string
    {
        return PhoneNumber::normalize($this->validated('phone'));
    }
}
