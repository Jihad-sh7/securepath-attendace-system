<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Request completed successfully.', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, ?string $resourceClass = null): JsonResponse
    {
        $items = $resourceClass
            ? $resourceClass::collection($paginator->getCollection())->resolve()
            : $paginator->items();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }
}
