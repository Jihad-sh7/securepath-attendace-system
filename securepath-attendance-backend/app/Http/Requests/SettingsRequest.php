<?php

namespace App\Http\Requests;

use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.organizationName' => ['sometimes', 'string', 'max:150'],
            'settings.timeZone' => ['sometimes', 'timezone'],
            'settings.dateFormat' => ['sometimes', Rule::in(['YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY'])],
            'settings.gracePeriod' => ['sometimes', 'integer', 'between:0,240'],
            'settings.duplicateRecognitionWindowSeconds' => ['sometimes', 'integer', 'between:1,3600'],
            'settings.absenceRule' => ['sometimes', 'string', 'max:500'],
            'settings.confidenceThreshold' => ['sometimes', 'numeric', 'between:0,1'],
            'settings.manualReviewThreshold' => ['sometimes', 'numeric', 'between:0,1'],
            'settings.livenessDetection' => ['sometimes', 'boolean'],
            'settings.unknownFaceHandling' => ['sometimes', 'string', 'max:200'],
            'settings.retentionPeriod' => ['sometimes', 'integer', 'between:1,120'],
            'settings.sessionTimeout' => ['sometimes', 'integer', 'between:5,1440'],
            'settings.passwordPolicy' => ['sometimes', 'string', 'max:500'],
            'settings.emailAlerts' => ['sometimes', 'boolean'],
            'settings.webhookUrl' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $submitted = $this->input('settings', []);
            if (! is_array($submitted)) return;

            foreach (array_diff(array_keys($submitted), array_keys(SettingsService::DEFINITIONS)) as $unknown) {
                $validator->errors()->add('settings.'.$unknown, 'This setting is not supported.');
            }

            $settings = array_merge(app(SettingsService::class)->all(), $submitted);
            if ((float) $settings['manualReviewThreshold'] > (float) $settings['confidenceThreshold']) {
                $validator->errors()->add('settings.manualReviewThreshold', 'The manual review threshold must not exceed the confidence threshold.');
            }
        });
    }
}
