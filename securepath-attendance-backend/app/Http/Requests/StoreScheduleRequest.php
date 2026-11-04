<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['type', 'status'] as $field) if ($this->filled($field)) $values[$field] = strtolower((string) $this->input($field));
        $this->merge($values);
    }
    public function rules(): array
    {
        $schedule = $this->route('schedule');
        return [
            'name' => [$schedule ? 'sometimes' : 'required', 'string', 'max:150'], 'code' => [$schedule ? 'sometimes' : 'required', 'string', 'max:32', Rule::unique('schedules', 'code')->ignore($schedule?->id)],
            'type' => ['nullable', Rule::in(['fixed', 'shift', 'flexible'])], 'startTime' => [$schedule ? 'sometimes' : 'required', 'date_format:H:i'], 'endTime' => [$schedule ? 'sometimes' : 'required', 'date_format:H:i', 'different:startTime'],
            'gracePeriodMinutes' => ['nullable', 'integer', 'min:0', 'max:240'], 'breakDurationMinutes' => ['nullable', 'integer', 'min:0', 'max:480'], 'breakWindow' => ['nullable', 'string', 'max:100'],
            'effectiveFrom' => ['nullable', 'date'], 'effectiveUntil' => ['nullable', 'date', 'after_or_equal:effectiveFrom'], 'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'days' => ['nullable', 'array'], 'days.*.dayOfWeek' => ['required', 'integer', 'between:1,7'], 'days.*.isWorkingDay' => ['required', 'boolean'],
            'personIds' => ['nullable', 'array'], 'personIds.*' => ['exists:people,person_code'], 'departmentIds' => ['nullable', 'array'], 'departmentIds.*' => ['exists:departments,code'],
        ];
    }
}
