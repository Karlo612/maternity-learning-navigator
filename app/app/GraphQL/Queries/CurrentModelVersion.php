<?php

namespace App\GraphQL\Queries;

use App\Models\ModelVersion;

final class CurrentModelVersion
{
    public function __invoke(mixed $root = null, array $args = []): ?array
    {
        $model = ModelVersion::query()->where('serving_default', true)->first()
            ?? ModelVersion::query()->orderBy('id')->first();
        if ($model === null) {
            return null;
        }

        return [
            'modelId' => $model->model_id,
            'version' => $model->version,
            'role' => $model->role,
            'status' => $model->status,
            'servingDefault' => $model->serving_default,
            'limitations' => $model->limitations,
        ];
    }
}
