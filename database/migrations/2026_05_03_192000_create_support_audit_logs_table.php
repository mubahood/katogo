<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('actor_id')->nullable()->index();
            $table->string('actor_role')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('entity_type')->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('description');
            $table->json('meta')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_audit_logs');
    }
};
