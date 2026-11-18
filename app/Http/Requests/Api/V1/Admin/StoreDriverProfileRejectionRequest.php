<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverProfileRejectionRequest extends FormRequest
{
    /**
     * Only admins reach this route, and the policy decides if the application
     * can be reviewed.
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
            'reason' => ['required', 'string', 'max:500'],
            'document_types' => ['nullable', 'array'],
            'document_types.*' => ['distinct', Rule::enum(DocumentType::class)],
        ];
    }

    /**
     * The documents the driver must replace.
     *
     * @return list<DocumentType>
     */
    public function documentTypes(): array
    {
        return array_map(
            fn (string $type): DocumentType => DocumentType::from($type),
            $this->validated('document_types') ?? [],
        );
    }
}
