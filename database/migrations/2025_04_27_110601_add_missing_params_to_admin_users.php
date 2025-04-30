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
            // Profile & Bio
            $table->json('profile_photos')->nullable();
            $table->text('bio')->nullable();
            $table->string('tagline')->nullable();
            $table->string('phone_country_name')->nullable();
            $table->string('phone_country_code')->nullable();
            $table->string('phone_country_international')->nullable();

            // Demographics
            $table->string('sexual_orientation')->nullable();
            $table->integer('height_cm')->nullable();
            $table->string('body_type')->nullable();

            // Location & Status
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('last_online_at')->nullable();
            $table->boolean('online_status')->nullable();

            // Matching Preferences
            $table->json('looking_for')->nullable();
            $table->json('interested_in')->nullable();
            $table->integer('age_range_min')->nullable();
            $table->integer('age_range_max')->nullable();
            $table->integer('max_distance_km')->nullable();

            // Lifestyle & Habits
            $table->string('smoking_habit')->nullable();
            $table->string('drinking_habit')->nullable();
            $table->string('pet_preference')->nullable();
            $table->string('religion')->nullable();
            $table->string('political_views')->nullable();
            $table->json('languages_spoken')->nullable();

            // Education & Work
            $table->string('education_level')->nullable();
            $table->string('occupation')->nullable();

            // Account & Security
            $table->boolean('email_verified')->nullable();
            $table->boolean('phone_verified')->nullable();
            $table->string('verification_code')->nullable();
            $table->integer('failed_login_attempts')->nullable();
            $table->dateTime('last_password_change')->nullable();

            // Monetization
            $table->string('subscription_tier')->nullable();
            $table->dateTime('subscription_expires')->nullable();
            $table->integer('credits_balance')->nullable();

            // Analytics & Matching Stats
            $table->integer('profile_views')->nullable();
            $table->integer('likes_received')->nullable();
            $table->integer('matches_count')->nullable();
            $table->integer('completed_profile_pct')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photos',
                'bio',
                'tagline',
                'phone_country_name',
                'phone_country_code',
                'phone_country_international',
                'sexual_orientation',
                'height_cm',
                'body_type',
                'country',
                'state',
                'city',
                'latitude',
                'longitude',
                'last_online_at',
                'online_status',
                'looking_for',
                'interested_in',
                'age_range_min',
                'age_range_max',
                'max_distance_km',
                'smoking_habit',
                'drinking_habit',
                'pet_preference',
                'religion',
                'political_views',
                'languages_spoken',
                'education_level',
                'occupation',
                'email_verified',
                'phone_verified',
                'verification_code',
                'failed_login_attempts',
                'last_password_change',
                'subscription_tier',
                'subscription_expires',
                'credits_balance',
                'profile_views',
                'likes_received',
                'matches_count',
                'completed_profile_pct',
            ]);
        });
    }
};
