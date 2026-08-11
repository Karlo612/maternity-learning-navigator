<?php

namespace App\GraphQL\Queries;

use App\Models\Source;

final class SourceByCode
{
    public function __invoke(mixed $root, array $args): ?array
    {
        $source = Source::query()->where('source_id', $args['code'])->first();
        if ($source === null) {
            return null;
        }

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
