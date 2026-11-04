<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $date = $this->route('attendance')?->attendance_date?->toDateString();
        $values = [];

        foreach (['checkInAt', 'checkOutAt'] as $field) {
            $value = $this->input($field);
            if ($date && is_string($value) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) $values[$field] = $date.' '.$value;
        }

        if ($this->filled('status')) $values['status'] = strtolower(str_replace(' ', '_', (string) $this->input('status')));
        $this->merge($values);
    }

    public function rules(): array { return ['checkInAt' => ['nullable', 'date'], 'checkOutAt' => ['nullable', 'date', 'after:checkInAt'], 'status' => ['nullable', 'in:present,late,absent,on_leave,excused,early_departure,missing_checkout,review'], 'notes' => ['nullable', 'string', 'max:2000'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]; }
}
