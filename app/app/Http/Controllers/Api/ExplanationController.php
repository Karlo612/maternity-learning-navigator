<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoutingEvent;
use App\Services\MlClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExplanationController extends Controller
{
    public function __invoke(Request $request, string $requestId, MlClient $ml): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $event = RoutingEvent::query()->with('modelVersion')->where('request_id', $requestId)->firstOrFail();
        $fingerprint = hash_hmac(
            'sha256',
            mb_strtolower(trim($validated['question'])),
            (string) config('governance.question_hmac_key'),
        );
        abort_unless(hash_equals((string) $event->question_fingerprint, $fingerprint), 422, 'Question does not match the original request.');
        abort_unless($event->status === 'matched' && $event->modelVersion !== null, 409, 'No explanation is available for this routing event.');

        $explanation = $ml->explain(
            $validated['question'], $event->locale, $event->modelVersion->model_id, $event->modelVersion->version,
        );

        return response()->json([
            'request_id' => $requestId,
            'method' => 'LIME',
            'features' => collect($explanation['features'] ?? [])->take(8)->values(),
            'disclaimer' => 'These local feature weights explain topic routing only. They are approximate, non-causal and do not establish clinical correctness.',
        ]);
    }
}
