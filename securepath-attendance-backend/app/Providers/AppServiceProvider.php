<?php

namespace App\Providers;

use App\Events\AlertCreated;
use App\Events\RecognitionPendingVerification;
use App\Listeners\NotifyAlertReviewers;
use App\Listeners\NotifyRecognitionReviewers;
use App\Models\User;
use App\Contracts\FaceAiServiceInterface;
use App\Services\PythonFaceAiService;
use App\Support\PermissionCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FaceAiServiceInterface::class, PythonFaceAiService::class);
    }

    public function boot(): void
    {
        Event::listen(RecognitionPendingVerification::class, NotifyRecognitionReviewers::class);
        Event::listen(AlertCreated::class, NotifyAlertReviewers::class);

        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        foreach (PermissionCatalog::all() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute((int) env('LOGIN_RATE_LIMIT', 5))
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('mobile', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
