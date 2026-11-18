<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilityRequest extends FormRequest
{
    /**
     * Only drivers reach this route, and the controller checks the approval.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. A driver who goes
     * online must tell where the tricycle is.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'is_online' => ['required', 'boolean'],
            'latitude' => ['required_if:is_online,true', 'nullable', 'numeric', 'between:4,22'],
            'longitude' => ['required_if:is_online,true', 'nullable', 'numeric', 'between:116,127'],
        ];
    }
}
