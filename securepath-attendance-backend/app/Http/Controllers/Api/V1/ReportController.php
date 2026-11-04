<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Services\ReportService;
use App\Support\ApiResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}
    public function show(ApiIndexRequest $request, string $type) { return ApiResponse::success($this->reports->generate($type, $request->validated())); }
    public function export(ApiIndexRequest $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ReportService::TYPES, true), 404);
        return response()->streamDownload(function () use ($type, $request): void {
            $output = fopen('php://output', 'wb');
            $headers = $type === 'recognition-accuracy' ? ['recognition_code', 'person_code', 'person_name', 'confidence', 'liveness', 'recognition_status', 'verification_status', 'occurred_at'] : ['event_code', 'person_code', 'person_name', 'department', 'date', 'check_in_at', 'check_out_at', 'status', 'worked_minutes', 'verification_method', 'confidence'];
            fputcsv($output, $headers); foreach ($this->reports->exportRows($type, $request->validated()) as $row) fputcsv($output, $row); fclose($output);
        }, $type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}

