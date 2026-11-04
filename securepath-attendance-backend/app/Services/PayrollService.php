<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Exceptions\ApiConflictException;
use App\Models\DepartmentRoleRate;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Person;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(private readonly WorkScheduleService $schedules, private readonly AuditService $audit) {}

    public function generate(int $year, int $month, User $actor): PayrollPeriod
    {
        $start = Carbon::createMidnightDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $currency = strtoupper((string) env('PAYROLL_CURRENCY', 'USD'));

        return DB::transaction(function () use ($year, $month, $start, $end, $currency, $actor): PayrollPeriod {
            $period = PayrollPeriod::where(['year' => $year, 'month' => $month])->lockForUpdate()->first();
            $period ??= PayrollPeriod::create(['year' => $year, 'month' => $month, 'starts_on' => $start, 'ends_on' => $end, 'status' => 'calculated', 'currency' => $currency, 'generated_by' => $actor->id]);
            $period->entries()->delete();

            $people = Person::withTrashed()->with(['department', 'role', 'attendanceRecords' => fn ($q) => $q->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])->orderBy('attendance_date')])
                ->where(function (Builder $query) use ($start, $end): void {
                    $query->where(fn (Builder $active) => $active->whereNull('deleted_at')->where('status', AccountStatus::Active->value))
                        ->orWhereHas('attendanceRecords', fn (Builder $attendance) => $attendance->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()]));
                })->orderBy('id')->get();

            foreach ($people as $person) $this->calculateEntry($period, $person, $start, $end, $currency);

            $period->update(['status' => 'calculated', 'currency' => $currency, 'calculated_at' => now(), 'generated_by' => $actor->id]);
            $this->audit->record('payroll.generated', $period, null, ['year' => $year, 'month' => $month, 'entries' => $people->count()], userId: $actor->id);
            return $period->fresh()->loadCount('entries');
        });
    }

    private function calculateEntry(PayrollPeriod $period, Person $person, Carbon $start, Carbon $end, string $currency): void
    {
        $issues = [];
        $breakdown = [];
        $regularMinutes = 0;
        $overtimeMinutes = 0;
        $regularCents = 0;
        $overtimeCents = 0;
        $ratesUsed = [];
        $canPay = true;

        if (! $person->department_id) { $issues[] = 'Missing department assignment.'; $canPay = false; }
        if (! $person->role_id) { $issues[] = 'Missing employee role.'; $canPay = false; }

        $rates = ($person->department_id && $person->role_id)
            ? DepartmentRoleRate::where('department_id', $person->department_id)->where('role_id', $person->role_id)
                ->whereDate('effective_from', '<=', $end)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $start))->orderBy('effective_from')->get()
            : collect();

        foreach ($person->attendanceRecords as $record) {
            $status = $record->status->value;
            if (! in_array($status, ['present', 'late', 'early_departure'], true)) {
                if ($status === 'review' || ($record->check_in_at && ! $record->check_out_at)) { $issues[] = "Attendance {$record->event_code} requires review."; $canPay = false; }
                continue;
            }
            if (! $record->check_in_at || ! $record->check_out_at || $record->check_out_at->lessThanOrEqualTo($record->check_in_at)) {
                $issues[] = "Attendance {$record->event_code} has an incomplete or invalid checkout.";
                $canPay = false;
                continue;
            }

            $date = Carbon::parse($record->attendance_date);
            $bounds = $this->boundsForRecord($person, $record, $date);
            if (! $bounds) {
                $issues[] = "Attendance {$record->event_code} has no valid schedule.";
                $canPay = false;
                continue;
            }

            $total = $record->check_in_at->diffInMinutes($record->check_out_at);
            $overlapStart = $record->check_in_at->greaterThan($bounds['start']) ? $record->check_in_at : $bounds['start'];
            $overlapEnd = $record->check_out_at->lessThan($bounds['end']) ? $record->check_out_at : $bounds['end'];
            $regular = $overlapEnd->greaterThan($overlapStart) ? $overlapStart->diffInMinutes($overlapEnd) : 0;
            $overtime = max(0, $total - $regular);
            $regularMinutes += $regular;
            $overtimeMinutes += $overtime;

            $rate = $rates->first(fn (DepartmentRoleRate $candidate) => $candidate->effective_from->lte($date) && (! $candidate->effective_until || $candidate->effective_until->gte($date)));
            if (! $rate) {
                $issues[] = "No pay rate covers {$date->toDateString()}.";
                $canPay = false;
            } elseif ($rate->currency !== $currency) {
                $issues[] = "Rate currency {$rate->currency} does not match payroll currency {$currency}.";
                $canPay = false;
            } else {
                $ratesUsed[(string) $rate->hourly_rate] = true;
                $regularCents += $this->payCents($regular, (string) $rate->hourly_rate);
                $overtimeCents += $this->payCents($overtime, (string) $rate->hourly_rate);
            }

            $breakdown[] = ['attendanceId' => $record->event_code, 'date' => $date->toDateString(), 'checkInAt' => $record->check_in_at->toIso8601String(), 'checkOutAt' => $record->check_out_at->toIso8601String(), 'scheduledStart' => $bounds['start']->toIso8601String(), 'scheduledEnd' => $bounds['end']->toIso8601String(), 'regularMinutes' => $regular, 'overtimeMinutes' => $overtime, 'totalMinutes' => $total, 'hourlyRate' => $rate?->hourly_rate, 'checkoutSource' => $record->check_out_source?->value ?? 'normal'];
        }

        $issues = array_values(array_unique($issues));
        if ($person->attendanceRecords->isEmpty() && $rates->count() === 1 && $rates->first()->currency === $currency) {
            $ratesUsed[(string) $rates->first()->hourly_rate] = true;
        }
        $rateValues = array_keys($ratesUsed);
        if (count($rateValues) > 1) $issues[] = 'Multiple historical rates were applied during this period.';
        $payable = $canPay;

        PayrollEntry::create(['payroll_period_id' => $period->id, 'person_id' => $person->id, 'department_id' => $person->department_id, 'role_id' => $person->role_id, 'person_code' => $person->person_code, 'person_name' => $person->full_name, 'department_code' => $person->department?->code, 'department_name' => $person->department?->name, 'role_code' => $person->role?->code, 'role_name' => $person->role?->name ?? $person->job_title, 'regular_minutes' => $regularMinutes, 'overtime_minutes' => $overtimeMinutes, 'total_worked_minutes' => $regularMinutes + $overtimeMinutes, 'hourly_rate' => count($rateValues) === 1 ? $rateValues[0] : null, 'currency' => $currency, 'regular_pay' => $payable ? $this->formatCents($regularCents) : null, 'overtime_pay' => $payable ? $this->formatCents($overtimeCents) : null, 'total_pay' => $payable ? $this->formatCents($regularCents + $overtimeCents) : null, 'status' => $issues ? 'attention' : 'calculated', 'issues' => $issues ?: null, 'attendance_breakdown' => $breakdown ?: null]);
    }

    private function boundsForRecord(Person $person, $record, Carbon $date): ?array
    {
        if ($record->scheduled_start && $record->scheduled_end) {
            $start = Carbon::parse($date->toDateString().' '.$record->scheduled_start);
            $end = Carbon::parse($date->toDateString().' '.$record->scheduled_end);
            if ($end->lessThanOrEqualTo($start)) $end->addDay();
            return ['start' => $start, 'end' => $end];
        }
        return $this->schedules->boundsFor($person, $date);
    }

    private function payCents(int $minutes, string $rate): int
    {
        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $scaled = ((int) $whole * 10000) + (int) str_pad(substr($fraction, 0, 4), 4, '0');
        return intdiv(($minutes * $scaled) + 3000, 6000);
    }

    private function formatCents(int $cents): string { return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100); }

    public function entries(PayrollPeriod $period, array $filters): LengthAwarePaginator
    {
        return $period->entries()->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $inner) => $inner->where('person_name', 'like', "%{$search}%")->orWhere('person_code', 'like', "%{$search}%")))
            ->when($filters['departmentId'] ?? null, fn (Builder $q, string $code) => $q->where('department_code', $code))
            ->when($filters['roleId'] ?? null, fn (Builder $q, string $code) => $q->where('role_code', $code))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', strtolower($status)))
            ->orderBy('person_name')->paginate($filters['limit'] ?? 20);
    }

    public function summary(PayrollPeriod $period): array
    {
        $query = $period->entries();
        return ['employees' => (clone $query)->count(), 'regularMinutes' => (int) (clone $query)->sum('regular_minutes'), 'overtimeMinutes' => (int) (clone $query)->sum('overtime_minutes'), 'totalWorkedMinutes' => (int) (clone $query)->sum('total_worked_minutes'), 'totalPay' => number_format((float) (clone $query)->sum('total_pay'), 2, '.', ''), 'attentionCount' => (clone $query)->where('status', 'attention')->count(), 'currency' => $period->currency];
    }

    public function transition(PayrollPeriod $period, string $status, User $actor): PayrollPeriod
    {
        $allowed = ['calculated' => ['approved'], 'approved' => ['closed']];
        if (! in_array($status, $allowed[$period->status] ?? [], true)) throw new ApiConflictException('The payroll period cannot move to that state.', 'INVALID_PAYROLL_STATE');
        $old = $period->status;
        $period->update(['status' => $status]);
        $this->audit->record('payroll.'.$status, $period, ['status' => $old], ['status' => $status], userId: $actor->id);
        return $period->fresh()->loadCount('entries');
    }
}
