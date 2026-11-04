<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sortBy' => ['nullable', 'string', 'max:64'],
            'sortOrder' => ['nullable', 'in:asc,desc'],
            'status' => ['nullable', 'string', 'max:64'],
            'date' => ['nullable', 'date'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'departmentId' => ['nullable', 'string', 'max:64'],
            'roleId' => ['nullable', 'string', 'max:64'],
            'personId' => ['nullable', 'string', 'max:64'],
            'location' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:64'],
            'severity' => ['nullable', 'string', 'max:64'],
            'faceRegistered' => ['nullable', 'boolean'],
            'biometricStatus' => ['nullable', 'in:not_registered,pending,processing,enrolled,failed,registered,revoked,expired'],
            'attendanceStatus' => ['nullable', 'string', 'max:64'],
            'verificationMethod' => ['nullable', 'string', 'max:64'],
            'recognitionStatus' => ['nullable', 'string', 'max:64'],
            'verificationStatus' => ['nullable', 'string', 'max:64'],
            'minConfidence' => ['nullable', 'numeric', 'between:0,1'],
        ];
    }
}
