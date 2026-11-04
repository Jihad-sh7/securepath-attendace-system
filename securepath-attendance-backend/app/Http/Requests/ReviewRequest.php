<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['note' => ['nullable', 'string', 'max:2000'], 'reviewNote' => ['nullable', 'string', 'max:2000']]; }
}

