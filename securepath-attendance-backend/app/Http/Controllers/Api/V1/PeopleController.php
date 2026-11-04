<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\ImportPeopleRequest;
use App\Http\Requests\StatusRequest;
use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\PersonResource;
use App\Http\Resources\RecognitionEventResource;
use App\Http\Resources\ScheduleResource;
use App\Models\Person;
use App\Services\AttendanceService;
use App\Services\PeopleImportService;
use App\Services\PeopleService;
use App\Services\WorkScheduleService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeopleController extends Controller
{
    public function __construct(private readonly PeopleService $people, private readonly AttendanceService $attendanceService, private readonly WorkScheduleService $workSchedules) {}
    public function index(ApiIndexRequest $request) { return ApiResponse::paginated($this->people->paginate($request->validated()), PersonResource::class); }
    public function show(Person $person) { return ApiResponse::success((new PersonResource($person->load(['department', 'role', 'biometricProfile', 'openAttendance', 'attendanceRecords' => fn ($q) => $q->whereDate('attendance_date', today())->limit(1)])))->resolve()); }
    public function store(StorePersonRequest $request) { return ApiResponse::success((new PersonResource($this->people->create($request->safe()->except('avatar'), $request->file('avatar'))))->resolve(), 'Person created successfully.', 201); }
    public function update(UpdatePersonRequest $request, Person $person) { return ApiResponse::success((new PersonResource($this->people->update($person, $request->safe()->except('avatar'), $request->file('avatar'))))->resolve(), 'Person updated successfully.'); }
    public function status(StatusRequest $request, Person $person) { return ApiResponse::success((new PersonResource($this->people->update($person, ['status' => strtolower($request->string('status'))])))->resolve(), 'Person status updated.'); }
    public function destroy(Person $person) { $this->people->delete($person); return response()->noContent(); }

    public function profile(Person $person)
    {
        $person->load(['department', 'role', 'biometricProfile', 'openAttendance', 'attendanceRecords' => fn ($q) => $q->whereDate('attendance_date', today())->limit(1)]);
        $attendance = $person->attendanceRecords()->with('person.department')->latest('attendance_date')->limit(10)->get();
        $recognitions = $person->recognitionEvents()->with(['person', 'attendance'])->latest('occurred_at')->limit(10)->get();
        $summary = ['totalDays' => $person->attendanceRecords()->count(), 'present' => $person->attendanceRecords()->where('status', 'present')->count(), 'late' => $person->attendanceRecords()->where('status', 'late')->count(), 'absent' => $person->attendanceRecords()->where('status', 'absent')->count(), 'averageConfidence' => round((float) $person->recognitionEvents()->avg('confidence') * 100, 1)];
        $schedule = $this->workSchedules->scheduleFor($person, now());
        $schedule?->loadMissing('days');
        return ApiResponse::success(['person' => (new PersonResource($person))->resolve(), 'currentAttendance' => $this->attendanceService->currentStatus($person), 'currentSchedule' => $schedule ? (new ScheduleResource($schedule))->resolve() : null, 'attendanceSummary' => $summary, 'recentAttendance' => AttendanceResource::collection($attendance)->resolve(), 'recognitionStatistics' => ['events' => $person->recognitionEvents()->count(), 'pending' => $person->recognitionEvents()->where('verification_status', 'pending')->count()], 'recentRecognition' => RecognitionEventResource::collection($recognitions)->resolve()]);
    }

    public function attendanceStatus(Person $person) { return ApiResponse::success($this->attendanceService->currentStatus($person)); }

    public function attendance(ApiIndexRequest $request, Person $person) { $paginator = $person->attendanceRecords()->with('person.department')->latest('attendance_date')->paginate($request->integer('limit', 20)); return ApiResponse::paginated($paginator, AttendanceResource::class); }
    public function import(ImportPeopleRequest $request, PeopleImportService $importer) { $summary = $importer->import($request->file('file'), $request->boolean('updateExisting')); return ApiResponse::success($summary, 'Import completed.', $summary['failed'] ? 200 : 201); }

    public function export(Request $request): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb'); fputcsv($output, ['person_code', 'first_name', 'last_name', 'email', 'phone', 'department', 'type', 'job_title', 'status', 'face_registered', 'biometric_consent']);
            Person::with('department')->orderBy('person_code')->chunk(500, function ($people) use ($output): void { foreach ($people as $person) fputcsv($output, [$person->person_code, $person->first_name, $person->last_name, $person->email, $person->phone, $person->department?->code, $person->type, $person->job_title, $person->status->value, $person->face_registered ? 1 : 0, $person->biometric_consent ? 1 : 0]); }); fclose($output);
        }, 'people-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
