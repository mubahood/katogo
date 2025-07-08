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
        Schema::table('users', function (Blueprint $table) {
            // Legal and Agreement Fields
            $table->string('terms_of_service_accepted', 10)->nullable(); // "Yes" or "No"
            $table->string('privacy_policy_accepted', 10)->nullable(); // "Yes" or "No"
            $table->string('community_guidelines_accepted', 10)->nullable(); // "Yes" or "No"
            $table->string('marketing_emails_consent', 10)->nullable(); // "Yes" or "No"
            $table->string('data_processing_consent', 10)->nullable(); // "Yes" or "No"
            $table->string('content_moderation_consent', 10)->nullable(); // "Yes" or "No"
            $table->timestamp('terms_accepted_date')->nullable();
            $table->timestamp('privacy_accepted_date')->nullable();
            $table->timestamp('guidelines_accepted_date')->nullable();

            // Additional User Settings
            $table->string('notification_preferences', 10)->nullable(); // "Yes" or "No"
            $table->string('push_notifications', 10)->nullable(); // "Yes" or "No"
            $table->string('email_notifications', 10)->nullable(); // "Yes" or "No"
            $table->string('profile_visibility', 20)->default('Public'); // "Public", "Private", "Friends"
            $table->string('content_filtering', 10)->default('On'); // "On" or "Off"
            $table->string('safe_mode', 10)->default('On'); // "On" or "Off"
            $table->string('location_sharing', 10)->nullable(); // "Yes" or "No"
            $table->string('analytics_consent', 10)->nullable(); // "Yes" or "No"
            $table->string('crash_reporting', 10)->nullable(); // "Yes" or "No"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'terms_of_service_accepted',
                'privacy_policy_accepted',
                'community_guidelines_accepted',
                'marketing_emails_consent',
                'data_processing_consent',
                'content_moderation_consent',
                'terms_accepted_date',
                'privacy_accepted_date',
                'guidelines_accepted_date',
                'notification_preferences',
                'push_notifications',
                'email_notifications',
                'profile_visibility',
                'content_filtering',
                'safe_mode',
                'location_sharing',
                'analytics_consent',
                'crash_reporting'
            ]);
        });
    }
};
