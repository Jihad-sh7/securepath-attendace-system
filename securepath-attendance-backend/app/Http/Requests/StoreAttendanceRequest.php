<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $date = $this->input('date');
        $values = [];

        foreach (['checkIn', 'checkOut'] as $field) {
            $value = $this->input($field);
            if (is_string($date) && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
                $values[$field] = $date.' '.$value;
            }
        }

        if ($this->filled('status')) $values['status'] = strtolower(str_replace(' ', '_', (string) $this->input('status')));
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'personId' => ['required', 'exists:people,person_code'], 'date' => ['required', 'date'], 'checkIn' => ['nullable', 'date'], 'checkOut' => ['nullable', 'date', 'after:checkIn'],
            'status' => ['nullable', Rule::in(['present', 'late', 'absent', 'on_leave', 'excused', 'early_departure', 'missing_checkout', 'review'])],
            'notes' => ['nullable', 'string', 'max:2000'], 'reason' => ['nullable', 'string', 'max:1000'], 'location' => ['nullable', 'string', 'max:150'],
        ];
    }
}
