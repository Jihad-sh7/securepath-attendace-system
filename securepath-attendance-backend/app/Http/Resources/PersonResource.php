<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = $this->relationLoaded('attendanceRecords') ? $this->attendanceRecords->sortByDesc('attendance_date')->first() : null;
        $open = $this->relationLoaded('openAttendance') ? $this->openAttendance : null;
        $current = $open ?? $today;
        $profile = $this->relationLoaded('biometricProfile') ? $this->biometricProfile : null;

        return [
            'id' => $this->person_code,
            'personCode' => $this->person_code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'departmentId' => $this->department?->code,
            'department' => $this->department?->name ?? 'Unassigned',
            'type' => $this->type,
            'roleId' => $this->role?->code,
            'role' => $this->role?->name ?? $this->job_title,
            'status' => ucfirst($this->status->value),
            'avatarUrl' => $this->avatar_path ? route('files.people-avatar', ['person' => $this->person_code]) : null,
            'faceStatus' => match ($profile?->status?->value) {
                'registered', 'enrolled' => 'Enrolled',
                'processing' => 'Processing',
                'pending' => 'Pending',
                'failed' => 'Failed',
                'revoked' => 'Revoked',
                default => $this->face_registered ? 'Enrolled' : 'Not Enrolled',
            },
            'faceRegistered' => $this->face_registered,
            'biometricReady' => $this->isBiometricReady(),
            'consent' => $this->biometric_consent ? 'Granted' : 'Pending',
            'biometricConsent' => $this->biometric_consent,
            'attendanceStatus' => $today ? str_replace('_', ' ', ucfirst($today->status->value)) : 'Absent',
            'attendanceState' => $open ? 'checked_in' : ($today?->check_out_at ? 'checked_out' : 'not_checked_in'),
            'isCheckedIn' => (bool) $open,
            'currentAttendanceId' => $current?->event_code,
            'checkIn' => $current?->check_in_at?->format('H:i') ?? '',
            'checkOut' => $current?->check_out_at?->format('H:i') ?? '',
            'checkInAt' => $current?->check_in_at?->toIso8601String(),
            'checkOutAt' => $current?->check_out_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
