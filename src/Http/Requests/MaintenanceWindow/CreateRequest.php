<?php

namespace Ajz\Anthropic\Http\Requests\MaintenanceWindow;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class CreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-maintenance-windows');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'environment' => ['required', 'string', 'in:prod,staging,dev,test'],
            'start_time' => [
                'required',
                'date',
                'after_or_equal:' . Carbon::now()->toDateTimeString(),
            ],
            'duration' => ['required', 'integer', 'min:1', 'max:72'],
            'comment' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'environment.in' => 'The environment must be one of: prod, staging, dev, test',
            'start_time.after_or_equal' => 'The start time must be in the future',
            'duration.min' => 'The duration must be at least 1 hour',
            'duration.max' => 'The duration cannot exceed 72 hours',
            'comment.min' => 'Please provide a more detailed comment (minimum 10 characters)',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('start_time') && is_string($this->start_time)) {
            $this->merge([
                'start_time' => Carbon::parse($this->start_time),
            ]);
        }
    }

    /**
     * Get data to be validated from the request.
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'user_id' => $this->user()->id,
        ]);
    }
}
