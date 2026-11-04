<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupervisedBiometricEnrollmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $image = ['required', 'file', 'image', 'mimes:jpg,jpeg,webp', 'max:5120'];

        return [
            'front' => $image,
            'left' => $image,
            'right' => $image,
            'consentAccepted' => ['sometimes', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return ['front.required' => 'Capture the front face photo.', 'left.required' => 'Capture the slight-left photo.', 'right.required' => 'Capture the slight-right photo.'];
    }
}
