<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable()->index();   // desktop, mobile, tablet
            $table->string('os', 40)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('country', 60)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('referrer_url')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('page_url', 500);
            $table->string('button_clicked', 20)->nullable();         // android, ios, web
            $table->unsignedInteger('time_on_page_seconds')->nullable();
            $table->timestamp('landed_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('button_clicked');
            $table->index('country');
            $table->foreign('user_id')->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
