<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\GeneratePayrollRequest;
use App\Http\Resources\PayrollEntryResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(Request $request)
    {
        $period = PayrollPeriod::withCount('entries')->when($request->integer('year'), fn ($q, $year) => $q->where('year', $year))->when($request->integer('month'), fn ($q, $month) => $q->where('month', $month))->latest('starts_on')->first();
        return ApiResponse::success($period ? ['period' => (new PayrollPeriodResource($period))->resolve(), 'summary' => $this->payroll->summary($period)] : null);
    }

    public function periods(ApiIndexRequest $request)
    {
        return ApiResponse::paginated(PayrollPeriod::withCount('entries')->latest('starts_on')->paginate($request->integer('limit', 20)), PayrollPeriodResource::class);
    }

    public function show(PayrollPeriod $payrollPeriod) { return ApiResponse::success((new PayrollPeriodResource($payrollPeriod->loadCount('entries')))->resolve()); }
    public function entries(ApiIndexRequest $request, PayrollPeriod $payrollPeriod) { return ApiResponse::paginated($this->payroll->entries($payrollPeriod, $request->validated()), PayrollEntryResource::class); }
    public function summary(PayrollPeriod $payrollPeriod) { return ApiResponse::success($this->payroll->summary($payrollPeriod)); }
    public function entry(PayrollEntry $payrollEntry) { return ApiResponse::success((new PayrollEntryResource($payrollEntry))->resolve()); }
    public function generate(GeneratePayrollRequest $request) { return ApiResponse::success((new PayrollPeriodResource($this->payroll->generate($request->integer('year'), $request->integer('month'), $request->user())))->resolve(), 'Payroll calculated successfully.', 201); }
    public function approve(Request $request, PayrollPeriod $payrollPeriod) { return ApiResponse::success((new PayrollPeriodResource($this->payroll->transition($payrollPeriod, 'approved', $request->user())))->resolve(), 'Payroll approved.'); }
    public function close(Request $request, PayrollPeriod $payrollPeriod) { return ApiResponse::success((new PayrollPeriodResource($this->payroll->transition($payrollPeriod, 'closed', $request->user())))->resolve(), 'Payroll closed.'); }

    public function export(PayrollPeriod $payrollPeriod)
    {
        $filename = sprintf('payroll-%04d-%02d.csv', $payrollPeriod->year, $payrollPeriod->month);
        return response()->streamDownload(function () use ($payrollPeriod): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Employee ID', 'Employee', 'Department', 'Role', 'Regular Hours', 'Overtime Hours', 'Total Hours', 'Hourly Rate', 'Regular Pay', 'Overtime Pay', 'Total Pay', 'Currency', 'Status', 'Issues']);
            $payrollPeriod->entries()->orderBy('person_name')->chunk(250, function ($entries) use ($output): void {
                foreach ($entries as $entry) fputcsv($output, [$entry->person_code, $entry->person_name, $entry->department_name, $entry->role_name, round($entry->regular_minutes / 60, 2), round($entry->overtime_minutes / 60, 2), round($entry->total_worked_minutes / 60, 2), $entry->hourly_rate, $entry->regular_pay, $entry->overtime_pay, $entry->total_pay, $entry->currency, $entry->status, implode('; ', $entry->issues ?? [])]);
            });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
