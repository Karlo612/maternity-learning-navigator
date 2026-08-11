<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModelVersion;
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
        ]);
    }
}
