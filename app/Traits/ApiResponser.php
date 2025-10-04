<?php

namespace App\Traits;

trait ApiResponser
{
    protected function success($data = [], $message = "")
    {
        return response()->json([
            'code' => 1,
            'message' => $message,
            'data' => $data
        ]);
    }

    protected function error($message = "", $statusCode = 400, $additionalData = [])
    {
        $response = [
            'code' => 0,
            'message' => $message,
            'data' => $additionalData ?: ""
        ];
        
        return response()->json($response, $statusCode);
    }
}
