<?php

namespace Tests\Feature;

use App\Models\DatasetVersion;
use App\Models\ModelVersion;
use App\Models\ReviewSignoff;
use App\Models\RoutingEvent;
use App\Services\GovernanceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NavigatorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('governance.resources_path', dirname(__DIR__, 3).'/resources');
        app(GovernanceImporter::class)->import();
    }

    public function test_resource_directory_imports_only_registered_source_metadata(): void
    {
        $response = $this->getJson('/api/v1/resources');

        $response->assertOk()->assertJsonCount(24, 'data');
        $response->assertJsonPath('data.0.organisation', fn (string $value) => $value !== '');
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
}
