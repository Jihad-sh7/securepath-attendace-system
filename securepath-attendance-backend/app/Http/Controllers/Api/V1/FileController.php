<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function personAvatar(Person $person) { abort_unless($person->avatar_path && Storage::disk('local')->exists($person->avatar_path), 404); return Storage::disk('local')->response($person->avatar_path); }
    public function userAvatar(User $user, Request $request)
    {
        abort_unless($request->user()->is($user) || $request->user()->hasPermission('users.view'), 403);
        abort_unless($user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);
        return Storage::disk('local')->response($user->avatar_path);
    }
}
