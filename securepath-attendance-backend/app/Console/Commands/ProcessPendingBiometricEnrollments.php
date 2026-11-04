<?php

namespace App\Console\Commands;

use App\Contracts\FaceAiServiceInterface;
use App\Enums\BiometricStatus;
use App\Enums\EnrollmentStatus;
use App\Models\BiometricEnrollmentSession;
use App\Models\BiometricProfile;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessPendingBiometricEnrollments extends Command
{
    protected $signature = 'biometrics:process-pending {personCode? : Process only this employee code}';

    protected $description = 'Retry pending or failed biometric enrollment sessions using their stored samples';

    public function handle(FaceAiServiceInterface $ai, AuditService $audit): int
    {
        if (! $ai->isConfigured()) {
            $this->error('The face AI service is not configured.');

            return self::FAILURE;
        }

        $query = BiometricEnrollmentSession::query()
            ->with(['person', 'samples'])
            ->whereIn('status', [EnrollmentStatus::Pending, EnrollmentStatus::Failed])
            ->latest();

        if ($personCode = $this->argument('personCode')) {
            $query->whereHas('person', fn ($person) => $person->where('person_code', $personCode));
        }

        $sessions = $query->get()->unique('person_id');
        if ($sessions->isEmpty()) {
            $this->info('No pending biometric enrollments were found.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($sessions as $session) {
            $person = $session->person;
            $samples = $session->samples->keyBy('pose');
            $paths = collect(['front', 'left', 'right'])
                ->mapWithKeys(fn (string $pose) => [$pose => $samples->get($pose)?->image_path])
                ->all();

            if (in_array(null, $paths, true)) {
                $this->warn("{$person->person_code}: all three enrollment samples are required.");
                $failed++;
                continue;
            }

            $session->update(['status' => EnrollmentStatus::Processing, 'failure_reason' => null]);

            try {
                $result = $ai->enroll($person, $paths);
                DB::transaction(function () use ($person, $session, $result, $audit): void {
                    $profile = BiometricProfile::where('person_id', $person->id)->lockForUpdate()->first()
                        ?? new BiometricProfile(['person_id' => $person->id]);
                    $profile->fill([
                        'status' => BiometricStatus::Enrolled,
                        'embedding' => $result['embedding'],
                        'embedding_dimension' => $result['embeddingDimension'],
                        'model_version' => $result['modelVersion'],
                        'quality_score' => $result['qualityScore'],
                        'enrolled_at' => now(),
                        'revoked_at' => null,
                        'last_updated_at' => now(),
                        'consent_status' => 'granted',
                        'consent_at' => $profile->consent_at ?? now(),
                    ])->save();
                    $session->update([
                        'status' => EnrollmentStatus::Completed,
                        'model_version' => $result['modelVersion'],
                        'quality_score' => $result['qualityScore'],
                        'completed_at' => now(),
                    ]);
                    $session->samples()->update(['biometric_profile_id' => $profile->id]);
                    $person->update(['face_registered' => true, 'biometric_consent' => true]);
                    $audit->record('biometric.enrollment_retried', $person, newValues: [
                        'sessionId' => $session->uuid,
                        'modelVersion' => $result['modelVersion'],
                    ], userId: $session->requested_by);
                });
                $this->info("{$person->person_code}: enrollment completed.");
            } catch (Throwable $exception) {
                $session->update(['status' => EnrollmentStatus::Failed, 'failure_reason' => $exception->getMessage()]);
                $this->error("{$person->person_code}: {$exception->getMessage()}");
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
