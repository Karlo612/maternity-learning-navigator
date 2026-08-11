<?php

namespace App\Services;

use App\Models\DatasetVersion;
use App\Models\ModelVersion;
use App\Models\ReviewSignoff;

class ReleaseGate
{
    public function status(): array
    {
        $required = collect(config('governance.required_signoffs'));
        $signoffs = ReviewSignoff::query()->whereIn('gate', $required)->get()->keyBy('gate');
        $gates = $required->map(function (string $gate) use ($signoffs): array {
            $signoff = $signoffs->get($gate);
            $complete = $signoff?->status === 'approved'
                && $signoff->reviewed_at !== null
                && filled($signoff->evidence_checksum);

            return [
                'gate' => $gate,
                'status' => $complete
                    ? 'approved'
                    : ($signoff?->status === 'approved' ? 'incomplete_evidence' : ($signoff?->status ?? 'missing')),
                'reviewer_role' => $signoff?->reviewer_role,
                'evidence_recorded' => $complete,
            ];
        })->values();

        $currentDataset = DatasetVersion::query()->latest('id')->first();
        $dataset = DatasetVersion::query()
            ->where('status', 'approved')
            ->where('eligible_rows', '>=', 600)
            ->whereNotNull('checksum')
            ->whereNotNull('released_at')
            ->latest('id')
            ->first();
        $currentModel = ModelVersion::query()->orderByDesc('serving_default')->latest('id')->first();
        $model = ModelVersion::query()
            ->where('status', 'approved')
            ->where('serving_default', true)
            ->whereNotNull('artifact_checksum')
            ->latest('id')
            ->first();
        $safetyMessagesReady = collect(['en', 'ckb'])->every(
            fn (string $locale): bool => filled(config("governance.safety_messages.{$locale}")),
        );

        $approved = (bool) config('governance.free_text_enabled')
            && $gates->every(fn (array $gate) => $gate['status'] === 'approved')
            && $dataset !== null
            && $model !== null
            && $safetyMessagesReady;

        return [
            'free_text_enabled' => $approved,
            'configured' => (bool) config('governance.free_text_enabled'),
            'gates' => $gates->all(),
            'dataset' => [
                'status' => $dataset?->status ?? ($currentDataset?->status ?? 'review_locked'),
                'version' => $dataset?->version ?? $currentDataset?->version,
                'eligible_rows' => $dataset?->eligible_rows ?? ($currentDataset?->eligible_rows ?? 0),
            ],
            'model' => [
                'status' => $model?->status ?? ($currentModel?->status ?? 'not_approved'),
                'id' => $model?->model_id ?? $currentModel?->model_id,
                'version' => $model?->version ?? $currentModel?->version,
            ],
            'reviewed_safety_messages' => $safetyMessagesReady ? 'configured' : 'missing',
        ];
    }
}
