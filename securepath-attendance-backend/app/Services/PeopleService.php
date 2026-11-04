<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PeopleService
{
    public function __construct(private readonly AuditService $audit) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sorts = ['name' => 'full_name', 'id' => 'person_code', 'department' => 'department_id', 'status' => 'status', 'createdAt' => 'created_at'];
        return Person::query()->with(['department', 'role', 'biometricProfile', 'openAttendance', 'attendanceRecords' => fn ($query) => $query->whereDate('attendance_date', today())->limit(1)])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('person_code', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhereHas('department', fn (Builder $department) => $department->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))))
            ->when($filters['departmentId'] ?? null, fn (Builder $query, string $code) => $query->whereHas('department', fn (Builder $q) => $q->where('code', $code)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', strtolower($status)))
            ->when(array_key_exists('faceRegistered', $filters), fn (Builder $query) => $query->where('face_registered', filter_var($filters['faceRegistered'], FILTER_VALIDATE_BOOL)))
            ->when($filters['biometricStatus'] ?? null, fn (Builder $query, string $status) => $query->whereHas('biometricProfile', fn (Builder $profile) => $profile->where('status', $status)))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['attendanceStatus'] ?? null, fn (Builder $query, string $status) => $query->whereHas('attendanceRecords', fn (Builder $q) => $q->whereDate('attendance_date', today())->where('status', strtolower($status))))
            ->orderBy($sorts[$filters['sortBy'] ?? 'name'] ?? 'full_name', $filters['sortOrder'] ?? 'asc')
            ->paginate($filters['limit'] ?? 20);
    }

    public function create(array $data, ?UploadedFile $avatar = null): Person
    {
        return DB::transaction(function () use ($data, $avatar): Person {
            $departmentId = isset($data['departmentId']) ? Department::where('code', $data['departmentId'])->value('id') : null;
            $role = isset($data['roleId']) ? Role::where('code', $data['roleId'])->first() : null;
            [$firstName, $lastName] = $this->resolveNames($data);
            $person = Person::create([
                'person_code' => $data['personCode'] ?? 'TMP-'.Str::uuid(), 'first_name' => $firstName, 'last_name' => $lastName, 'full_name' => $data['fullName'] ?? trim("{$firstName} {$lastName}"),
                'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null, 'department_id' => $departmentId, 'role_id' => $role?->id, 'type' => $data['type'] ?? 'employee', 'job_title' => $role?->name ?? ($data['jobTitle'] ?? null),
                'status' => $data['status'] ?? 'active', 'biometric_consent' => $data['biometricConsent'] ?? false,
            ]);
            if (! isset($data['personCode'])) {
                $person->update(['person_code' => strtoupper(substr($person->type, 0, 3)).'-'.str_pad((string) $person->id, 5, '0', STR_PAD_LEFT)]);
            }
            if ($avatar) {
                $person->update(['avatar_path' => $avatar->storeAs('people/'.$person->id, Str::uuid().'.'.$avatar->extension())]);
            }
            $this->audit->record('person.created', $person, newValues: $person->only(['person_code', 'full_name', 'department_id', 'status']));
            return $person->load(['department', 'role', 'biometricProfile', 'openAttendance', 'attendanceRecords']);
        });
    }

    public function update(Person $person, array $data, ?UploadedFile $avatar = null): Person
    {
        return DB::transaction(function () use ($person, $data, $avatar): Person {
            $old = $person->toArray();
            $mapped = [];
            foreach (['firstName' => 'first_name', 'lastName' => 'last_name', 'fullName' => 'full_name', 'email' => 'email', 'phone' => 'phone', 'type' => 'type', 'jobTitle' => 'job_title', 'status' => 'status', 'biometricConsent' => 'biometric_consent'] as $source => $target) {
                if (array_key_exists($source, $data)) $mapped[$target] = $data[$source];
            }
            if (array_key_exists('departmentId', $data)) {
                $mapped['department_id'] = $data['departmentId'] ? Department::where('code', $data['departmentId'])->value('id') : null;
            }
            if (array_key_exists('roleId', $data)) {
                $role = $data['roleId'] ? Role::where('code', $data['roleId'])->first() : null;
                $mapped['role_id'] = $role?->id;
                $mapped['job_title'] = $role?->name;
            }
            if ($avatar) {
                $mapped['avatar_path'] = $avatar->storeAs('people/'.$person->id, Str::uuid().'.'.$avatar->extension());
            }
            if (! isset($mapped['full_name']) && (isset($mapped['first_name']) || isset($mapped['last_name']))) {
                $mapped['full_name'] = trim(($mapped['first_name'] ?? $person->first_name).' '.($mapped['last_name'] ?? $person->last_name));
            }
            $person->update($mapped);
            $this->audit->record('person.updated', $person, $old, $person->fresh()->toArray());
            return $person->fresh(['department', 'role', 'biometricProfile', 'openAttendance', 'attendanceRecords']);
        });
    }

    public function delete(Person $person): void
    {
        $this->audit->record('person.deleted', $person, $person->only(['person_code', 'full_name', 'status']));
        $person->update(['status' => 'inactive']);
        $person->delete();
    }

    private function resolveNames(array $data): array
    {
        if (isset($data['firstName'], $data['lastName'])) {
            return [$data['firstName'], $data['lastName']];
        }
        $parts = preg_split('/\s+/', trim($data['fullName'] ?? ''), 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
