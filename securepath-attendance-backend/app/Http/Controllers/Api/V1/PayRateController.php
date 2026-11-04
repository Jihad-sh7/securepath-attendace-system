<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\StorePayRateRequest;
use App\Http\Resources\PayRateResource;
use App\Models\DepartmentRoleRate;
use App\Services\PayRateService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PayRateController extends Controller
{
    public function __construct(private readonly PayRateService $rates) {}
    public function index(ApiIndexRequest $request) { return ApiResponse::paginated($this->rates->paginate($request->validated()), PayRateResource::class); }
    public function store(StorePayRateRequest $request) { return ApiResponse::success((new PayRateResource($this->rates->save(null, $request->validated(), $request->user()->id)))->resolve(), 'Pay rate created successfully.', 201); }
    public function update(StorePayRateRequest $request, DepartmentRoleRate $payRate) { return ApiResponse::success((new PayRateResource($this->rates->save($payRate, $request->validated(), $request->user()->id)))->resolve()); }
    public function destroy(Request $request, DepartmentRoleRate $payRate) { $this->rates->delete($payRate, $request->user()->id); return response()->noContent(); }
}
