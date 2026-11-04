<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KioskAttendanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'operation' => ['required', 'in:check_in,check_out'],
            'eventUuid' => ['required', 'uuid'],
            'occurredAt' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:150'],
        ];
    }
}
