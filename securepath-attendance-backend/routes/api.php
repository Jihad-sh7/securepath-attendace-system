<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AttendanceCorrectionController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BiometricController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\MobileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PayRateController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\PeopleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RecognitionEventController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:login');
    });

    Route::post('mobile/auth/login', [MobileController::class, 'login'])->middleware('throttle:login');
    Route::get('mobile/enrollments/{token}', [EnrollmentController::class, 'show'])->middleware('throttle:mobile');
    Route::post('mobile/enrollments/{token}/complete', [EnrollmentController::class, 'complete'])->middleware('throttle:mobile');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });

        Route::get('profile', [ProfileController::class, 'show']);
        Route::match(['put', 'post'], 'profile', [ProfileController::class, 'update']);
        Route::post('profile/change-password', [ProfileController::class, 'changePassword']);

        Route::prefix('mobile')->middleware('throttle:mobile')->group(function (): void {
            Route::get('me', [MobileController::class, 'me']);
            Route::get('schedule', [MobileController::class, 'schedule']);
            Route::get('attendance', [MobileController::class, 'attendance']);
            Route::post('recognition-events', [MobileController::class, 'submitRecognition']);
            Route::post('attendance/recognize', [MobileController::class, 'recognizeAttendance']);
        });

        Route::middleware('permission:dashboard.view')->prefix('dashboard')->group(function (): void {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('summary', [DashboardController::class, 'summary']);
            Route::get('attendance-trend', [DashboardController::class, 'attendanceTrend']);
            Route::get('attendance-by-department', [DashboardController::class, 'attendanceByDepartment']);
            Route::get('recent-activity', [DashboardController::class, 'recentActivity']);
        });

        Route::get('people/export', [PeopleController::class, 'export'])->middleware('permission:people.export');
        Route::post('people/import', [PeopleController::class, 'import'])->middleware('permission:people.import');
        Route::get('people', [PeopleController::class, 'index'])->middleware('permission:people.view');
        Route::post('people', [PeopleController::class, 'store'])->middleware('permission:people.create');
        Route::get('people/{person}', [PeopleController::class, 'show'])->middleware('permission:people.view');
        Route::get('people/{person}/profile', [PeopleController::class, 'profile'])->middleware('permission:people.view');
        Route::get('people/{person}/attendance', [PeopleController::class, 'attendance'])->middleware('permission:people.view');
        Route::get('people/{person}/attendance-status', [PeopleController::class, 'attendanceStatus'])->middleware('permission:people.view,attendance.view,attendance.manual_checkin,attendance.manual_checkout');
        Route::match(['put', 'patch'], 'people/{person}', [PeopleController::class, 'update'])->middleware('permission:people.edit');
        Route::patch('people/{person}/status', [PeopleController::class, 'status'])->middleware('permission:people.edit');
        Route::delete('people/{person}', [PeopleController::class, 'destroy'])->middleware('permission:people.delete');
        Route::get('people/{person}/avatar', [FileController::class, 'personAvatar'])->middleware('permission:people.view')->name('files.people-avatar');

        Route::get('people/{person}/biometric-status', [BiometricController::class, 'status'])->middleware('permission:biometrics.view');
        Route::post('people/{person}/biometric-enrollment', [BiometricController::class, 'enroll'])->middleware('permission:biometrics.enroll');
        Route::post('people/{person}/biometric-reenrollment', [BiometricController::class, 'reenroll'])->middleware('permission:biometrics.reenroll');
        Route::post('people/{person}/biometric-enrollment/retry', [BiometricController::class, 'retry'])->middleware('permission:biometrics.enroll,biometrics.reenroll');
        Route::get('people/{person}/biometric-consent', [BiometricController::class, 'consentStatus'])->middleware('permission:biometrics.view');
        Route::post('people/{person}/biometric-consent', [BiometricController::class, 'consent'])->middleware('permission:biometrics.manage');
        Route::delete('people/{person}/biometric-data', [BiometricController::class, 'destroy'])->middleware('permission:biometrics.delete');
        Route::post('people/{person}/enrollment-request', [BiometricController::class, 'requestEnrollment'])->middleware('permission:biometrics.manage');
        Route::get('people/{person}/enrollment-status', [BiometricController::class, 'enrollmentStatus'])->middleware('permission:biometrics.view');

        Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:departments.view');
        Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:departments.manage');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->middleware('permission:departments.view');
        Route::get('departments/{department}/members', [DepartmentController::class, 'members'])->middleware('permission:departments.view');
        Route::get('departments/{department}/statistics', [DepartmentController::class, 'statistics'])->middleware('permission:departments.view');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:departments.manage');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:departments.manage');

        Route::get('schedules', [ScheduleController::class, 'index'])->middleware('permission:schedules.view');
        Route::post('schedules', [ScheduleController::class, 'store'])->middleware('permission:schedules.manage');
        Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])->middleware('permission:schedules.view');
        Route::put('schedules/{schedule}', [ScheduleController::class, 'update'])->middleware('permission:schedules.manage');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->middleware('permission:schedules.manage');

        Route::get('holidays', [HolidayController::class, 'index'])->middleware('permission:schedules.view');
        Route::post('holidays', [HolidayController::class, 'store'])->middleware('permission:schedules.manage');
        Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->middleware('permission:schedules.manage');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->middleware('permission:schedules.manage');

        Route::get('attendance', [AttendanceController::class, 'index'])->middleware('permission:attendance.view,myAttendance.view');
        Route::post('attendance', [AttendanceController::class, 'store'])->middleware('permission:attendance.create');
        Route::post('attendance/manual', [AttendanceController::class, 'manual'])->middleware('permission:attendance.create');
        Route::post('attendance/manual-check-in', [AttendanceController::class, 'manualCheckIn'])->middleware('permission:attendance.manual_checkin');
        Route::post('attendance/manual-check-out', [AttendanceController::class, 'manualCheckOut'])->middleware('permission:attendance.manual_checkout');
        Route::get('attendance/{attendance}', [AttendanceController::class, 'show'])->middleware('permission:attendance.view,myAttendance.view');
        Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->middleware('permission:attendance.edit');
        Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])->middleware('permission:attendance.delete');
        Route::post('attendance/{attendance}/corrections', [AttendanceCorrectionController::class, 'store'])->middleware('permission:attendance.edit,myAttendance.view');

        Route::get('attendance-corrections', [AttendanceCorrectionController::class, 'index'])->middleware('permission:attendance.approve');
        Route::get('attendance-corrections/{attendanceCorrection}', [AttendanceCorrectionController::class, 'show'])->middleware('permission:attendance.approve');
        Route::patch('attendance-corrections/{attendanceCorrection}/approve', [AttendanceCorrectionController::class, 'approve'])->middleware('permission:attendance.approve');
        Route::patch('attendance-corrections/{attendanceCorrection}/reject', [AttendanceCorrectionController::class, 'reject'])->middleware('permission:attendance.approve');

        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->middleware('permission:attendance.view,myAttendance.view');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->middleware('permission:attendance.create,myAttendance.view');
        Route::get('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->middleware('permission:attendance.view,myAttendance.view');
        Route::patch('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->middleware('permission:attendance.approve');
        Route::patch('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->middleware('permission:attendance.approve');
        Route::delete('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->middleware('permission:attendance.edit,myAttendance.view');

        Route::get('recognition-events', [RecognitionEventController::class, 'index'])->middleware('permission:biometrics.view');
        Route::get('recognition-events/{recognitionEvent}', [RecognitionEventController::class, 'show'])->middleware('permission:biometrics.view');
        Route::post('recognition-events/{recognitionEvent}/verify', [RecognitionEventController::class, 'verify'])->middleware('permission:biometrics.manage');
        Route::post('recognition-events/{recognitionEvent}/reject', [RecognitionEventController::class, 'reject'])->middleware('permission:biometrics.manage');
        Route::post('recognition-events/{recognitionEvent}/assign-person', [RecognitionEventController::class, 'assign'])->middleware('permission:biometrics.manage');

        Route::get('reports/{type}', [ReportController::class, 'show'])->middleware('permission:reports.view');
        Route::get('reports/{type}/export', [ReportController::class, 'export'])->middleware('permission:reports.export');

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:payRates.view,people.view,payroll.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:payRates.manage');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:payRates.manage');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:payRates.manage');
        Route::get('pay-rates', [PayRateController::class, 'index'])->middleware('permission:payRates.view');
        Route::post('pay-rates', [PayRateController::class, 'store'])->middleware('permission:payRates.manage');
        Route::put('pay-rates/{payRate}', [PayRateController::class, 'update'])->middleware('permission:payRates.manage');
        Route::delete('pay-rates/{payRate}', [PayRateController::class, 'destroy'])->middleware('permission:payRates.manage');

        Route::get('payroll', [PayrollController::class, 'index'])->middleware('permission:payroll.view');
        Route::get('payroll/periods', [PayrollController::class, 'periods'])->middleware('permission:payroll.view');
        Route::get('payroll/entries/{payrollEntry}', [PayrollController::class, 'entry'])->middleware('permission:payroll.view');
        Route::get('payroll/periods/{payrollPeriod}', [PayrollController::class, 'show'])->middleware('permission:payroll.view');
        Route::get('payroll/periods/{payrollPeriod}/employees', [PayrollController::class, 'entries'])->middleware('permission:payroll.view');
        Route::get('payroll/periods/{payrollPeriod}/summary', [PayrollController::class, 'summary'])->middleware('permission:payroll.view');
        Route::get('payroll/periods/{payrollPeriod}/export', [PayrollController::class, 'export'])->middleware('permission:payroll.export');
        Route::post('payroll/generate', [PayrollController::class, 'generate'])->middleware('permission:payroll.manage');
        Route::post('payroll/periods/{payrollPeriod}/approve', [PayrollController::class, 'approve'])->middleware('permission:payroll.manage');
        Route::post('payroll/periods/{payrollPeriod}/close', [PayrollController::class, 'close'])->middleware('permission:payroll.manage');

        Route::get('alerts', [AlertController::class, 'index'])->middleware('permission:alerts.view');
        Route::get('alerts/{alert}', [AlertController::class, 'show'])->middleware('permission:alerts.view');
        Route::patch('alerts/{alert}/status', [AlertController::class, 'status'])->middleware('permission:alerts.manage');
        Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->middleware('permission:alerts.manage');
        Route::post('alerts/{alert}/notes', [AlertController::class, 'notes'])->middleware('permission:alerts.manage');

        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.manage');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
        Route::patch('users/{user}/status', [UserController::class, 'status'])->middleware('permission:users.manage');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');
        Route::get('users/{user}/avatar', [FileController::class, 'userAvatar'])->name('files.user-avatar');

        Route::middleware('role:super_admin')->group(function (): void {
            Route::get('admins', [AdminController::class, 'index']);
            Route::post('admins', [AdminController::class, 'store']);
            Route::get('admins/{admin}', [AdminController::class, 'show']);
            Route::put('admins/{admin}', [AdminController::class, 'update']);
            Route::delete('admins/{admin}', [AdminController::class, 'destroy']);
            Route::patch('admins/{admin}/status', [AdminController::class, 'status']);
            Route::get('admins/{admin}/permissions', [AdminController::class, 'permissions']);
            Route::put('admins/{admin}/permissions', [AdminController::class, 'updatePermissions']);
            Route::post('admins/{admin}/reset-password', [AdminController::class, 'resetPassword']);
            Route::get('permissions', PermissionController::class);
        });

        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:auditLogs.view');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:auditLogs.view');
        Route::get('settings', [SettingsController::class, 'index'])->middleware('permission:settings.view');
        Route::put('settings', [SettingsController::class, 'update'])->middleware('permission:settings.manage');

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
    });
});
