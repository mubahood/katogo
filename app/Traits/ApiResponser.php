<?php

namespace App\Traits;

trait ApiResponser
{
    protected function success($data = [], $message = "")
    {
        return response()->json([
            'code' => 1,
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }

    protected function error($message = "", $statusCode = 400, $additionalData = [])
    {
        $response = [
            'code' => 0,
            'status' => 'error',
            'message' => $message,
            'data' => $additionalData ?: ""
        ];
        
        return response()->json($response, $statusCode);
    }
}
