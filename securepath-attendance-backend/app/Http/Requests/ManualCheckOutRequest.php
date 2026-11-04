<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualCheckOutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'personId' => ['required'],
            'checkOutAt' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

