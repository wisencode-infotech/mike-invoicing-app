<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Every /api/v1/* response — success or failure — shares this envelope
 * (see docs/ARCHITECTURE.md section 9), so integrators can always check
 * `success` and read `data`/`message` the same way regardless of endpoint.
 */
trait ApiResponses
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        $body = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    protected function fail(string $message, mixed $errors = null, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $errors !== null ? ['errors' => $errors] : null,
        ], $status);
    }

    /**
     * Flattens a paginator into a plain resource array plus a `meta` block,
     * rather than nesting Laravel's own links/meta shape inside our `data`
     * key — keeps the envelope shape identical for paginated and
     * non-paginated endpoints.
     */
    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass, string $message = 'OK'): JsonResponse
    {
        return $this->success(
            $resourceClass::collection($paginator->items()),
            $message,
            200,
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
