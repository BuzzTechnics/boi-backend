<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared SLA engine's three tables.
 *
 * Prefixed boi_sla_ so a portal that already has its own SLA tables — the online
 * portal has `trackers` and `sla_configurations` — can adopt this without a
 * collision and migrate across in its own time.
 *
 * boi_sla_definitions is the configurable half: what each stage is called, how long
 * it has, and who hears about it at each escalation level. A portal can run purely
 * from config; seeding this table is what puts those values in front of a business
 * owner in Nova instead of in a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boi_sla_definitions')) {
            Schema::create('boi_sla_definitions', function (Blueprint $table) {
                $table->id();
                // Set on shared installations so one row set can serve several funds;
                // null means "this portal", which is how a single app uses it.
                $table->string('app', 64)->nullable();
                $table->string('case_type', 64);
                $table->string('name');
                $table->text('description')->nullable();

                // Working minutes. Kept as minutes rather than days so a four-hour
                // assignment SLA and a three-day review are the same kind of thing.
                $table->integer('sla_minutes');
                $table->integer('assignment_minutes')->nullable();

                // Role names, resolved by the portal's own directory. Null falls back
                // to config, so a portal only stores what it wants people to edit.
                $table->json('owner_roles')->nullable();
                $table->json('level_1_roles')->nullable();
                $table->json('level_2_roles')->nullable();
                $table->json('level_3_roles')->nullable();

                // Overrides the 75/100/150/200 defaults for this case type only.
                $table->json('thresholds')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['app', 'case_type']);
            });
        }

        if (! Schema::hasTable('boi_sla_trackers')) {
            Schema::create('boi_sla_trackers', function (Blueprint $table) {
                $table->id();
                $table->string('app', 64)->nullable();
                // 'owner' is the clock on whoever holds the task; 'assignment' is the
                // clock on a pool before anyone holds it at all.
                $table->string('type', 32)->default('owner');
                $table->string('case_type', 64);
                $table->unsignedBigInteger('case_id');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->timestamp('start_time');
                $table->timestamp('deadline')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('status', 32)->default('in_progress');
                $table->text('notes')->nullable();

                // One stamp per level: at a quarter-hourly cadence, a stage past 150%
                // would otherwise escalate four times an hour until someone acted.
                $table->timestamp('reminder_sent_at')->nullable();
                $table->timestamp('escalation1_sent_at')->nullable();
                $table->timestamp('escalation2_sent_at')->nullable();
                $table->timestamp('escalation3_sent_at')->nullable();
                $table->timestamps();

                $table->index(['app', 'type', 'case_type', 'case_id']);
                $table->index('status');
                $table->index('deadline');
            });
        }

        if (! Schema::hasTable('boi_sla_notification_logs')) {
            Schema::create('boi_sla_notification_logs', function (Blueprint $table) {
                $table->id();
                $table->string('app', 64)->nullable();
                $table->unsignedBigInteger('tracker_id')->nullable();
                $table->string('case_type', 64)->nullable();
                $table->unsignedBigInteger('case_id')->nullable();
                $table->string('notification_type', 32);
                $table->string('channel', 64);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('recipient_name')->nullable();
                $table->string('recipient_email')->nullable();
                $table->string('status', 16);
                $table->text('error')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tracker_id', 'created_at']);
                $table->index(['notification_type', 'created_at']);
                $table->index(['status', 'created_at']);
                $table->index('recipient_email');
            });
        }
    }

    public function down(): void
    {
        // The trail is deliberately one-way (BRD §5.4-03: audit records protected
        // against deletion). Definitions and trackers go; the log stays.
        Schema::dropIfExists('boi_sla_trackers');
        Schema::dropIfExists('boi_sla_definitions');
    }
};
