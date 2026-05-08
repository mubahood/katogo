<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('merged_accounts')) {
            return;
        }

        Schema::create('merged_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('source_user_id')->index();
            $table->unsignedBigInteger('target_user_id')->index();

            $table->string('source_email')->nullable();
            $table->string('source_phone_number', 40)->nullable();
            $table->string('target_email')->nullable();
            $table->string('target_phone_number', 40)->nullable();

            $table->string('match_type', 50)->default('phone_or_email');
            $table->string('merge_reason', 120)->nullable();

            $table->json('source_permissions')->nullable();
            $table->json('target_permissions')->nullable();
            $table->longText('source_snapshot')->nullable();
            $table->longText('target_snapshot')->nullable();

            $table->string('request_ip', 45)->nullable();
            $table->text('request_user_agent')->nullable();

            $table->string('status', 30)->default('completed')->index();
            $table->string('sync_mode', 40)->default('bidirectional_permissions');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('merged_at')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['source_user_id', 'target_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merged_accounts');
    }
};
