<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\RoutingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'uuid'],
            'helpful' => ['required', 'boolean'],
            'reason_code' => ['nullable', 'in:wrong_topic,could_not_find_resource,explanation_unclear'],
            'comments' => ['prohibited'],
        ]);
        $event = RoutingEvent::query()->where('request_id', $validated['request_id'])->firstOrFail();
        Feedback::query()->updateOrCreate(
            ['routing_event_id' => $event->id],
            ['helpful' => $validated['helpful'], 'reason_code' => $validated['reason_code'] ?? null],
        );

        return response()->json(['stored' => true]);
    }
}
