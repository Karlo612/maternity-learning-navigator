<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemoSample;
use App\Models\ReviewSignoff;
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
            'curated_demo' => [
                'mode' => 'curated_demo',
                'serving_status' => data_get($model, 'demo.status', 'unavailable'),
                'visible_samples' => DemoSample::query()->where('split', 'visible')->where('review_status', 'approved')->count(),
                'expected_visible_samples' => 12,
                'sorani_review' => ReviewSignoff::query()->where('gate', 'curated_demo_sorani')->value('status') ?? 'missing',
                'production_gate_bypassed' => false,
            ],
        ]);
    }
}
