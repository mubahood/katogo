<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN payment_status ENUM('Pending','Processing','Completed','Failed','Refunded','AwaitingPIN','CaptchaFailed') NOT NULL DEFAULT 'Pending'");
    }

    public function down(): void
    {
        // Revert captcha-specific rows before shrinking the ENUM
        DB::statement("UPDATE subscriptions SET payment_status = 'Processing' WHERE payment_status IN ('AwaitingPIN','CaptchaFailed')");
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN payment_status ENUM('Pending','Processing','Completed','Failed','Refunded') NOT NULL DEFAULT 'Pending'");
    }
};
