<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    private ?array $resolved = null;

    public const DEFINITIONS = [
        'organizationName' => ['group' => 'general', 'type' => 'string', 'default' => 'SecurePath Vision'],
        'timeZone' => ['group' => 'general', 'type' => 'string', 'default' => 'Asia/Damascus'],
        'dateFormat' => ['group' => 'general', 'type' => 'string', 'default' => 'YYYY-MM-DD'],
        'gracePeriod' => ['group' => 'attendance', 'type' => 'integer', 'default' => 10],
        'duplicateRecognitionWindowSeconds' => ['group' => 'attendance', 'type' => 'integer', 'default' => 60],
        'absenceRule' => ['group' => 'attendance', 'type' => 'string', 'default' => 'Mark absent after 2 hours'],
        'confidenceThreshold' => ['group' => 'biometrics', 'type' => 'float', 'default' => 0.85],
        'manualReviewThreshold' => ['group' => 'biometrics', 'type' => 'float', 'default' => 0.75],
        'livenessDetection' => ['group' => 'biometrics', 'type' => 'boolean', 'default' => true],
        'unknownFaceHandling' => ['group' => 'biometrics', 'type' => 'string', 'default' => 'Require manual review'],
        'retentionPeriod' => ['group' => 'privacy', 'type' => 'integer', 'default' => 24],
        'sessionTimeout' => ['group' => 'security', 'type' => 'integer', 'default' => 30],
        'passwordPolicy' => ['group' => 'security', 'type' => 'string', 'default' => '12 chars, mixed case, number, symbol'],
        'emailAlerts' => ['group' => 'notifications', 'type' => 'boolean', 'default' => true],
        'webhookUrl' => ['group' => 'integrations', 'type' => 'string', 'default' => ''],
    ];

    public function all(): array
    {
        if ($this->resolved !== null) return $this->resolved;
        $stored = Setting::all()->keyBy('key');
        return $this->resolved = collect(self::DEFINITIONS)->mapWithKeys(fn (array $definition, string $key) => [$key => $stored->get($key)?->typedValue() ?? self::defaultFor($key)])->all();
    }

    public static function defaultFor(string $key): mixed
    {
        return match ($key) {
            'duplicateRecognitionWindowSeconds' => config('attendance.duplicate_recognition_window_seconds'),
            'confidenceThreshold' => config('attendance.minimum_confidence'),
            'manualReviewThreshold' => config('attendance.review_threshold'),
            default => self::DEFINITIONS[$key]['default'] ?? null,
        };
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function update(array $settings, int $userId): array
    {
        return DB::transaction(function () use ($settings, $userId): array {
            foreach ($settings as $key => $value) {
                if (! isset(self::DEFINITIONS[$key])) {
                    continue;
                }
                $definition = self::DEFINITIONS[$key];
                Setting::updateOrCreate(['key' => $key], ['group' => $definition['group'], 'type' => $definition['type'], 'value' => $definition['type'] === 'boolean' ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value), 'updated_by' => $userId]);
            }
            app(AuditService::class)->record('settings.updated', 'settings', newValues: array_keys($settings), userId: $userId);
            $this->resolved = null;
            return $this->all();
        });
    }
}
