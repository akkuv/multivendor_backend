<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler
{
    use ApiResponse;

    public function render(Request $request, Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => $this->handleValidation($e),
            $e instanceof AuthenticationException => $this->handleAuthentication(),
            $e instanceof AuthorizationException => $this->handleAuthorization(),
            $e instanceof NotFoundHttpException,
            $e instanceof ModelNotFoundException => $this->handleNotFound($e),
            $e instanceof MethodNotAllowedHttpException => $this->handleMethodNotAllowed(),
            $e instanceof InsufficientStockException => $this->error($e->getMessage(), null, 422),
            $e instanceof QueryException => $this->handleQuery($e),
            $e instanceof HttpException => $this->handleHttp($e),
            default => $this->handleException($e),
        };
    }

    protected function handleValidation(ValidationException $e): JsonResponse
    {
        return $this->validationError($e->errors());
    }

    protected function handleAuthentication(): JsonResponse
    {
        return $this->error(__('Unauthenticated. Please login.'), null, 401);
    }

    protected function handleAuthorization(): JsonResponse
    {
        return $this->error(__('You are not authorized to perform this action.'), null, 403);
    }

    protected function handleNotFound(Throwable $e): JsonResponse
    {
        $message = 'Resource not found.';
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            $message = "{$model} not found.";
        }

        return $this->error($message, null, 404);
    }

    protected function handleMethodNotAllowed(): JsonResponse
    {
        return $this->error(__('Method not allowed.'), null, 405);
    }

    protected function handleQuery(QueryException $e): JsonResponse
    {
        $message = $e->getMessage();

        // Foreign key constraint failures happen when a record is still
        // referenced by other data (e.g. deleting a brand that has products
        // or a category that has child records). These are expected user
        // errors: return a clear message instead of a generic 500 and skip
        // the error-log noise.
        if (
            str_contains($message, 'cannot delete or update a parent row')
            || str_contains($message, 'foreign key constraint fails')
            || str_contains($message, 'FOREIGN KEY constraint failed')
            || str_contains($message, 'is still referenced from table')
        ) {
            return $this->error(
                'This record cannot be deleted because it is still referenced by other data. Remove or unlink the related records before deleting.',
                null,
                422,
            );
        }

        logger()->error($message, ['trace' => $e->getTraceAsString()]);

        return $this->error(__('Database error occurred.'), null, 500);
    }

    protected function handleHttp(HttpException $e): JsonResponse
    {
        return $this->error(
            $e->getMessage() ?: 'Request error occurred.',
            null,
            $e->getStatusCode(),
        );
    }

    protected function handleException(Throwable $e): JsonResponse
    {
        if (config('app.debug')) {
            logger()->error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $this->error(
            config('app.debug') ? $e->getMessage() : 'Internal server error occurred.',
            config('app.debug') ? ['exception' => class_basename($e)] : null,
            500,
        );
    }
}
