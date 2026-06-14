<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $add = [
                'preferred_quality'     => fn() => $table->enum('preferred_quality',
                    ['auto','240p','480p','720p','1080p'])->default('auto'),
                'data_saver_mode'       => fn() => $table->boolean('data_saver_mode')->default(false),
                'parental_pin'          => fn() => $table->string('parental_pin', 6)->nullable()
                                            ->comment('Bcrypt-hashed 4-digit safe mode PIN'),
                'subtitle_language'     => fn() => $table->string('subtitle_language', 10)->nullable()->default('en'),
                'autoplay_next'         => fn() => $table->boolean('autoplay_next')->default(true),
                'push_subscriptions'    => fn() => $table->boolean('push_subscriptions')->default(true),
                'push_new_movies'       => fn() => $table->boolean('push_new_movies')->default(true),
                'push_game_invites'     => fn() => $table->boolean('push_game_invites')->default(true),
                'push_expiry_reminders' => fn() => $table->boolean('push_expiry_reminders')->default(true),
                'preferred_payment'     => fn() => $table->string('preferred_payment', 30)->nullable()
                                            ->comment('pesapal|flutterwave|mtn|airtel'),
                'content_language'      => fn() => $table->string('content_language', 10)->nullable()->default('lg')
                                            ->comment('lg=Luganda, en=English'),
            ];

            foreach ($add as $col => $definition) {
                if (!Schema::hasColumn('admin_users', $col)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $cols = [
                'preferred_quality','data_saver_mode','parental_pin','subtitle_language',
                'autoplay_next','push_subscriptions','push_new_movies','push_game_invites',
                'push_expiry_reminders','preferred_payment','content_language',
            ];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('admin_users', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
    }
};
