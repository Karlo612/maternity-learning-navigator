<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MlClient
{
    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.ml.url'))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('services.ml.token'))
            ->timeout((int) config('services.ml.timeout_seconds'));
    }

    public function health(): array
    {
        return $this->client()->get('/v1/health')->throw()->json();
    }

    public function classify(string $question, string $locale): array
    {
        return $this->client()->post('/v1/classify', [
            'question' => $question,
            'locale' => $locale,
        ])->throw()->json();
    }

    public function explain(string $question, string $locale, string $modelId, string $modelVersion): array
    {
        return $this->client()->post('/v1/explain', [
            'question' => $question,
            'locale' => $locale,
            'model_id' => $modelId,
            'model_version' => $modelVersion,
        ])->throw()->json();
    }
}
