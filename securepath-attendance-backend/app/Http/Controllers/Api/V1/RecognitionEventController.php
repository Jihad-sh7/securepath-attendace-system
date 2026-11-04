<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\AssignRecognitionRequest;
use App\Http\Requests\ReviewRequest;
use App\Http\Resources\RecognitionEventResource;
use App\Models\Person;
use App\Models\RecognitionEvent;
use App\Services\RecognitionService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

class RecognitionEventController extends Controller
{
    public function __construct(private readonly RecognitionService $recognition) {}
    public function index(ApiIndexRequest $request)
    {
        $filters = $request->validated(); $paginator = RecognitionEvent::with(['person', 'attendance', 'verifier'])
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn ($inner) => $inner->where('recognition_code', 'like', "%{$search}%")->orWhere('source_device_identifier', 'like', "%{$search}%")->orWhereHas('person', fn ($person) => $person->where('full_name', 'like', "%{$search}%"))))
            ->when($filters['dateFrom'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '>=', $date))->when($filters['dateTo'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '<=', $date))
            ->when($filters['personId'] ?? null, fn ($q, $code) => $q->whereHas('person', fn ($person) => $person->where('person_code', $code)))
            ->when($filters['recognitionStatus'] ?? null, fn ($q, $status) => $q->where('recognition_status', $status))->when($filters['verificationStatus'] ?? null, fn ($q, $status) => $q->where('verification_status', $status))
            ->when($filters['minConfidence'] ?? null, fn ($q, $confidence) => $q->where('confidence', '>=', $confidence))->latest('occurred_at')->paginate($filters['limit'] ?? 20);
        return ApiResponse::paginated($paginator, RecognitionEventResource::class);
    }
    public function show(RecognitionEvent $recognitionEvent) { return ApiResponse::success((new RecognitionEventResource($recognitionEvent->load(['person', 'attendance', 'verifier'])))->resolve()); }
    public function verify(ReviewRequest $request, RecognitionEvent $recognitionEvent) { return ApiResponse::success((new RecognitionEventResource($this->recognition->verify($recognitionEvent, $request->user(), $request->input('note'))))->resolve(), 'Recognition verified.'); }
    public function reject(ReviewRequest $request, RecognitionEvent $recognitionEvent) { return ApiResponse::success((new RecognitionEventResource($this->recognition->reject($recognitionEvent, $request->user(), $request->input('note'))))->resolve(), 'Recognition rejected.'); }
    public function assign(AssignRecognitionRequest $request, RecognitionEvent $recognitionEvent) { $person = Person::where('person_code', $request->string('personId'))->firstOrFail(); return ApiResponse::success((new RecognitionEventResource($this->recognition->assignPerson($recognitionEvent, $person, $request->user(), $request->input('note'))))->resolve(), 'Person assigned.'); }
}

