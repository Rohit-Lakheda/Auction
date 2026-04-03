<?php

namespace App\Http\Controllers\Api\V1\Concerns;

trait ApiResponse
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'error' => null,
        ], $status);
    }

    protected function fail(string $message, int $status = 422, mixed $error = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'error' => $error,
        ], $status);
    }
}

