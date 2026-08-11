<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label_en');
            $table->text('description_en');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('review_status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->unique(['category_id', 'locale']);
        });

        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->string('source_id')->unique();
            $table->string('organisation');
            $table->string('title');
            $table->text('url');
            $table->string('language', 40);
            $table->string('category');
            $table->string('authority');
            $table->string('reuse_status');
            $table->text('allowed_use');
            $table->date('last_verified');
            $table->timestamps();
        });

        Schema::create('category_source', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->primary(['category_id', 'source_id']);
        });

        Schema::create('dataset_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version')->unique();
            $table->string('checksum')->nullable();
            $table->unsignedInteger('eligible_rows')->default(0);
            $table->string('status')->default('review_locked');
            $table->json('metrics')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('model_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('model_id');
            $table->string('version');
            $table->string('role');
            $table->string('status')->default('not_trained');
            $table->string('artifact_checksum')->nullable();
            $table->json('metrics')->nullable();
            $table->text('limitations');
            $table->boolean('serving_default')->default(false);
            $table->timestamps();
            $table->unique(['model_id', 'version']);
        });

        Schema::create('review_signoffs', function (Blueprint $table): void {
            $table->id();
            $table->string('gate')->unique();
            $table->string('reviewer_role');
            $table->string('status')->default('pending');
            $table->string('evidence_checksum')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('routing_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('model_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 12);
            $table->string('status');
            $table->decimal('routing_confidence', 6, 5)->nullable();
            $table->string('confidence_band')->nullable();
            $table->string('question_fingerprint', 64)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamps();
        });

        Schema::create('feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('routing_event_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->string('reason_code')->nullable();
            $table->timestamps();
            $table->unique('routing_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('routing_events');
        Schema::dropIfExists('review_signoffs');
        Schema::dropIfExists('model_versions');
        Schema::dropIfExists('dataset_versions');
        Schema::dropIfExists('category_source');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('categories');
    }
};
