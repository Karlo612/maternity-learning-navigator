<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemoSample;
use App\Models\ReviewSignoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoSampleController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['locale' => ['required', 'in:en,ckb']]);
        $locale = $validated['locale'];
        $samples = DemoSample::query()->with(['category.translations'])
            ->recruiterVisible($locale)
            ->orderBy('category_id')
            ->get();
        $review = ReviewSignoff::query()->where('gate', 'curated_demo_sorani')->first();

        return response()->json([
            'demo_only' => true,
            'locale' => $locale,
            'review_status' => $samples->isNotEmpty() ? 'approved' : ($review?->status ?? 'changes_required'),
            'data' => $samples->map(fn (DemoSample $sample) => [
                'sample_id' => $sample->sample_id,
                'question' => $sample->question,
                'category' => [
                    'slug' => $sample->category->slug,
                    'label' => $sample->category->labelFor($locale),
                ],
                'content_checksum' => $sample->content_checksum,
            ])->values(),
        ]);
    }
}
