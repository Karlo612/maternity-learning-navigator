<?php

namespace App\GraphQL\Queries;

use App\Models\Category;

final class Categories
{
    public function __invoke(mixed $root, array $args): array
    {
        $locale = in_array($args['locale'] ?? 'en', ['en', 'ckb'], true) ? $args['locale'] : 'en';

        return Category::query()->with('translations')->where('active', true)->orderBy('id')->get()
            ->map(fn (Category $category) => [
                'slug' => $category->slug,
                'label' => $category->labelFor($locale),
                'description' => $category->description_en,
            ])->all();
    }
}
