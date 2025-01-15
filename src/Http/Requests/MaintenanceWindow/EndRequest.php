<?php

namespace Ajz\Anthropic\Http\Requests\MaintenanceWindow;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class EndRequest extends FormRequest
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
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
            'completion_status' => [
                'required',
                'string',
                'in:completed,partially_completed,cancelled',
            ],
            'completion_notes' => [
                'required_if:completion_status,partially_completed',
                'string',
                'min:10',
                'max:1000',
            ],
            'remaining_tasks' => [
                'required_if:completion_status,partially_completed',
                'array',
                'min:1',
            ],
            'remaining_tasks.*' => [
                'required',
                'string',
                'min:5',
                'max:200',
            ],
            'follow_up_required' => [
                'required_if:completion_status,partially_completed',
                'boolean',
            ],
            'follow_up_date' => [
                'required_if:follow_up_required,true',
                'date',
                'after:now',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'reason.min' => 'Please provide a detailed reason for ending the maintenance window early',
            'completion_status.in' => 'Invalid completion status. Must be one of: completed, partially_completed, cancelled',
            'completion_notes.required_if' => 'Please provide completion notes when status is partially completed',
            'remaining_tasks.required_if' => 'Please list remaining tasks when status is partially completed',
            'remaining_tasks.*.min' => 'Each remaining task description must be at least 5 characters',
            'follow_up_date.required_if' => 'Please specify a follow-up date when follow-up is required',
            'follow_up_date.after' => 'Follow-up date must be in the future',
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
            $this->validateMinimumDuration($validator);
        });
    }

    /**
     * Validate that the window can be ended based on its status.
     */
    protected function validateWindowStatus($validator): void
    {
        $window = $this->route('window');

        if ($window->status !== 'active') {
            $validator->errors()->add(
                'window',
                'Only active maintenance windows can be ended'
            );
        }

        if ($window->end_time->isPast()) {
            $validator->errors()->add(
                'window',
                'Cannot end a maintenance window that has already ended'
            );
        }
    }

    /**
     * Validate that the window has run for a minimum duration.
     */
    protected function validateMinimumDuration($validator): void
    {
        $window = $this->route('window');
        $minimumDuration = 15; // 15 minutes minimum

        $actualDuration = Carbon::now()->diffInMinutes($window->start_time);

        if ($actualDuration < $minimumDuration) {
            $validator->errors()->add(
                'duration',
                "Maintenance window must run for at least {$minimumDuration} minutes before it can be ended early"
            );
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('follow_up_date') && is_string($this->follow_up_date)) {
            $this->merge([
                'follow_up_date' => Carbon::parse($this->follow_up_date),
            ]);
        }

        // Convert string boolean to actual boolean
        if ($this->has('follow_up_required')) {
            $this->merge([
                'follow_up_required' => filter_var($this->follow_up_required, FILTER_VALIDATE_BOOLEAN),
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
            'ended_at' => Carbon::now(),
            'actual_duration' => Carbon::now()->diffInMinutes($this->route('window')->start_time),
        ]);
    }
}
