<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maker-checker requests to change an application's status (e.g. reversing a
 * decline). The row is the audit record: who asked, why, the state it was
 * raised against, and who approved/rejected it.
 *
 * pending_key is set only while a request is pending and is unique, so at most
 * one pending request per (type, application) can exist — enforced by the
 * database even when two makers submit at the same moment. NULLs are not
 * compared by unique indexes (Postgres, SQLite, MySQL), so processed rows
 * never collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('status_change_requests')) {
            return;
        }

        Schema::create('status_change_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('requestable');
            $table->string('type', 64);                 // e.g. 'decline_reversal'
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->string('from_status', 64);
            $table->string('to_status', 64);
            $table->text('reason');                     // maker's justification
            $table->json('payload')->nullable();        // values applied on approval
            $table->json('snapshot')->nullable();       // application state when requested
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->string('pending_key', 191)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('requested_by');
            $table->index('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_change_requests');
    }
};
