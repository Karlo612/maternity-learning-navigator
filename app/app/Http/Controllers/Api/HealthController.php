<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MlClient;
use App\Services\ReleaseGate;
use Illuminate\Http\JsonResponse;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(ReleaseGate $gate, MlClient $ml): JsonResponse
    {
        try {
            $model = $ml->health();
        } catch (Throwable) {
            $model = ['status' => 'unavailable'];
        }

        return response()->json([
            'status' => 'ok',
            'database' => 'available',
            'model_service' => $model,
            'release_gate' => $gate->status(),
        ]);
    }
}
