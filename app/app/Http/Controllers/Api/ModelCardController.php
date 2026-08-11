<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModelVersion;
use App\Services\CuratedDemoGate;
use App\Services\ReleaseGate;
use Illuminate\Http\JsonResponse;

class ModelCardController extends Controller
{
    public function __invoke(ReleaseGate $gate, CuratedDemoGate $demoGate): JsonResponse
    {
        $demoStatus = $demoGate->status();

        return response()->json([
            'intended_use' => 'Route general maternity learning questions to registered resources.',
            'excluded_uses' => ['diagnosis', 'triage', 'treatment', 'outcome prediction', 'generated clinical answers'],
            'models' => ModelVersion::query()->get([
                'model_id', 'version', 'role', 'status', 'metrics', 'limitations', 'serving_default',
            ]),
            'release_gate' => $gate->status(),
            'explanation' => 'LIME explains the routing model only and is not a clinical explanation.',
            'curated_demo' => [
                'intended_use' => 'Exercise the real PHP to Python to model to LIME flow using fixed, reviewed educational samples.',
                'excluded_uses' => ['arbitrary free text', 'clinical evaluation', 'general model-performance claims'],
                'release_ready' => $demoStatus['released'],
                'approved_visible_fixtures' => $demoStatus['released'] ? $demoStatus['approved_visible_fixtures'] : 0,
                'approved_hidden_fixtures' => $demoStatus['released'] ? $demoStatus['approved_hidden_fixtures'] : 0,
                'sorani_review_status' => $demoStatus['status'],
            ],
        ]);
    }
}
