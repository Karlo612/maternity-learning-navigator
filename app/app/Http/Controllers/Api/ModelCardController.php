<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModelVersion;
use App\Models\DemoSample;
use App\Models\ReviewSignoff;
use App\Services\ReleaseGate;
use Illuminate\Http\JsonResponse;

class ModelCardController extends Controller
{
    public function __invoke(ReleaseGate $gate): JsonResponse
    {
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
                'approved_visible_fixtures' => DemoSample::query()->where('split', 'visible')->where('review_status', 'approved')->count(),
                'approved_hidden_fixtures' => DemoSample::query()->where('split', 'hidden')->where('review_status', 'approved')->count(),
                'sorani_review_status' => ReviewSignoff::query()->where('gate', 'curated_demo_sorani')->value('status') ?? 'missing',
            ],
        ]);
    }
}
