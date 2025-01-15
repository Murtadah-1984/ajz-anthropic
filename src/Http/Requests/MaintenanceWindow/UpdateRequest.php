<?php

namespace Ajz\Anthropic\Http\Requests\MaintenanceWindow;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class UpdateRequest extends FormRequest
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
            'start_time' => [
                'sometimes',
                'date',
                'after_or_equal:' . Carbon::now()->toDateTimeString(),
            ],
            'duration' => ['sometimes', 'integer', 'min:1', 'max:72'],
            'comment' => ['sometimes', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'start_time.after_or_equal' => 'The start time must be in the future',
            'duration.min' => 'The duration must be at least 1 hour',
            'duration.max' => 'The duration cannot exceed 72 hours',
            'comment.min' => 'Please provide a more detailed comment (minimum 10 characters)',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateWindowStatus($validator);
            $this->validateTimeConflicts($validator);
        });
    }

    /**
     * Validate that the window can be updated based on its status.
     */
    protected function validateWindowStatus($validator): void
    {
        $window = $this->route('window');

        if ($window->status === 'expired') {
            $validator->errors()->add('window', 'Cannot update an expired maintenance window');
        }

        if ($window->status === 'active' && $this->has('start_time')) {
            $validator->errors()->add('start_time', 'Cannot modify start time of an active maintenance window');
        }
    }

    /**
     * Validate that the new time doesn't conflict with other windows.
     */
    protected function validateTimeConflicts($validator): void
    {
        if (!$this->has('start_time') && !$this->has('duration')) {
            return;
        }

        $window = $this->route('window');
        $startTime = $this->get('start_time', $window->start_time);
        $duration = $this->get('duration', $window->duration);
        $endTime = Carbon::parse($startTime)->addHours($duration);

        $conflicts = $window->newQuery()
            ->where('id', '!=', $window->id)
            ->where('environment', $window->environment)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime]);
            })
            ->exists();

        if ($conflicts) {
            $validator->errors()->add(
                'time_conflict',
                'The specified time window conflicts with another maintenance window'
            );
        }
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
            'updated_at' => Carbon::now(),
        ]);
    }
}
