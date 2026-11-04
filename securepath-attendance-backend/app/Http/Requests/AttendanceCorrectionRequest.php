<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['requestedValues' => ['required', 'array', 'min:1'], 'requestedValues.checkInAt' => ['nullable', 'date'], 'requestedValues.checkOutAt' => ['nullable', 'date'], 'requestedValues.status' => ['nullable', 'string'], 'requestedValues.notes' => ['nullable', 'string', 'max:2000'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]; }
}

