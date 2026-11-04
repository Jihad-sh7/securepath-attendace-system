<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceLoginRequest;
use App\Http\Requests\KioskAttendanceRequest;
use App\Http\Requests\RecognitionEventRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\RecognitionEventResource;
use App\Http\Resources\ScheduleResource;
use App\Http\Resources\UserResource;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\MobileAuthService;
use App\Services\KioskAttendanceService;
use App\Services\RecognitionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MobileController extends Controller
{
    public function login(DeviceLoginRequest $request, MobileAuthService $auth) { return ApiResponse::success($auth->login($request->string('identifier'), $request->string('secret')), 'Device authenticated.'); }
    public function me(Request $request) { $actor = $request->user(); return ApiResponse::success($actor instanceof User ? (new UserResource($actor->load('permissions', 'person')))->resolve() : ['id' => $actor->id, 'name' => $actor->name, 'identifier' => $actor->identifier, 'type' => 'kiosk_device']); }
    public function schedule(Request $request) { $user = $request->user(); abort_unless($user instanceof User && $user->person, 403); $schedule = $user->person->schedules()->with('days')->first() ?? $user->person->department?->schedules()->with('days')->first(); return ApiResponse::success($schedule ? (new ScheduleResource($schedule))->resolve() : null); }
    public function attendance(Request $request) { $user = $request->user(); abort_unless($user instanceof User && $user->person, 403); $records = $user->person->attendanceRecords()->with('person.department')->latest('attendance_date')->paginate(min($request->integer('limit', 20), 100)); return ApiResponse::paginated($records, AttendanceResource::class); }
    public function submitRecognition(RecognitionEventRequest $request, RecognitionService $recognition)
    {
        $device = $request->user(); abort_unless($device instanceof MobileDevice && $request->user()->tokenCan('recognition:submit'), 403);
        $data = $request->validated(); $data['deviceIdentifier'] = $device->identifier;
        return ApiResponse::success((new RecognitionEventResource($recognition->submit($data)))->resolve(), 'Recognition event accepted.', 201);
    }

    public function recognizeAttendance(KioskAttendanceRequest $request, KioskAttendanceService $attendance)
    {
        $device = $request->user(); abort_unless($device instanceof MobileDevice && $request->user()->tokenCan('recognition:submit'), 403);
        return ApiResponse::success($attendance->recognize($request->file('image'), $request->validated(), $device), 'Attendance recorded.');
    }
}
