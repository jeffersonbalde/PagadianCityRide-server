<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRideRequest extends FormRequest
{
    /**
     * Only passengers reach this route, and the action refuses a second ride.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. The coordinates must
     * be inside the Philippines, which stops garbage and typos.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'pickup' => ['required', 'array'],
            'pickup.latitude' => ['required', 'numeric', 'between:4,22'],
            'pickup.longitude' => ['required', 'numeric', 'between:116,127'],
            'pickup.label' => ['nullable', 'string', 'max:255'],
            'destination' => ['required', 'array'],
            'destination.latitude' => ['required', 'numeric', 'between:4,22'],
            'destination.longitude' => ['required', 'numeric', 'between:116,127'],
            'destination.label' => ['required', 'string', 'max:255'],
            'passengers_count' => ['required', 'integer', 'between:1,'.config('rides.max_passengers')],
            'note' => ['nullable', 'string', 'max:120'],
            'distance_meters' => ['nullable', 'integer', 'between:0,200000'],
            'duration_seconds' => ['nullable', 'integer', 'between:0,86400'],
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
            'pickup.latitude.between' => 'The pick-up point must be in the Philippines.',
            'pickup.longitude.between' => 'The pick-up point must be in the Philippines.',
            'destination.latitude.between' => 'The destination must be in the Philippines.',
            'destination.longitude.between' => 'The destination must be in the Philippines.',
            'destination.label.required' => 'Choose where you want to go.',
            'passengers_count.between' => 'A tricycle carries 1 to '.config('rides.max_passengers').' people.',
        ];
    }
}
