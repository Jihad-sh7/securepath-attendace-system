<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRecognitionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['personId' => ['required', 'exists:people,person_code'], 'note' => ['nullable', 'string', 'max:2000']]; }
}

