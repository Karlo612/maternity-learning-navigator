<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoutingEvent;
use App\Services\MlClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DemoExplanationController extends Controller
{
    public function __invoke(Request $request, string $requestId, MlClient $ml): JsonResponse
    {
        $request->validate([
            'question' => ['prohibited'],
            'sample_id' => ['prohibited'],
        ]);
        $event = RoutingEvent::query()->with(['demoSample', 'category', 'modelVersion'])
            ->where('request_id', $requestId)
            ->where('mode', 'curated_demo')
            ->firstOrFail();
        abort_unless(
            $event->status === 'matched' && $event->demoSample !== null && $event->modelVersion !== null,
            409,
            'No explanation is available for this demo routing event.',
        );

        try {
            $explanation = $ml->explainDemo(
                $event->demoSample->question,
                $event->locale,
                $event->modelVersion->model_id,
                $event->modelVersion->version,
            );
        } catch (RequestException $error) {
            return $this->serviceError($requestId, $error->response?->status() === 409 ? 409 : 503);
        } catch (Throwable) {
            return $this->serviceError($requestId, 503);
        }

        $predicted = $explanation['predicted_class'] ?? null;
        $explained = $explanation['explained_class'] ?? null;
        if (($explanation['demo_only'] ?? false) !== true || $predicted !== $explained || $predicted !== $event->category?->slug) {
            return response()->json([
                'request_id' => $requestId,
                'demo_only' => true,
                'reason' => 'explained_class_mismatch',
                'message' => 'The explanation did not match the released routing class and was rejected.',
            ], 409);
        }

        return response()->json([
            'request_id' => $requestId,
            'demo_only' => true,
            'method' => 'LIME',
            'sample' => [
                'sample_id' => $event->demoSample->sample_id,
                'question' => $event->demoSample->question,
                'category' => [
                    'slug' => $event->category->slug,
                    'label' => $event->category->labelFor($event->locale),
                ],
                'content_checksum' => $event->demoSample->content_checksum,
            ],
            'predicted_class' => $predicted,
            'explained_class' => $explained,
            'probability' => $explanation['probability'] ?? null,
            'model' => ['id' => $event->modelVersion->model_id, 'version' => $event->modelVersion->version],
            'sampling' => [
                'random_seed' => $explanation['random_seed'] ?? 41,
                'num_samples' => $explanation['num_samples'] ?? 1000,
                'max_features' => 8,
            ],
            'features' => collect($explanation['features'] ?? [])->take(8)->values(),
            'disclaimer' => 'Local feature influence is approximate and non-causal. It explains topic routing, not medical correctness.',
        ]);
    }

    private function serviceError(string $requestId, int $status): JsonResponse
    {
        return response()->json([
            'request_id' => $requestId,
            'demo_only' => true,
            'reason' => $status === 409 ? 'model_version_conflict' : 'model_service_unavailable',
            'message' => 'The requested demonstration model version is unavailable.',
        ], $status);
    }
}
