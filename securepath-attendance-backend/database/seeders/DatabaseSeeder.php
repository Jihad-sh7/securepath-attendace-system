<?php

namespace Database\Seeders;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\BiometricStatus;
use App\Enums\LivenessResult;
use App\Enums\RecognitionStatus;
use App\Enums\UserRole;
use App\Enums\VerificationMethod;
use App\Enums\VerificationStatus;
use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\BiometricProfile;
use App\Models\Department;
use App\Models\DepartmentRoleRate;
use App\Models\MobileDevice;
use App\Models\Permission;
use App\Models\Person;
use App\Models\RecognitionEvent;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemActionNotification;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(PermissionSeeder::class);

            $superAdmin = User::updateOrCreate(['email' => 'superadmin@attendance.local'], ['name' => 'Mahmoud Mohammed', 'phone' => '+963 11 555 0101', 'password' => 'SuperAdmin123!', 'role' => UserRole::SuperAdmin, 'status' => 'active', 'title' => 'Super Admin', 'email_verified_at' => now(), 'two_factor_enabled' => true]);
            $admin = User::updateOrCreate(['email' => 'admin@attendance.local'], ['name' => 'Nadia Brooks', 'phone' => '+963 11 555 0171', 'password' => 'Admin123!', 'role' => UserRole::Admin, 'status' => 'active', 'title' => 'Admin', 'email_verified_at' => now(), 'two_factor_enabled' => true]);
            $admin->permissions()->sync(Permission::whereIn('name', ['dashboard.view', 'people.view', 'people.create', 'people.edit', 'attendance.view', 'attendance.edit', 'reports.view'])->pluck('id'));

            $departments = collect([
                ['code' => 'DEP-ENG', 'name' => 'Engineering'], ['code' => 'DEP-OPS', 'name' => 'Operations'], ['code' => 'DEP-HR', 'name' => 'Human Resources'], ['code' => 'DEP-SALES', 'name' => 'Sales'], ['code' => 'DEP-MKT', 'name' => 'Marketing'],
            ])->map(fn (array $data) => Department::updateOrCreate(['code' => $data['code']], $data + ['description' => $data['name'].' department', 'status' => 'active', 'max_overtime_minutes' => 120, 'auto_checkout_enabled' => true]));

            $roleData = [
                'DEP-ENG' => ['code' => 'DEVELOPER', 'name' => 'Developer', 'rate' => '28.5000'],
                'DEP-OPS' => ['code' => 'OPERATIONS_SPECIALIST', 'name' => 'Operations Specialist', 'rate' => '22.0000'],
                'DEP-HR' => ['code' => 'HR_SPECIALIST', 'name' => 'HR Specialist', 'rate' => '24.0000'],
                'DEP-SALES' => ['code' => 'SALES_REPRESENTATIVE', 'name' => 'Sales Representative', 'rate' => '20.5000'],
                'DEP-MKT' => ['code' => 'MARKETING_SPECIALIST', 'name' => 'Marketing Specialist', 'rate' => '23.7500'],
            ];
            $roles = collect($roleData)->map(fn (array $data) => Role::updateOrCreate(['code' => $data['code']], ['name' => $data['name'], 'status' => 'active']));
            foreach ($departments as $department) {
                $data = $roleData[$department->code];
                DepartmentRoleRate::updateOrCreate(['department_id' => $department->id, 'role_id' => $roles[$department->code]->id, 'effective_from' => today()->subYears(5)->startOfYear()->toDateString()], ['hourly_rate' => $data['rate'], 'currency' => 'USD', 'effective_until' => null, 'created_by' => $superAdmin->id]);
            }

            $schedules = collect([
                ['code' => 'SCH-001', 'name' => 'Core Office Shift', 'type' => 'fixed', 'start_time' => '08:30', 'end_time' => '17:00', 'grace_period_minutes' => 10, 'break_duration_minutes' => 45, 'break_window' => '12:30-13:15'],
                ['code' => 'SCH-002', 'name' => 'Facility Coverage', 'type' => 'shift', 'start_time' => '07:00', 'end_time' => '19:00', 'grace_period_minutes' => 5, 'break_duration_minutes' => 60, 'break_window' => 'Rotating'],
                ['code' => 'SCH-003', 'name' => 'Flexible Field Shift', 'type' => 'flexible', 'start_time' => '09:30', 'end_time' => '18:30', 'grace_period_minutes' => 20, 'break_duration_minutes' => 45, 'break_window' => 'Flexible'],
            ])->map(function (array $data) { $schedule = Schedule::updateOrCreate(['code' => $data['code']], $data + ['status' => 'active']); if ($schedule->days()->count() === 0) foreach (range(1, 7) as $day) $schedule->days()->create(['day_of_week' => $day, 'is_working_day' => $day <= 5]); return $schedule; });
            $schedules[0]->departments()->sync($departments->whereIn('code', ['DEP-ENG', 'DEP-HR', 'DEP-MKT'])->pluck('id'));
            $schedules[1]->departments()->sync($departments->where('code', 'DEP-OPS')->pluck('id'));
            $schedules[2]->departments()->sync($departments->where('code', 'DEP-SALES')->pluck('id'));

            $people = collect();
            foreach (range(1, 36) as $index) {
                $first = fake()->firstName(); $last = fake()->lastName(); $department = $departments[($index - 1) % $departments->count()];
                $role = $roles[$department->code];
                $person = Person::updateOrCreate(['person_code' => 'EMP-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT)], ['first_name' => $first, 'last_name' => $last, 'full_name' => "{$first} {$last}", 'email' => "employee{$index}@attendance.local", 'phone' => '+963 9'.str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'department_id' => $department->id, 'role_id' => $role->id, 'type' => $index % 9 === 0 ? 'contractor' : 'employee', 'job_title' => $role->name, 'status' => $index === 36 ? 'suspended' : 'active', 'face_registered' => $index % 7 !== 0, 'biometric_consent' => $index % 8 !== 0]);
                BiometricProfile::updateOrCreate(['person_id' => $person->id], ['status' => $person->face_registered ? BiometricStatus::Registered : BiometricStatus::Pending, 'enrolled_at' => $person->face_registered ? now()->subMonths(2) : null, 'last_updated_at' => now()->subDays($index), 'consent_status' => $person->biometric_consent ? 'granted' : 'pending', 'consent_at' => $person->biometric_consent ? now()->subMonths(3) : null, 'model_version' => $person->face_registered ? 'mobile-face-v1' : null]);
                $people->push($person);
            }

            $employee = User::updateOrCreate(['email' => 'employee@attendance.local'], ['person_id' => $people->first()->id, 'name' => $people->first()->full_name, 'password' => 'Employee123!', 'role' => UserRole::User, 'status' => 'active', 'title' => 'Employee', 'email_verified_at' => now()]);
            $employee->permissions()->sync(Permission::where('name', 'myAttendance.view')->pluck('id'));

            $attendanceCounter = 1;
            foreach (range(0, 13) as $dayOffset) {
                $date = today()->subDays($dayOffset); if ($date->isWeekend()) continue;
                foreach ($people as $index => $person) {
                    if (($index + $dayOffset) % 17 === 0) continue;
                    $late = ($index + $dayOffset) % 9 === 0; $checkIn = $date->copy()->setTime($late ? 9 : 8, $late ? 5 : 25 + ($index % 12)); $checkOut = $date->copy()->setTime(17, $index % 20);
                    AttendanceRecord::updateOrCreate(['person_id' => $person->id, 'attendance_date' => $date->toDateString()], ['event_code' => 'EVT-'.str_pad((string) $attendanceCounter++, 6, '0', STR_PAD_LEFT), 'scheduled_start' => '08:30', 'scheduled_end' => '17:00', 'check_in_at' => $checkIn, 'check_in_source' => 'face_recognition', 'check_out_at' => $checkOut, 'check_out_source' => 'face_recognition', 'status' => $late ? AttendanceStatus::Late : AttendanceStatus::Present, 'minutes_late' => $late ? 35 : 0, 'worked_minutes' => $checkIn->diffInMinutes($checkOut), 'verification_method' => VerificationMethod::FaceRecognition, 'recognition_confidence' => 0.90 + (($index % 9) / 100), 'liveness_result' => LivenessResult::Passed, 'source' => AttendanceSource::Mobile, 'source_identifier' => 'MOBILE-KIOSK-01', 'location' => 'Main Entrance']);
                }
            }

            foreach (range(1, 60) as $index) {
                $person = $people[($index - 1) % $people->count()]; $low = $index % 13 === 0; $unknown = $index % 19 === 0;
                RecognitionEvent::updateOrCreate(['event_uuid' => Str::uuid()->toString()], ['recognition_code' => 'REC-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT), 'person_id' => $unknown ? null : $person->id, 'confidence' => $unknown ? 0.12 : ($low ? 0.64 : 0.96), 'liveness_result' => $index % 23 === 0 ? LivenessResult::Failed : LivenessResult::Passed, 'recognition_status' => $unknown ? RecognitionStatus::Unknown : ($low ? RecognitionStatus::LowConfidence : RecognitionStatus::Matched), 'verification_status' => ($unknown || $low) ? VerificationStatus::Pending : VerificationStatus::Automatic, 'source_device_identifier' => 'MOBILE-KIOSK-01', 'location' => 'Main Entrance', 'occurred_at' => now()->subMinutes($index * 17), 'metadata' => ['seeded' => true]]);
            }

            foreach ([['unknown_face', 'critical'], ['low_confidence', 'warning'], ['repeated_absence', 'warning'], ['liveness_failure', 'critical']] as $index => [$type, $severity]) Alert::updateOrCreate(['alert_code' => 'ALT-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT)], ['type' => $type, 'severity' => AlertSeverity::from($severity), 'title' => str_replace('_', ' ', ucwords($type, '_')), 'message' => 'Seeded administrative alert for dashboard testing.', 'status' => $index === 3 ? AlertStatus::Resolved : AlertStatus::Open]);

            MobileDevice::updateOrCreate(['identifier' => 'MOBILE-KIOSK-01'], ['name' => 'Main Entrance Mobile Kiosk', 'secret' => 'KioskSecret123!', 'status' => 'active', 'metadata' => ['platform' => 'mobile']]);
            foreach (SettingsService::DEFINITIONS as $key => $definition) {
                $default = SettingsService::defaultFor($key);
                Setting::updateOrCreate(['key' => $key], ['group' => $definition['group'], 'type' => $definition['type'], 'value' => $definition['type'] === 'boolean' ? ($default ? '1' : '0') : (string) $default, 'updated_by' => $superAdmin->id]);
            }
            AuditLog::create(['user_id' => $superAdmin->id, 'action' => 'system.seeded', 'resource_type' => 'System', 'resource_id' => 'initial-data', 'new_values' => ['people' => $people->count()], 'result' => 'success']);
            $superAdmin->notifyNow(new SystemActionNotification('Backend ready', 'SecurePath sample data has been loaded.', 'success'));
            $admin->notifyNow(new SystemActionNotification('Welcome', 'Your limited Admin account is ready.', 'success'));
        });
    }
}
