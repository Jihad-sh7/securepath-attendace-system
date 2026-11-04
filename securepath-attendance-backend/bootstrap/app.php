<?php

use App\Exceptions\ApiConflictException;
use App\Exceptions\FaceAiUnavailableException;
use App\Exceptions\FaceProcessingException;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Console\Commands\ProcessPendingBiometricEnrollments;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ProcessPendingBiometricEnrollments::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request): ?string => null);

        $middleware->alias([
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'errors' => $exception->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Authentication is required.',
                'code' => 'UNAUTHENTICATED',
            ], Response::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'code' => 'FORBIDDEN',
            ], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The requested resource was not found.',
                'code' => 'NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (ApiConflictException $exception, Request $request) {
            $payload = [
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => $exception->codeName,
            ];
            if ($exception->codeName === 'FACE_NOT_REGISTERED') $payload['recognized'] = false;
            if ($exception->details !== []) $payload['details'] = $exception->details;

            return response()->json($payload, Response::HTTP_CONFLICT);
        });

        $exceptions->render(fn (FaceAiUnavailableException $exception, Request $request) => response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'code' => 'FACE_AI_UNAVAILABLE',
        ], Response::HTTP_SERVICE_UNAVAILABLE));

        $exceptions->render(fn (FaceProcessingException $exception, Request $request) => response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'code' => $exception->codeName,
        ], Response::HTTP_UNPROCESSABLE_ENTITY));

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) return null;

            $status = $exception->getStatusCode();
            [$message, $code] = match ($status) {
                400 => ['The request could not be processed.', 'BAD_REQUEST'],
                401 => ['Authentication is required.', 'UNAUTHENTICATED'],
                403 => ['You do not have permission to perform this action.', 'FORBIDDEN'],
                404 => ['The requested resource was not found.', 'NOT_FOUND'],
                405 => ['The requested method is not allowed.', 'METHOD_NOT_ALLOWED'],
                409 => ['The request conflicts with the current resource state.', 'CONFLICT'],
                429 => ['Too many requests. Please try again later.', 'RATE_LIMITED'],
                default => ['The request could not be completed.', 'HTTP_ERROR'],
            };

            return response()->json(['success' => false, 'message' => $message, 'code' => $code], $status, $exception->getHeaders());
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) return null;
            return response()->json(['success' => false, 'message' => 'A database operation could not be completed.', 'code' => 'DATABASE_ERROR'], Response::HTTP_INTERNAL_SERVER_ERROR);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) return null;
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred.', 'code' => 'INTERNAL_ERROR'], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })
    ->create();
