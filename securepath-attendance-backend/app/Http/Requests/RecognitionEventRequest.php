<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecognitionEventRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['eventUuid' => ['required', 'uuid'], 'personId' => ['nullable', 'exists:people,person_code'], 'confidence' => ['nullable', 'numeric', 'between:0,1'], 'livenessPassed' => ['nullable', 'boolean'], 'livenessResult' => ['nullable', 'in:passed,failed,inconclusive,not_provided'], 'occurredAt' => ['required', 'date'], 'deviceIdentifier' => ['required', 'string', 'max:100'], 'location' => ['nullable', 'string', 'max:150'], 'metadata' => ['nullable', 'array']];
    }
}

