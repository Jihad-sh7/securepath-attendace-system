<?php

namespace App\Support;

final class PermissionCatalog
{
    public const GROUPS = [
        'dashboard' => ['dashboard.view'],
        'people' => ['people.view', 'people.create', 'people.edit', 'people.delete', 'people.import', 'people.export'],
        'attendance' => ['attendance.view', 'attendance.create', 'attendance.manual_checkin', 'attendance.manual_checkout', 'attendance.edit', 'attendance.delete', 'attendance.approve'],
        'reports' => ['reports.view', 'reports.generate', 'reports.export'],
        'payroll' => ['payroll.view', 'payroll.manage', 'payroll.export'],
        'payRates' => ['payRates.view', 'payRates.manage'],
        'schedules' => ['schedules.view', 'schedules.manage'],
        'departments' => ['departments.view', 'departments.manage'],
        'alerts' => ['alerts.view', 'alerts.manage'],
        'administration' => ['users.view', 'users.manage', 'admins.view', 'admins.create', 'admins.edit', 'admins.delete', 'admins.permissions', 'auditLogs.view'],
        'settings' => ['settings.view', 'settings.manage'],
        'biometrics' => ['biometrics.view', 'biometrics.enroll', 'biometrics.reenroll', 'biometrics.delete', 'biometrics.manage'],
        'selfService' => ['myAttendance.view'],
    ];

    public static function all(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::GROUPS))));
    }

    public static function grouped(): array
    {
        return collect(self::GROUPS)->map(fn (array $permissions, string $group) => [
            'group' => $group,
            'permissions' => $permissions,
        ])->values()->all();
    }
}
