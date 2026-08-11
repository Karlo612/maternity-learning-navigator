<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['category' => ['nullable', 'string'], 'locale' => ['nullable', 'in:en,ckb']]);
        $query = Source::query()->orderBy('organisation')->orderBy('title');

        if ($request->filled('category')) {
            $category = Category::query()->where('slug', $request->string('category'))->firstOrFail();
            $query->whereHas('categories', fn ($builder) => $builder->whereKey($category->id));
        }

        return response()->json(['data' => $query->get()->map(fn (Source $source) => [
            'id' => $source->source_id,
            'organisation' => $source->organisation,
            'title' => $source->title,
            'url' => $source->url,
            'language' => $source->language,
            'category' => $source->category,
            'reuse_status' => $source->reuse_status,
            'last_verified' => $source->last_verified->toDateString(),
        ])]);
    }
}
