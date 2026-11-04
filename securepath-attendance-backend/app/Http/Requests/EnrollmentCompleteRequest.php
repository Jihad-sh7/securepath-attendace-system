<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrollmentCompleteRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['secureReference' => ['required', 'string', 'max:1000'], 'modelVersion' => ['required', 'string', 'max:100'], 'metadata' => ['nullable', 'array']]; }
}

