<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CuratedDemoGate;
use App\Services\MlClient;
use App\Services\ReleaseGate;
use Illuminate\Http\JsonResponse;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(ReleaseGate $gate, CuratedDemoGate $demoGate, MlClient $ml): JsonResponse
    {
        try {
            $model = $ml->health();
        } catch (Throwable) {
            $model = ['status' => 'unavailable'];
        }

        $demoStatus = $demoGate->status();

        return response()->json([
            'status' => 'ok',
            'database' => 'available',
            'model_service' => $model,
            'release_gate' => $gate->status(),
            'curated_demo' => [
                'mode' => 'curated_demo',
                'release_status' => $demoStatus['status'],
                'release_ready' => $demoStatus['released'],
                'serving_status' => $demoStatus['released'] ? data_get($model, 'demo.status', 'unavailable') : 'review_locked',
                'visible_samples' => $demoStatus['released'] ? $demoStatus['approved_visible_fixtures'] : 0,
                'expected_visible_samples' => $demoStatus['expected_visible_fixtures'],
                'sorani_review' => $demoStatus['status'],
                'production_gate_bypassed' => false,
            ],
        ]);
    }
}
