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

        $locale = (string) $request->input('locale', 'en');

        return response()->json(['requested_locale' => $locale, 'data' => $query->get()->map(fn (Source $source) => [
            'id' => $source->source_id,
            'organisation' => $source->organisation,
            'title' => $source->title,
            'url' => $source->url,
            'language' => $source->language,
            'locale_match' => $this->matchesLocale($source->language, $locale),
            'fallback_used' => ! $this->matchesLocale($source->language, $locale),
            'availability_note' => $this->matchesLocale($source->language, $locale)
                ? 'Available in the requested language.'
                : 'The registered source is available in a different language; no translation is implied.',
            'category' => $source->category,
            'reuse_status' => $source->reuse_status,
            'last_verified' => $source->last_verified->toDateString(),
        ])]);
    }

    private function matchesLocale(string $language, string $locale): bool
    {
        return $locale === 'ckb'
            ? str_contains($language, 'Sorani')
            : str_contains($language, 'English');
    }
}
