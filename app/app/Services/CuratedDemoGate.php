<?php

namespace App\Services;

use App\Models\DemoSample;
use App\Models\ReviewSignoff;

class CuratedDemoGate
{
    private const EXPECTED_TOTAL = 144;

    private const EXPECTED_VISIBLE = 12;

    private const EXPECTED_HIDDEN = 12;

    private const EXPECTED_SORANI = 72;

    public function status(): array
    {
        $signoff = ReviewSignoff::query()->where('gate', 'curated_demo_sorani')->first();
        $approved = DemoSample::query()->where('review_status', 'approved');
        $approvedTotal = (clone $approved)->count();
        $approvedVisible = (clone $approved)->where('split', 'visible')->count();
        $approvedHidden = (clone $approved)->where('split', 'hidden')->count();
        $approvedSorani = (clone $approved)
            ->where('locale', 'ckb')
            ->where('translation_status', 'human_reviewed')
            ->count();
        $signoffComplete = $signoff?->status === 'approved'
            && $signoff->reviewer_name !== null
            && $signoff->reviewer_role !== null
            && $signoff->reviewed_at !== null
            && is_string($signoff->evidence_checksum)
            && preg_match('/^[a-f0-9]{64}$/', $signoff->evidence_checksum) === 1;
        $released = $signoffComplete
            && $approvedTotal === self::EXPECTED_TOTAL
            && $approvedVisible === self::EXPECTED_VISIBLE
            && $approvedHidden === self::EXPECTED_HIDDEN
            && $approvedSorani === self::EXPECTED_SORANI;

        return [
            'released' => $released,
            'status' => $released ? 'approved' : ($signoff?->status ?? 'missing'),
            'reason' => $released ? null : 'review_gate_pending',
            'approved_samples' => $approvedTotal,
            'approved_visible_fixtures' => $approvedVisible,
            'approved_hidden_fixtures' => $approvedHidden,
            'approved_sorani_samples' => $approvedSorani,
            'expected_samples' => self::EXPECTED_TOTAL,
            'expected_visible_fixtures' => self::EXPECTED_VISIBLE,
            'expected_hidden_fixtures' => self::EXPECTED_HIDDEN,
            'expected_sorani_samples' => self::EXPECTED_SORANI,
        ];
    }

    public function released(): bool
    {
        return $this->status()['released'];
    }
}
