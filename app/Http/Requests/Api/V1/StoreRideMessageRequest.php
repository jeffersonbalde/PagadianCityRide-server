<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RideMessage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRideMessageRequest extends FormRequest
{
    /**
     * The policy decides if this person may write in the chat of the ride.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Spaces around the message do not count, and a message of only spaces is no message.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body')) === '' ? null : trim($this->input('body'))]);
        }
    }

    /**
     * A message has words, a photo, or both.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:image', 'string', 'max:'.RideMessage::MAX_LENGTH],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
            'body.required_without' => 'Write a message or choose a photo.',
            'image.image' => 'The file must be a photo.',
            'image.mimes' => 'The photo must be a JPG, PNG or WEBP file.',
            'image.max' => 'The photo is too big. It can be up to 5 MB.',
        ];
    }
}
