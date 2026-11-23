<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;

class HealthController extends Controller
{
    public function readiness(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
        } catch (PDOException $exception) {
            Log::warning('Database readiness check failed.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'status' => 'unavailable',
                'checks' => [
                    'database' => 'unavailable',
                ],
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'checks' => [
                'database' => 'ok',
            ],
        ]);
    }
}
