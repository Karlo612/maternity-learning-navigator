<?php

namespace App\Services;

use App\Models\Category;
use App\Models\DatasetVersion;
use App\Models\ModelVersion;
use App\Models\ReviewSignoff;
use App\Models\Source;
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

    private const CATEGORIES = [
        ['antenatal-appointments', 'Antenatal appointments', 'Find registered information about antenatal care and appointments.'],
        ['birth-place-choices', 'Birth-place choices', 'Find registered information about options to discuss with a maternity team.'],
        ['labour-preparation', 'Labour preparation', 'Find registered learning resources about labour and birth preparation.'],
        ['pain-relief-information', 'Pain-relief information', 'Find registered resources describing pain-relief information.'],
        ['after-birth-postnatal', 'After birth and postnatal', 'Find registered information about the period after birth and postnatal checks.'],
        ['feeding-support', 'Feeding support', 'Find registered breastfeeding and bottle-feeding support resources.'],
    ];

    public function import(): void
    {
        foreach (self::CATEGORIES as [$slug, $label, $description]) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['label_en' => $label, 'description_en' => $description, 'active' => true],
            );
        }

        $path = rtrim((string) config('governance.resources_path'), '/').'/source_registry.csv';
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to read governance source registry at {$path}");
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new RuntimeException('The source registry is empty.');
        }

        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $values);
            if ($row === false) {
                throw new RuntimeException('The source registry contains a malformed row.');
            }

            Source::query()->updateOrCreate(
                ['source_id' => $row['source_id']],
                collect($row)->except('source_id')->all(),
            );
        }
        fclose($handle);

        $datasetPath = rtrim((string) config('governance.resources_path'), '/').'/question_candidates.csv';
        $datasetChecksum = hash_file('sha256', $datasetPath);
        if ($datasetChecksum === false) {
            throw new RuntimeException("Unable to checksum governed dataset at {$datasetPath}");
        }
        $datasetHandle = fopen($datasetPath, 'rb');
        if ($datasetHandle === false) {
            throw new RuntimeException("Unable to read governed dataset at {$datasetPath}");
        }
        $datasetHeaders = fgetcsv($datasetHandle);
        if ($datasetHeaders === false) {
            throw new RuntimeException('The governed dataset is empty.');
        }
        $eligibleRows = 0;
        $candidateRows = 0;
        while (($values = fgetcsv($datasetHandle)) !== false) {
            $row = array_combine($datasetHeaders, $values);
            $candidateRows++;
            if ($row !== false && ($row['training_eligible'] ?? 'false') === 'true') {
                $eligibleRows++;
            }
        }
        fclose($datasetHandle);
        DatasetVersion::query()->firstOrCreate(
            ['version' => 'registry-'.substr($datasetChecksum, 0, 12)],
            [
                'checksum' => $datasetChecksum,
                'eligible_rows' => $eligibleRows,
                'status' => 'review_locked',
                'metrics' => ['candidate_rows' => $candidateRows, 'required_training_rows' => 600],
            ],
        );

        foreach (self::CATEGORY_SOURCES as $slug => $sourceIds) {
            $category = Category::query()->where('slug', $slug)->firstOrFail();
            $sync = Source::query()
                ->whereIn('source_id', $sourceIds)
                ->get()
                ->mapWithKeys(fn (Source $source, int $index) => [$source->id => ['display_order' => $index]])
                ->all();
            $category->sources()->sync($sync);
        }

        ModelVersion::query()->firstOrCreate(
            ['model_id' => 'baseline-tfidf-logreg', 'version' => 'review-gated'],
            [
                'role' => 'Interpretable multilingual routing baseline',
                'status' => 'not_trained',
                'limitations' => 'No project-specific performance claim is permitted until reviewed bilingual data passes the release gates.',
                'serving_default' => false,
            ],
        );
        ModelVersion::query()->firstOrCreate(
            ['model_id' => 'xlm-roberta-base', 'version' => 'revision-pending'],
            [
                'role' => 'Multilingual transformer candidate',
                'status' => 'not_trained',
                'limitations' => 'Published Sorani use does not establish maternity-domain validity. Training and serving remain locked pending review.',
                'serving_default' => false,
            ],
        );

        ReviewSignoff::query()->firstOrCreate(
            ['gate' => 'sorani_language'],
            ['reviewer_role' => 'Qualified Sorani language reviewer', 'status' => 'pending'],
        );
        ReviewSignoff::query()->firstOrCreate(
            ['gate' => 'clinical_safety'],
            ['reviewer_role' => 'Qualified clinical-safety reviewer', 'status' => 'pending'],
        );
    }
}
