<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverProfileRequest extends FormRequest
{
    /**
     * Only drivers reach this route, and the policy decides if the application
     * can still be changed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tidy up the numbers before they are checked, so "abc  1234" and
     * "ABC 1234" are the same plate.
     */
    protected function prepareForValidation(): void
    {
        $tidy = fn (mixed $value): mixed => is_string($value)
            ? strtoupper(trim(preg_replace('/\s+/', ' ', $value)))
            : $value;

        $this->merge([
            'tricycle_number' => $tidy($this->input('tricycle_number')),
            'plate_number' => $tidy($this->input('plate_number')),
            'franchise_number' => $tidy($this->input('franchise_number')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $ownProfileId = $this->user()->driverProfile?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'tricycle_number' => ['required', 'string', 'max:20'],
            'plate_number' => ['required', 'string', 'max:20', Rule::unique('driver_profiles')->ignore($ownProfileId)],
            'franchise_number' => ['required', 'string', 'max:30', Rule::unique('driver_profiles')->ignore($ownProfileId)],
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
            'plate_number.unique' => 'This plate number is already registered.',
            'franchise_number.unique' => 'This franchise number is already registered.',
        ];
    }
}
