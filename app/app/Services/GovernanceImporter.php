<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\DatasetVersion;
use App\Models\DemoSample;
use App\Models\ModelVersion;
use App\Models\ReviewSignoff;
use App\Models\Source;
use Illuminate\Support\Carbon;
use JsonException;
use RuntimeException;

class GovernanceImporter
{
    private const CATEGORY_SOURCES = [
        'antenatal-appointments' => ['NHS-006'],
        'birth-place-choices' => ['NHS-005'],
        'labour-preparation' => ['NHS-001', 'NHS-002', 'NHS-003'],
        'pain-relief-information' => ['NHS-004'],
        'after-birth-postnatal' => ['NHS-007', 'NHS-008', 'NHS-009'],
        'feeding-support' => ['NHS-010', 'NHS-011'],
    ];

    /** @throws JsonException */
    public function import(): void
    {
        $resources = rtrim((string) config('governance.resources_path'), '/');
        $demoReview = $this->readJson((string) config('governance.demo_review_path'));
        $catalog = $this->readJson((string) config('governance.interface_catalog_path'));
        $translationApproved = ($demoReview['decision'] ?? null) === 'approved';

        foreach ($catalog['en']['categories'] as $slug => $english) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['label_en' => $english['label'], 'description_en' => $english['description'], 'active' => true],
            );
            $sorani = $catalog['ckb']['categories'][$slug] ?? null;
            if (is_array($sorani)) {
                CategoryTranslation::query()->updateOrCreate(
                    ['category_id' => $category->id, 'locale' => 'ckb'],
                    [
                        'label' => $sorani['label'],
                        'description' => $sorani['description'],
                        'review_status' => $translationApproved ? 'approved' : 'pending',
                        'reviewed_at' => $translationApproved ? $demoReview['reviewed_at'] : null,
                    ],
                );
            }
        }

        $this->importSources($resources.'/source_registry.csv');
        $this->importDatasetState($resources.'/question_candidates.csv');
        $this->connectCategorySources();
        $this->importDemoSamples($resources.'/demo_samples.csv');
        $this->importModelDecisions();
        $this->importSignoffs((string) config('governance.signoffs_path'));
    }

    private function importSources(string $path): void
    {
        foreach ($this->readCsv($path) as $row) {
            Source::query()->updateOrCreate(
                ['source_id' => $row['source_id']],
                collect($row)->except('source_id')->all(),
            );
        }
    }

    private function importDatasetState(string $path): void
    {
        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new RuntimeException("Unable to checksum governed dataset at {$path}");
        }
        $rows = $this->readCsv($path);
        $eligible = collect($rows)->where('training_eligible', 'true')->count();
        DatasetVersion::query()->updateOrCreate(
            ['version' => 'registry-'.substr($checksum, 0, 12)],
            [
                'checksum' => $checksum,
                'eligible_rows' => $eligible,
                'status' => 'review_locked',
                'metrics' => ['candidate_rows' => count($rows), 'required_training_rows' => 600],
            ],
        );
    }

    private function connectCategorySources(): void
    {
        foreach (self::CATEGORY_SOURCES as $slug => $sourceIds) {
            $category = Category::query()->where('slug', $slug)->firstOrFail();
            $sync = Source::query()->whereIn('source_id', $sourceIds)->get()
                ->mapWithKeys(fn (Source $source, int $index) => [$source->id => ['display_order' => $index]])
                ->all();
            $category->sources()->sync($sync);
        }
    }

    private function importDemoSamples(string $path): void
    {
        foreach ($this->readCsv($path) as $row) {
            $category = Category::query()->where('slug', $row['category'])->firstOrFail();
            $source = Source::query()->where('source_id', $row['source_id'])->firstOrFail();
            DemoSample::query()->updateOrCreate(
                ['sample_id' => $row['sample_id']],
                [
                    'category_id' => $category->id,
                    'source_id' => $source->id,
                    'locale' => $row['locale'],
                    'question' => $row['question'],
                    'split' => $row['split'],
                    'paraphrase_family_id' => $row['paraphrase_family_id'],
                    'authoring_method' => $row['authoring_method'],
                    'translation_status' => $row['translation_status'],
                    'review_status' => $row['review_status'],
                    'reviewer_name' => $row['reviewer_name'] ?: null,
                    'reviewer_role' => $row['reviewer_role'] ?: null,
                    'reviewed_at' => $row['reviewed_at'] ? Carbon::parse($row['reviewed_at']) : null,
                    'content_checksum' => hash('sha256', implode("\x1f", [
                        $row['sample_id'], $row['locale'], $row['question'], $row['category'], $row['source_id'],
                    ])),
                ],
            );
        }
    }

    private function importModelDecisions(): void
    {
        ModelVersion::query()->updateOrCreate(
            ['model_id' => 'demo-tfidf-logreg', 'version' => 'review-pending'],
            [
                'role' => 'Curated bilingual portfolio demonstration router',
                'status' => 'not_trained',
                'limitations' => 'Fixed reviewed samples only. Fixture checks will not be presented as a general accuracy estimate.',
                'serving_default' => false,
            ],
        );
        ModelVersion::query()->updateOrCreate(
            ['model_id' => 'baseline-tfidf-logreg', 'version' => 'review-gated'],
            [
                'role' => 'Production multilingual routing baseline',
                'status' => 'not_trained',
                'limitations' => 'No project-specific performance claim is permitted until reviewed bilingual production data passes the release gates.',
                'serving_default' => false,
            ],
        );
        ModelVersion::query()->updateOrCreate(
            ['model_id' => 'xlm-roberta-base', 'version' => 'revision-pending'],
            [
                'role' => 'Multilingual transformer candidate',
                'status' => 'not_trained',
                'limitations' => 'Published Sorani use does not establish maternity-domain validity. Training and serving remain locked pending review.',
                'serving_default' => false,
            ],
        );
    }

    /** @throws JsonException */
    private function importSignoffs(string $path): void
    {
        $document = $this->readJson($path);
        foreach ($document['signoffs'] ?? [] as $signoff) {
            ReviewSignoff::query()->updateOrCreate(
                ['gate' => $signoff['gate']],
                [
                    'reviewer_name' => $signoff['reviewer_name'] ?? null,
                    'reviewer_role' => $signoff['reviewer_role'],
                    'status' => $signoff['status'],
                    'evidence_checksum' => $signoff['evidence_checksum'] ?? null,
                    'reviewed_at' => ! empty($signoff['reviewed_at']) ? Carbon::parse($signoff['reviewed_at']) : null,
                ],
            );
        }
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to read governed CSV at {$path}");
        }
        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new RuntimeException("Governed CSV is empty at {$path}");
        }
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $values);
            if ($row === false) {
                throw new RuntimeException("Governed CSV contains a malformed row at {$path}");
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** @throws JsonException */
    private function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read governed JSON at {$path}");
        }

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
