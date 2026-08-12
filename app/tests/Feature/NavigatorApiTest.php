<?php

namespace Tests\Feature;

use App\Models\DatasetVersion;
use App\Models\DemoSample;
use App\Models\ModelVersion;
use App\Models\ReviewSignoff;
use App\Models\RoutingEvent;
use App\Services\GovernanceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class NavigatorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('governance.resources_path', dirname(__DIR__, 3).'/resources');
        $hotFile = storage_path('framework/testing.vite.hot');
        file_put_contents($hotFile, 'http://127.0.0.1:5173');
        Vite::useHotFile($hotFile);
        app(GovernanceImporter::class)->import();
    }

    public function test_resource_directory_imports_only_registered_source_metadata(): void
    {
        $response = $this->getJson('/api/v1/resources');

        $response->assertOk()->assertJsonCount(19, 'data');
        $response->assertJsonPath('data.0.organisation', fn (string $value) => $value !== '');
    }

    public function test_human_readable_api_documentation_page_is_not_shadowed_by_api_routes(): void
    {
        $this->get('/api-docs')->assertOk();
        $this->get('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('info.title', 'Maternity Learning Navigator API');
    }

    public function test_forwarded_https_scheme_is_trusted_for_cloud_asset_urls(): void
    {
        $request = Request::create('http://navigator.test');
        $request->server->set('REMOTE_ADDR', '10.0.0.10');
        $request->headers->set('X-Forwarded-Proto', 'https');

        $scheme = app(TrustProxies::class)->handle(
            $request,
            fn (Request $proxiedRequest) => $proxiedRequest->getScheme(),
        );

        $this->assertSame('https', $scheme);
    }

    public function test_openapi_documents_every_live_demo_contract_and_failure_state(): void
    {
        $document = $this->getJson('/api/v1/openapi.json')->assertOk()->json();

        foreach (['/demo/samples', '/demo/classifications', '/demo/classifications/{requestId}/explanation'] as $path) {
            $this->assertArrayHasKey($path, $document['paths']);
        }
        foreach (['200', '404', '409', '422', '429', '503'] as $status) {
            $this->assertArrayHasKey(
                $status,
                $document['paths']['/demo/classifications']['post']['responses'],
            );
        }
        $this->assertTrue($document['components']['schemas']['DemoClassificationInput']['additionalProperties'] === false);
        $this->assertContains('reason', $document['components']['schemas']['DemoSampleList']['required']);
        $this->assertSame(8, $document['components']['schemas']['DemoExplanation']['properties']['features']['maxItems']);
    }

    public function test_free_text_fails_closed_while_human_reviews_are_pending(): void
    {
        config()->set('governance.free_text_enabled', true);

        $response = $this->postJson('/api/v1/classifications', [
            'question' => 'What happens at antenatal appointments?',
            'locale' => 'en',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'unsupported')
            ->assertJsonPath('reason', 'review_gate_pending')
            ->assertJsonPath('explanation_available', false);
        $this->assertDatabaseMissing('routing_events', ['status' => 'matched']);
        $this->assertFalse(collect(Schema::getColumnListing('routing_events'))->contains('question'));
    }

    public function test_curated_samples_are_visible_after_exact_content_review_is_approved(): void
    {
        $this->getJson('/api/v1/demo/samples?locale=ckb')
            ->assertOk()
            ->assertJsonPath('demo_only', true)
            ->assertJsonPath('review_status', 'approved')
            ->assertJsonPath('reason', null)
            ->assertJsonCount(6, 'data');
    }

    public function test_curated_demo_fails_closed_when_rows_and_release_signoff_disagree(): void
    {
        DemoSample::query()->update([
            'review_status' => 'approved',
            'reviewer_name' => 'Test reviewer',
            'reviewer_role' => 'Test fixture reviewer',
            'reviewed_at' => now(),
        ]);
        DemoSample::query()->where('locale', 'ckb')->update(['translation_status' => 'human_reviewed']);
        ReviewSignoff::query()->where('gate', 'curated_demo_sorani')->update([
            'status' => 'changes_required',
            'evidence_checksum' => null,
            'reviewed_at' => null,
        ]);
        $sample = DemoSample::query()->where('locale', 'en')->where('split', 'visible')->firstOrFail();

        $this->getJson('/api/v1/demo/samples?locale=en')
            ->assertOk()
            ->assertJsonPath('reason', 'review_gate_pending')
            ->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/demo/classifications', ['sample_id' => $sample->sample_id])
            ->assertConflict()
            ->assertJsonPath('reason', 'review_gate_pending');
        $this->assertDatabaseCount('routing_events', 0);
    }

    public function test_approved_manifest_derives_review_state_without_mutating_signed_csv(): void
    {
        $dataset = dirname(__DIR__, 3).'/resources/demo_samples.csv';
        $interface = base_path('resources/js/interface-catalog.json');
        $reviewPath = tempnam(sys_get_temp_dir(), 'demo-review-');
        $signoffsPath = tempnam(sys_get_temp_dir(), 'demo-signoffs-');
        $reviewedAt = '2026-08-11T12:00:00+01:00';
        $review = [
            'schema_version' => 1,
            'decision' => 'approved',
            'reviewer_name' => 'Karlo Nahro',
            'reviewer_role' => 'Native Sorani speaker and professional translator',
            'reviewed_at' => $reviewedAt,
            'dataset_file' => 'resources/demo_samples.csv',
            'dataset_sha256' => hash_file('sha256', $dataset),
            'interface_file' => 'app/resources/js/interface-catalog.json',
            'interface_sha256' => hash_file('sha256', $interface),
            'decision_notes' => 'Exact immutable content approved for the curated portfolio demonstration.',
        ];
        file_put_contents($reviewPath, json_encode($review, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $signoffs = json_decode(file_get_contents(dirname(__DIR__, 3).'/governance/review_signoffs.json'), true);
        foreach ($signoffs['signoffs'] as &$signoff) {
            if ($signoff['gate'] !== 'curated_demo_sorani') {
                continue;
            }
            $signoff = array_merge($signoff, [
                'status' => 'approved',
                'reviewer_name' => $review['reviewer_name'],
                'reviewer_role' => $review['reviewer_role'],
                'reviewed_at' => $reviewedAt,
                'evidence_file' => 'governance/demo_review_manifest.json',
                'evidence_checksum' => hash_file('sha256', $reviewPath),
            ]);
        }
        unset($signoff);
        file_put_contents($signoffsPath, json_encode($signoffs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        config()->set('governance.demo_review_path', $reviewPath);
        config()->set('governance.signoffs_path', $signoffsPath);

        app(GovernanceImporter::class)->import();

        $this->assertSame(144, DemoSample::query()->where('review_status', 'approved')->count());
        $this->assertSame(72, DemoSample::query()->where('locale', 'ckb')->where('translation_status', 'human_reviewed')->count());
        $this->assertSame(144, DemoSample::query()->where('reviewer_name', 'Karlo Nahro')->count());
        $this->getJson('/api/v1/demo/samples?locale=ckb')
            ->assertOk()
            ->assertJsonPath('review_status', 'approved')
            ->assertJsonCount(6, 'data');

        unlink($reviewPath);
        unlink($signoffsPath);
    }

    public function test_demo_classification_accepts_only_an_approved_visible_sample_id(): void
    {
        $sample = $this->approveVisibleSample('en');
        Http::fake([
            '*/v1/demo/classify' => Http::response([
                'demo_only' => true,
                'status' => 'matched',
                'category' => $sample->category->slug,
                'confidence' => .88,
                'confidence_band' => 'high',
                'model_id' => 'demo-tfidf-logreg',
                'model_version' => 'fixture-version',
            ]),
        ]);

        $this->postJson('/api/v1/demo/classifications', [
            'sample_id' => $sample->sample_id,
            'question' => 'This arbitrary question must be rejected.',
        ])->assertUnprocessable();

        $response = $this->postJson('/api/v1/demo/classifications', ['sample_id' => $sample->sample_id]);
        $response->assertOk()
            ->assertJsonPath('demo_only', true)
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('sample.sample_id', $sample->sample_id)
            ->assertJsonPath('category.slug', $sample->category->slug)
            ->assertJsonPath('explanation_available', true);

        $event = RoutingEvent::query()->firstOrFail();
        $this->assertSame('curated_demo', $event->mode);
        $this->assertSame($sample->id, $event->demo_sample_id);
        $this->assertNull($event->question_fingerprint);
        $this->assertFalse(collect(Schema::getColumnListing('routing_events'))->contains('question'));
    }

    public function test_demo_explanation_is_bound_to_its_classification_and_never_persisted(): void
    {
        $sample = $this->approveVisibleSample('en');
        Http::fake([
            '*/v1/demo/classify' => Http::response([
                'demo_only' => true,
                'status' => 'matched',
                'category' => $sample->category->slug,
                'confidence' => .88,
                'confidence_band' => 'high',
                'model_id' => 'demo-tfidf-logreg',
                'model_version' => 'fixture-version',
            ]),
            '*/v1/demo/explain' => Http::response([
                'demo_only' => true,
                'predicted_class' => $sample->category->slug,
                'explained_class' => $sample->category->slug,
                'probability' => .88,
                'random_seed' => 41,
                'num_samples' => 1000,
                'features' => [[
                    'token' => 'appointment',
                    'weight' => .42,
                    'direction' => 'supporting',
                    'occurrences' => [['start' => 21, 'end' => 32]],
                ]],
            ]),
        ]);
        $classification = $this->postJson('/api/v1/demo/classifications', ['sample_id' => $sample->sample_id]);
        $requestId = $classification->json('request_id');

        $this->postJson("/api/v1/demo/classifications/{$requestId}/explanation", [
            'question' => $sample->question,
        ])->assertUnprocessable();

        $this->postJson("/api/v1/demo/classifications/{$requestId}/explanation")
            ->assertOk()
            ->assertJsonPath('demo_only', true)
            ->assertJsonPath('predicted_class', $sample->category->slug)
            ->assertJsonPath('explained_class', $sample->category->slug)
            ->assertJsonPath('sampling.random_seed', 41)
            ->assertJsonPath('features.0.direction', 'supporting');

        $this->assertDatabaseCount('routing_events', 1);
        $this->assertFalse(collect(Schema::getColumnListing('routing_events'))->contains('lime_tokens'));
    }

    public function test_demo_model_service_failure_has_an_explicit_unavailable_contract(): void
    {
        $sample = $this->approveVisibleSample('en');
        Http::fake(['*/v1/demo/classify' => Http::response(['detail' => 'Demo artifact unavailable'], 503)]);

        $this->postJson('/api/v1/demo/classifications', ['sample_id' => $sample->sample_id])
            ->assertServiceUnavailable()
            ->assertJsonPath('demo_only', true)
            ->assertJsonPath('reason', 'model_service_unavailable');

        $this->assertDatabaseCount('routing_events', 0);
    }

    public function test_approved_gate_calls_private_model_and_stores_derived_metadata_only(): void
    {
        config()->set('governance.free_text_enabled', true);
        config()->set('governance.safety_messages', [
            'en' => 'Independently reviewed English safety message.',
            'ckb' => 'independently-reviewed-ckb-message',
        ]);
        ReviewSignoff::query()->update([
            'status' => 'approved',
            'evidence_checksum' => str_repeat('a', 64),
            'reviewed_at' => now(),
        ]);
        DatasetVersion::query()->update([
            'status' => 'approved',
            'eligible_rows' => 600,
            'released_at' => now(),
        ]);
        ModelVersion::query()->where('model_id', 'baseline-tfidf-logreg')->update([
            'status' => 'approved',
            'artifact_checksum' => str_repeat('b', 64),
            'serving_default' => true,
        ]);
        Http::fake([
            '*/v1/classify' => Http::response([
                'status' => 'matched',
                'category' => 'antenatal-appointments',
                'confidence' => .91,
                'confidence_band' => 'high',
                'model_id' => 'baseline-tfidf-logreg',
                'model_version' => 'review-gated',
            ]),
        ]);

        $question = 'What happens at antenatal appointments?';
        $response = $this->postJson('/api/v1/classifications', ['question' => $question, 'locale' => 'en']);

        $response->assertOk()
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('category.slug', 'antenatal-appointments')
            ->assertJsonCount(1, 'resources');
        $event = RoutingEvent::query()->firstOrFail();
        $this->assertNotSame($question, $event->question_fingerprint);
        $this->assertSame(64, strlen((string) $event->question_fingerprint));
    }

    public function test_graphql_is_read_only_and_returns_registered_categories(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => '{ categories(locale: "en") { slug label } }',
        ]);

        $response->assertOk()->assertJsonCount(6, 'data.categories');
        $this->assertStringNotContainsString('mutation', file_get_contents(base_path('graphql/schema.graphql')));
    }

    public function test_rest_and_graphql_report_language_fallback_explicitly(): void
    {
        $this->getJson('/api/v1/resources?category=antenatal-appointments&locale=ckb')
            ->assertOk()
            ->assertJsonPath('requested_locale', 'ckb')
            ->assertJsonPath('data.0.fallback_used', true)
            ->assertJsonPath('data.0.locale_match', false);

        $this->postJson('/graphql', [
            'query' => '{ resources(category: "antenatal-appointments", locale: "ckb") { code requestedLocale localeMatch fallbackUsed } }',
        ])->assertOk()
            ->assertJsonPath('data.resources.0.requestedLocale', 'ckb')
            ->assertJsonPath('data.resources.0.fallbackUsed', true);
    }

    public function test_feedback_rejects_free_text_fields(): void
    {
        $event = RoutingEvent::query()->create([
            'request_id' => fake()->uuid(), 'locale' => 'en', 'status' => 'unsupported', 'latency_ms' => 1,
        ]);

        $this->postJson('/api/v1/feedback', [
            'request_id' => $event->request_id,
            'helpful' => false,
            'reason_code' => 'wrong_topic',
            'comments' => 'This must never be accepted or stored.',
        ])->assertUnprocessable();

        $this->assertFalse(collect(Schema::getColumnListing('feedback'))->contains('comments'));
    }

    public function test_cors_is_restricted_to_configured_origin(): void
    {
        config()->set('cors.allowed_origins', ['https://maternity.example.test']);

        $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/resources')
            ->assertHeader('Access-Control-Allow-Origin', 'https://maternity.example.test')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $this->withHeaders([
            'Origin' => 'https://maternity.example.test',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/resources')
            ->assertHeader('Access-Control-Allow-Origin', 'https://maternity.example.test');
    }

    private function approveVisibleSample(string $locale): DemoSample
    {
        DemoSample::query()->update([
            'review_status' => 'approved',
            'reviewer_name' => 'Test reviewer',
            'reviewer_role' => 'Test fixture reviewer',
            'reviewed_at' => now(),
        ]);
        DemoSample::query()->where('locale', 'ckb')->update(['translation_status' => 'human_reviewed']);
        ReviewSignoff::query()->where('gate', 'curated_demo_sorani')->update([
            'status' => 'approved',
            'reviewer_name' => 'Test reviewer',
            'reviewer_role' => 'Test fixture reviewer',
            'reviewed_at' => now(),
            'evidence_checksum' => str_repeat('c', 64),
        ]);
        $sample = DemoSample::query()->with('category')->where('locale', $locale)->where('split', 'visible')->firstOrFail();

        return $sample->fresh('category');
    }
}
