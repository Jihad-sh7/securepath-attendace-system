<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualCheckInRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'personId' => ['required'],
            'checkInAt' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

