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
        Schema::table('admin_users', function (Blueprint $table) {
            // Notification tracking fields to prevent spam
            $table->timestamp('last_trending_notification_sent')->nullable()->comment('When was the last trending notification sent to this user');
            $table->string('last_trending_notification_period', 20)->nullable()->comment('Last notification period: morning, afternoon, evening, night');
            $table->date('last_trending_notification_date')->nullable()->comment('Date of last trending notification');
            $table->integer('trending_notifications_today')->default(0)->comment('Count of trending notifications sent today');
            $table->integer('max_trending_notifications_per_day')->default(4)->comment('Maximum trending notifications per day (1 per period)');
            
            // Index for performance on notification queries
            $table->index(['last_trending_notification_date', 'trending_notifications_today'], 'idx_notification_tracking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropIndex('idx_notification_tracking');
            $table->dropColumn([
                'last_trending_notification_sent',
                'last_trending_notification_period', 
                'last_trending_notification_date',
                'trending_notifications_today',
                'max_trending_notifications_per_day'
            ]);
        });
    }
};
