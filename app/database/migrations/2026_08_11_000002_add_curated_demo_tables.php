<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_signoffs', function (Blueprint $table): void {
            $table->string('reviewer_name')->nullable()->after('gate');
        });

        Schema::create('demo_samples', function (Blueprint $table): void {
            $table->id();
            $table->string('sample_id')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_id')->constrained()->restrictOnDelete();
            $table->string('locale', 12);
            $table->text('question');
            $table->string('split', 12);
            $table->string('paraphrase_family_id');
            $table->string('authoring_method');
            $table->string('translation_status');
            $table->string('review_status');
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_role')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('content_checksum', 64);
            $table->timestamps();
            $table->index(['locale', 'split', 'review_status']);
        });

        Schema::table('routing_events', function (Blueprint $table): void {
            $table->string('mode', 24)->default('production')->after('request_id');
            $table->foreignId('demo_sample_id')->nullable()->after('mode')
                ->constrained('demo_samples')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('routing_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('demo_sample_id');
            $table->dropColumn('mode');
        });
        Schema::dropIfExists('demo_samples');
        Schema::table('review_signoffs', function (Blueprint $table): void {
            $table->dropColumn('reviewer_name');
        });
    }
};
