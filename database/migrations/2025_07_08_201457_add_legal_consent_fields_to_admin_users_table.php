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
            // Legal consent tracking fields
            $table->string('terms_of_service_accepted', 10)->nullable()->after('updated_at');
            $table->string('privacy_policy_accepted', 10)->nullable()->after('terms_of_service_accepted');
            $table->string('community_guidelines_accepted', 10)->nullable()->after('privacy_policy_accepted');
            $table->string('marketing_emails_consent', 10)->nullable()->after('community_guidelines_accepted');
            $table->string('data_processing_consent', 10)->nullable()->after('marketing_emails_consent');
            $table->string('content_moderation_consent', 10)->nullable()->after('data_processing_consent');
            
            // Date tracking for legal compliance
            $table->timestamp('terms_accepted_date')->nullable()->after('content_moderation_consent');
            $table->timestamp('privacy_accepted_date')->nullable()->after('terms_accepted_date');
            $table->timestamp('guidelines_accepted_date')->nullable()->after('privacy_accepted_date');
            
            // User preferences and settings  
            $table->string('notification_preferences', 10)->nullable()->after('guidelines_accepted_date');
            $table->string('push_notifications', 10)->nullable()->after('notification_preferences');
            $table->string('email_notifications', 10)->nullable()->after('push_notifications');
            $table->string('profile_visibility', 20)->default('Public')->after('email_notifications');
            $table->string('content_filtering', 10)->default('On')->after('profile_visibility');
            $table->string('safe_mode', 10)->default('On')->after('content_filtering');
            $table->string('location_sharing', 10)->nullable()->after('safe_mode');
            $table->string('analytics_consent', 10)->nullable()->after('location_sharing');
            $table->string('crash_reporting', 10)->nullable()->after('analytics_consent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
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
