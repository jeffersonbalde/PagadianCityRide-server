<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\ReportStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRideReportResolutionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An open report can only be closed: resolved (the admins acted) or dismissed (nothing to act on).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ReportStatus::Resolved->value, ReportStatus::Dismissed->value])],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    public function status(): ReportStatus
    {
        return ReportStatus::from($this->validated('status'));
    }
}
