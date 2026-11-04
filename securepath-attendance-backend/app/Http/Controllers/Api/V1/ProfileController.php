<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request) { return ApiResponse::success((new UserResource($request->user()->load('permissions', 'person')))->resolve()); }

    public function update(ProfileRequest $request, AuditService $audit)
    {
        $user = $request->user(); $old = $user->only(['name', 'email', 'phone']); $data = $request->safe()->except('avatar');
        if ($request->hasFile('avatar')) $data['avatar_path'] = $request->file('avatar')->storeAs('users/'.$user->id, Str::uuid().'.'.$request->file('avatar')->extension());
        $user->update($data); $audit->record('profile.updated', $user, $old, $user->only(['name', 'email', 'phone']));
        return ApiResponse::success((new UserResource($user->fresh(['permissions', 'person'])))->resolve(), 'Profile updated successfully.');
    }

    public function changePassword(ChangePasswordRequest $request, AuthService $auth) { $auth->changePassword($request->user(), $request->string('password')); return ApiResponse::success(null, 'Password changed successfully.'); }
}

