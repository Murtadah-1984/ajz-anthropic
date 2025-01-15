<?php

namespace Ajz\Anthropic\Http\Requests\MaintenanceWindow;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class ExtendRequest extends FormRequest
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
            'duration' => [
                'required',
                'integer',
                'min:1',
                'max:24', // Max extension of 24 hours
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'duration.min' => 'Extension duration must be at least 1 hour',
            'duration.max' => 'Extension cannot exceed 24 hours',
            'reason.min' => 'Please provide a detailed reason for the extension',
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
            $this->validateTotalDuration($validator);
            $this->validateTimeConflicts($validator);
        });
    }

    /**
     * Validate that the window can be extended based on its status.
     */
    protected function validateWindowStatus($validator): void
    {
        $window = $this->route('window');

        if ($window->status !== 'active') {
            $validator->errors()->add(
                'window',
                'Only active maintenance windows can be extended'
            );
        }

        if ($window->end_time->isPast()) {
            $validator->errors()->add(
                'window',
                'Cannot extend a maintenance window that has already ended'
            );
        }
    }

    /**
     * Validate that the total duration doesn't exceed maximum allowed.
     */
    protected function validateTotalDuration($validator): void
    {
        $window = $this->route('window');
        $totalDuration = $window->duration + $this->input('duration');

        if ($totalDuration > 96) { // Max total duration of 96 hours (4 days)
            $validator->errors()->add(
                'duration',
                'Total maintenance window duration cannot exceed 96 hours'
            );
        }
    }

    /**
     * Validate that the extension doesn't conflict with other windows.
     */
    protected function validateTimeConflicts($validator): void
    {
        $window = $this->route('window');
        $newEndTime = $window->end_time->addHours($this->input('duration'));

        $conflicts = $window->newQuery()
            ->where('id', '!=', $window->id)
            ->where('environment', $window->environment)
            ->where('start_time', '<', $newEndTime)
            ->where('end_time', '>', $window->end_time)
            ->exists();

        if ($conflicts) {
            $validator->errors()->add(
                'time_conflict',
                'The extension would conflict with another scheduled maintenance window'
            );
        }
    }

    /**
     * Get data to be validated from the request.
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'user_id' => $this->user()->id,
            'extended_at' => Carbon::now(),
        ]);
    }
}
