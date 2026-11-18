<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    /**
     * Only drivers reach this route, and the controller checks they are online.
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
            'latitude' => ['required', 'numeric', 'between:4,22'],
            'longitude' => ['required', 'numeric', 'between:116,127'],
        ];
    }
}
