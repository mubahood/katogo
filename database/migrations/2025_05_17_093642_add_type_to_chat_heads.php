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
        Schema::table('chat_heads', function (Blueprint $table) {
            $table->string('type')->default('dating')->nullable();
            $table->integer('sender_unread_count')->default(0)->nullable();
            $table->integer('receiver_unread_count')->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_heads', function (Blueprint $table) {
            //
        });
    }
};
