<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverDocumentRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
            'file.required' => 'Take or choose a photo of the document.',
            'file.image' => 'The document must be a photo.',
            'file.mimes' => 'The photo must be a JPG, PNG or WEBP file.',
            'file.max' => 'The photo is too big. It can be up to 5 MB.',
        ];
    }
}
