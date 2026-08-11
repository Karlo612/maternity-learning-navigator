<?php

namespace App\GraphQL\Queries;

use App\Models\Category;
use App\Models\Source;

final class Resources
{
    public function __invoke(mixed $root, array $args): array
    {
        $query = Source::query()->orderBy('organisation')->orderBy('title');
        if (! empty($args['category'])) {
            $category = Category::query()->where('slug', $args['category'])->firstOrFail();
            $query->whereHas('categories', fn ($builder) => $builder->whereKey($category->id));
        }

        return $query->get()->map(fn (Source $source) => $this->map($source))->all();
    }

    private function map(Source $source): array
    {
        return [
            'code' => $source->source_id,
            'organisation' => $source->organisation,
            'title' => $source->title,
            'url' => $source->url,
            'language' => $source->language,
            'category' => $source->category,
            'reuseStatus' => $source->reuse_status,
            'lastVerified' => $source->last_verified->toDateString(),
        ];
    }
}
