<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bvn_nin_calls')) {
            return;
        }

        Schema::create('bvn_nin_calls', function (Blueprint $table) {
            $table->id();
            $table->string('project', 64);
            $table->string('kind', 8);                 // 'bvn' | 'nin'
            $table->string('identifier_masked', 32)->nullable();
            $table->string('method', 10);
            $table->string('endpoint', 1024);
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('exception_class', 255)->nullable();
            $table->boolean('succeeded')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project', 'created_at']);
            $table->index(['kind', 'created_at']);
            $table->index(['succeeded', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bvn_nin_calls');
    }
};
