<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait ApiResponse
{
    protected function success(
        ?string $message = null,
        mixed $data = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message ?? __('common.success'),
            'data' => $data,
            'errors' => null,
            'asset_base' => $this->assetBase(),
        ], $status);
    }

    /**
     * The origin the client reached this API through. Frontends resolve stored
     * relative image paths (e.g. `/uploads/images/...`) against this value so
     * uploads keep working after the LAN IP / host changes, without a rebuild.
     */
    protected function assetBase(): string
    {
        return request()->getSchemeAndHttpHost();
    }

    protected function error(
        ?string $message = null,
        mixed $errors = null,
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message ?? __('common.failed'),
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    protected function validationError(array $errors, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message ?? __('common.validation_failed'),
            'data' => null,
            'errors' => $errors,
        ], 422);
    }

    protected function withResource(
        mixed $resource,
        ?string $message = null,
    ): JsonResponse {
        if ($resource instanceof AnonymousResourceCollection) {
            $data = $resource->response()->getData(true);

            return response()->json([
                'success' => true,
                'message' => $message ?? __('common.success'),
                'data' => $data,
                'errors' => null,
                'asset_base' => $this->assetBase(),
            ]);
        }

        return $this->success($message, $resource);
    }

    /**
     * The standard list payload, for controllers that need to nest a page of
     * results inside a larger response instead of returning it at the top level.
     *
     * @param  callable(Collection): mixed  $resource
     * @return array<string, mixed>
     */
    protected function paginatedPayload(LengthAwarePaginator $paginator, callable $resource): array
    {
        return [
            'data' => $resource(collect($paginator->items())),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @param  callable(Collection): mixed  $resource
     * @param  array<string, mixed>  $extra  Sibling keys merged alongside the page, e.g. counts
     */
    protected function paginated(
        string $message,
        LengthAwarePaginator $paginator,
        callable $resource,
        array $extra = [],
    ): JsonResponse {
        return $this->success(
            $message,
            array_merge($this->paginatedPayload($paginator, $resource), $extra),
        );
    }
}
