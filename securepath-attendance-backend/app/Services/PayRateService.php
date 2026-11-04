<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DepartmentRoleRate;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayRateService
{
    public function __construct(private readonly AuditService $audit) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return DepartmentRoleRate::with(['department', 'role'])
            ->when($filters['departmentId'] ?? null, fn (Builder $q, string $code) => $q->whereHas('department', fn (Builder $d) => $d->where('code', $code)))
            ->when($filters['roleId'] ?? null, fn (Builder $q, string $code) => $q->whereHas('role', fn (Builder $r) => $r->where('code', $code)))
            ->latest('effective_from')->paginate($filters['limit'] ?? 20);
    }

    public function save(?DepartmentRoleRate $rate, array $data, int $userId): DepartmentRoleRate
    {
        return DB::transaction(function () use ($rate, $data, $userId): DepartmentRoleRate {
            $rate ??= new DepartmentRoleRate;
            $old = $rate->exists ? $rate->toArray() : null;
            $departmentId = Department::where('code', $data['departmentId'])->value('id');
            $roleId = Role::where('code', $data['roleId'])->value('id');
            $overlap = DepartmentRoleRate::where('department_id', $departmentId)->where('role_id', $roleId)->when($rate->exists, fn ($q) => $q->whereKeyNot($rate->id))
                ->whereDate('effective_from', '<=', $data['effectiveUntil'] ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effectiveFrom']))->exists();
            if ($overlap) throw ValidationException::withMessages(['effectiveFrom' => ['This department and role already have a rate overlapping the selected dates.']]);

            $rate->fill(['department_id' => $departmentId, 'role_id' => $roleId, 'hourly_rate' => $data['hourlyRate'], 'currency' => $data['currency'] ?? 'USD', 'effective_from' => $data['effectiveFrom'], 'effective_until' => $data['effectiveUntil'] ?? null, 'created_by' => $rate->created_by ?? $userId])->save();
            $this->audit->record($old ? 'pay_rate.updated' : 'pay_rate.created', $rate, $old, $rate->toArray(), userId: $userId);
            return $rate->load(['department', 'role']);
        });
    }

    public function delete(DepartmentRoleRate $rate, int $userId): void
    {
        $this->audit->record('pay_rate.deleted', $rate, $rate->toArray(), userId: $userId);
        $rate->delete();
    }
}
