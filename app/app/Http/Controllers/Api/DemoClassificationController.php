<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemoSample;
use App\Models\ModelVersion;
use App\Models\RoutingEvent;
use App\Services\CuratedDemoGate;
use App\Services\MlClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class DemoClassificationController extends Controller
{
    public function __invoke(Request $request, MlClient $ml, CuratedDemoGate $gate): JsonResponse
    {
        $started = hrtime(true);
        $validated = $request->validate([
            'sample_id' => ['required', 'string', 'max:80'],
            'question' => ['prohibited'],
            'locale' => ['prohibited'],
        ]);
        if (! $gate->released()) {
            return response()->json([
                'demo_only' => true,
                'status' => 'unsupported',
                'reason' => 'review_gate_pending',
                'message' => 'The fixed demonstration samples remain unavailable until the exact reviewed checksums and release sign-off agree.',
            ], 409);
        }
        $sample = DemoSample::query()->with(['category.sources', 'category.translations'])
            ->where('sample_id', $validated['sample_id'])
            ->where('split', 'visible')
            ->where('review_status', 'approved')
            ->when(
                str_starts_with($validated['sample_id'], 'ckb-'),
                fn ($builder) => $builder->where('translation_status', 'human_reviewed'),
            )
            ->firstOrFail();
        $requestId = (string) Str::uuid();

        try {
            $prediction = $ml->classifyDemo($sample->question, $sample->locale);
        } catch (RequestException $error) {
            return $this->serviceError($requestId, $error->response?->status() === 409 ? 409 : 503);
        } catch (Throwable) {
            return $this->serviceError($requestId, 503);
        }

        if (($prediction['demo_only'] ?? false) !== true || ($prediction['category'] ?? null) !== $sample->category->slug) {
            return response()->json([
                'request_id' => $requestId,
                'demo_only' => true,
                'status' => 'unsupported',
                'reason' => 'reviewed_fixture_mismatch',
                'message' => 'The fixed sample did not match its reviewed category, so no result was released.',
            ], 409);
        }

        $model = ModelVersion::query()->updateOrCreate(
            ['model_id' => $prediction['model_id'], 'version' => $prediction['model_version']],
            [
                'role' => 'Curated bilingual portfolio demonstration router',
                'status' => 'demo_approved',
                'metrics' => ['intended_mode' => 'curated_demo', 'fixture_only' => true],
                'limitations' => 'Fixed reviewed samples only. Fixture checks are not a general accuracy estimate.',
                'serving_default' => false,
            ],
        );
        $event = RoutingEvent::query()->create([
            'request_id' => $requestId,
            'mode' => 'curated_demo',
            'demo_sample_id' => $sample->id,
            'category_id' => $sample->category_id,
            'model_version_id' => $model->id,
            'locale' => $sample->locale,
            'status' => 'matched',
            'routing_confidence' => $prediction['confidence'] ?? null,
            'confidence_band' => $prediction['confidence_band'] ?? null,
            'question_fingerprint' => null,
            'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
        ]);

        return response()->json([
            'request_id' => $event->request_id,
            'demo_only' => true,
            'status' => 'matched',
            'sample' => [
                'sample_id' => $sample->sample_id,
                'question' => $sample->question,
                'category' => ['slug' => $sample->category->slug, 'label' => $sample->category->labelFor($sample->locale)],
                'content_checksum' => $sample->content_checksum,
            ],
            'category' => [
                'slug' => $sample->category->slug,
                'label' => $sample->category->labelFor($sample->locale),
            ],
            'routing_confidence' => $event->routing_confidence,
            'confidence_band' => $event->confidence_band,
            'model' => ['id' => $model->model_id, 'version' => $model->version],
            'resources' => $sample->category->sources->map(fn ($source) => [
                'id' => $source->source_id,
                'organisation' => $source->organisation,
                'title' => $source->title,
                'url' => $source->url,
                'language' => $source->language,
                'requested_locale' => $sample->locale,
                'fallback_used' => $sample->locale === 'ckb' && ! str_contains($source->language, 'Sorani'),
                'last_verified' => $source->last_verified->toDateString(),
            ])->values(),
            'explanation_available' => true,
        ]);
    }

    private function serviceError(string $requestId, int $status): JsonResponse
    {
        return response()->json([
            'request_id' => $requestId,
            'demo_only' => true,
            'status' => 'unsupported',
            'reason' => $status === 409 ? 'model_version_conflict' : 'model_service_unavailable',
            'message' => 'The demonstration model is unavailable, so no routing result was released.',
        ], $status);
    }
}
