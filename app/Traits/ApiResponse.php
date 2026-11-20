<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * One response envelope for every API endpoint, so the frontend and the
 * tests only ever need to read { success, message, data }.
 */
trait ApiResponse
{
    protected function successResponse(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function createdResponse(mixed $data, string $message = 'Data created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    protected function notFoundResponse(string $resource = 'Data'): JsonResponse
    {
        return self::errorResponse("{$resource} not found", 404);
    }

    protected function badRequestResponse(string $message): JsonResponse
    {
        return self::errorResponse($message, 400);
    }

    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return self::errorResponse($message, 500);
    }

    /**
     * Static so the global exception handler in bootstrap/app.php can build
     * the exact same envelope without duplicating its shape.
     */
    public static function errorResponse(string $message, int $status, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }
}