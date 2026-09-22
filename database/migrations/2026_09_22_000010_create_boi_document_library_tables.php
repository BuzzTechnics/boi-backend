<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Document Library for BOI intervention portals.
 *
 * A generic version of the customer document-exchange the funds need: a Project
 * Officer (working the BOI credit workflow) requests documents for an approved
 * case; the customer uploads them on the portal; each upload is a version, so a
 * re-supply after a return is preserved; the review outcome comes back and the
 * overall status is re-derived.
 *
 * Polymorphic on purpose — the "case" is an Application in one fund, a
 * LoanApplication in another — so the one engine serves every fund. The `app`
 * column keeps the funds apart when the tables are shared, exactly like the SLA
 * tables ({@see boi_sla_trackers}). Tables live on the portal's own database by
 * default, or on a named connection (boi-api's) via boi_document_library.connection.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('boi_document_library.connection');
    }

    public function up(): void
    {
        if (! Schema::connection($this->getConnection())->hasTable('boi_document_requests')) {
            Schema::connection($this->getConnection())->create('boi_document_requests', function (Blueprint $table) {
                $table->id();
                // Names the fund in shared tables, so one report can cover every fund.
                $table->string('app')->index();
                // The workflow's identifier for this request; unique within a fund.
                $table->string('reference');
                // The owning case (Application / LoanApplication / …). No FK: it may
                // live on a different connection from these tables.
                $table->nullableMorphs('documentable');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('stage')->default('default');
                $table->string('status')->default('requested');
                $table->unsignedBigInteger('project_officer_id')->nullable();
                $table->text('message')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->json('workflow_response')->nullable();
                $table->timestamps();

                $table->unique(['app', 'reference']);
                $table->index(['app', 'status']);
            });
        }

        if (! Schema::connection($this->getConnection())->hasTable('boi_documents')) {
            Schema::connection($this->getConnection())->create('boi_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('boi_document_request_id')->constrained('boi_document_requests')->cascadeOnDelete();
                $table->string('reference')->nullable();
                $table->string('name');
                $table->string('category')->nullable();
                $table->boolean('is_mandatory')->default(true);
                $table->text('instructions')->nullable();
                $table->string('permitted_formats')->nullable();
                $table->unsignedInteger('max_size_kb')->nullable();
                $table->string('status')->default('requested');
                $table->text('officer_comment')->nullable();
                $table->timestamps();

                $table->unique(['boi_document_request_id', 'reference'], 'boi_doc_request_reference_unique');
            });
        }

        if (! Schema::connection($this->getConnection())->hasTable('boi_document_versions')) {
            Schema::connection($this->getConnection())->create('boi_document_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('boi_document_id')->constrained('boi_documents')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->string('file_path');
                $table->string('original_name')->nullable();
                $table->unsignedInteger('size_kb')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->string('review_decision')->nullable();
                $table->text('review_comment')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['boi_document_id', 'version'], 'boi_doc_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('boi_document_versions');
        Schema::connection($this->getConnection())->dropIfExists('boi_documents');
        Schema::connection($this->getConnection())->dropIfExists('boi_document_requests');
    }
};
