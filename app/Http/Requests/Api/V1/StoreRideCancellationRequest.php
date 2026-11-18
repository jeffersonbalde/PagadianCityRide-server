<?php

namespace App\Http\Requests\Api\V1;

use App\DriverCancelReason;
use App\Models\Ride;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRideCancellationRequest extends FormRequest
{
    /**
     * The policy decides if the person can cancel the ride.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. A driver must pick a
     * reason from the list, and write a few words when it is "other". A passenger may write a short reason.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        if ($this->isDriver()) {
            return [
                'reason' => ['required', Rule::enum(DriverCancelReason::class)],
                'note' => [Rule::requiredIf(fn (): bool => $this->input('reason') === DriverCancelReason::Other->value), 'nullable', 'string', 'max:200'],
            ];
        }

        return [
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Is the person who cancels the driver of this ride?
     */
    public function isDriver(): bool
    {
        /** @var Ride $ride */
        $ride = $this->route('ride');

        return $ride->driver_id !== null && $ride->driver_id === $this->user()->id;
    }
}
