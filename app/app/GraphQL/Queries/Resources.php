<?php

namespace App\GraphQL\Queries;

use App\Models\Category;
use App\Models\Source;

final class Resources
{
    public function __invoke(mixed $root, array $args): array
    {
        $locale = in_array($args['locale'] ?? 'en', ['en', 'ckb'], true) ? $args['locale'] : 'en';
        $query = Source::query()->orderBy('organisation')->orderBy('title');
        if (! empty($args['category'])) {
            $category = Category::query()->where('slug', $args['category'])->firstOrFail();
            $query->whereHas('categories', fn ($builder) => $builder->whereKey($category->id));
        }

        return $query->get()->map(fn (Source $source) => $this->map($source, $locale))->all();
    }

    private function map(Source $source, string $locale): array
    {
        $matches = $locale === 'ckb'
            ? str_contains($source->language, 'Sorani')
            : str_contains($source->language, 'English');

        return [
            'code' => $source->source_id,
            'organisation' => $source->organisation,
            'title' => $source->title,
            'url' => $source->url,
            'language' => $source->language,
            'requestedLocale' => $locale,
            'localeMatch' => $matches,
            'fallbackUsed' => ! $matches,
            'availabilityNote' => $matches
                ? 'Available in the requested language.'
                : 'The registered source is available in a different language; no translation is implied.',
            'category' => $source->category,
            'reuseStatus' => $source->reuse_status,
            'lastVerified' => $source->last_verified->toDateString(),
        ];
    }
}
