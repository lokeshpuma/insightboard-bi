<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('file_checksum_sha256', 64);
            $table->string('delimiter', 10)->default(',');
            $table->string('encoding', 20)->default('UTF-8');
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedInteger('column_count')->default(0);
            $table->string('status')->default('uploaded'); // uploaded, processing, ready, failed, archived
            $table->json('analysis_summary')->nullable();
            $table->timestamp('last_profiled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('dataset_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->string('field_key');
            $table->string('original_name');
            $table->string('display_name');
            $table->string('inferred_type'); // string, integer, decimal, boolean, date, datetime, identifier
            $table->string('semantic_role')->default('dimension'); // dimension, measure, date, identifier
            $table->unsignedInteger('ordinal_position')->default(0);
            $table->unsignedBigInteger('null_count')->default(0);
            $table->unsignedBigInteger('distinct_count')->nullable();
            $table->json('statistics')->nullable();
            $table->json('sample_values')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'field_key']);
            $table->index(['dataset_id', 'ordinal_position']);
        });

        Schema::create('dataset_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('row_data');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['dataset_id', 'row_number']);
            $table->index(['dataset_id', 'id']);
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued, processing, completed, failed, cancelled
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('total_rows')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('dataset_rows');
        Schema::dropIfExists('dataset_columns');
        Schema::dropIfExists('datasets');
    }
};
