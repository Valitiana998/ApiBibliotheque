<?php

namespace App\Http\Controllers\Api;

trait ApiResponse
{
    protected function success(mixed $data, string $message, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => $message], $status);
    }

    protected function notFound(string $message = 'Ressource introuvable')
    {
        return response()->json(['success' => false, 'data' => null, 'message' => $message], 404);
    }
}
