<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassificationRequest;
use App\Models\Category;
use App\Models\ModelVersion;
use App\Models\RoutingEvent;
use App\Services\MlClient;
use App\Services\ReleaseGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;

class ClassificationController extends Controller
{
    public function __invoke(ClassificationRequest $request, ReleaseGate $gate, MlClient $ml): JsonResponse
    {
        $started = hrtime(true);
        $requestId = (string) Str::uuid();
        $question = trim((string) $request->validated('question'));
        $locale = (string) $request->validated('locale');
        $release = $gate->status();

        if (! $release['free_text_enabled']) {
            $event = RoutingEvent::query()->create([
                'request_id' => $requestId,
                'mode' => 'production',
                'locale' => $locale,
                'status' => 'unsupported',
                'question_fingerprint' => $this->fingerprint($question),
                'latency_ms' => $this->elapsed($started),
            ]);

            return response()->json([
                'request_id' => $event->request_id,
                'status' => 'unsupported',
                'reason' => 'review_gate_pending',
                'message' => 'Free-text routing remains locked until the documented language and clinical-safety reviews are approved.',
                'release_gate' => $release,
                'resources' => [],
                'explanation_available' => false,
            ]);
        }

        try {
            $prediction = $ml->classify($question, $locale);
        } catch (Throwable) {
            return response()->json([
                'request_id' => $requestId,
                'status' => 'unsupported',
                'reason' => 'model_service_unavailable',
                'message' => 'The routing service is temporarily unavailable. No healthcare assessment has been made.',
                'resources' => [],
                'explanation_available' => false,
            ], 503);
        }

        $category = isset($prediction['category'])
            ? Category::query()->with(['sources', 'translations'])->where('slug', $prediction['category'])->first()
            : null;
        $model = ModelVersion::query()
            ->where('model_id', $prediction['model_id'] ?? '')
            ->where('version', $prediction['model_version'] ?? '')
            ->first();
        $status = in_array($prediction['status'] ?? '', ['matched', 'low_confidence', 'safety_bypass', 'unsupported'], true)
            ? $prediction['status']
            : 'unsupported';

        $event = RoutingEvent::query()->create([
            'request_id' => $requestId,
            'mode' => 'production',
            'category_id' => $category?->id,
            'model_version_id' => $model?->id,
            'locale' => $locale,
            'status' => $status,
            'routing_confidence' => $prediction['confidence'] ?? null,
            'confidence_band' => $prediction['confidence_band'] ?? null,
            'question_fingerprint' => $this->fingerprint($question),
            'latency_ms' => $this->elapsed($started),
        ]);

        $message = match ($status) {
            'safety_bypass' => (string) config("governance.safety_messages.{$locale}"),
            'low_confidence' => 'The router could not confidently match this question to one educational topic. No healthcare assessment has been made.',
            'unsupported' => 'This question is outside the released education router. No healthcare assessment has been made.',
            default => null,
        };

        return response()->json([
            'request_id' => $event->request_id,
            'status' => $status,
            'category' => $category ? [
                'slug' => $category->slug,
                'label' => $category->labelFor($locale),
            ] : null,
            'routing_confidence' => $event->routing_confidence,
            'confidence_band' => $event->confidence_band,
            'model' => $model ? ['id' => $model->model_id, 'version' => $model->version] : null,
            'message' => $message,
            'resources' => $category?->sources->map(fn ($source) => [
                'id' => $source->source_id,
                'organisation' => $source->organisation,
                'title' => $source->title,
                'url' => $source->url,
                'language' => $source->language,
                'last_verified' => $source->last_verified->toDateString(),
            ])->values() ?? [],
            'explanation_available' => $status === 'matched' && $model !== null,
        ]);
    }

    private function fingerprint(string $question): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($question)), (string) config('governance.question_hmac_key'));
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
