<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_ticket_records', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_ticket_records', 'show_to_customer')) {
                $table->boolean('show_to_customer')
                    ->default(true)
                    ->after('is_internal_note')
                    ->comment('If false, record is hidden from customer app UI');
            }

            if (!Schema::hasColumn('customer_ticket_records', 'customer_seen')) {
                $table->boolean('customer_seen')
                    ->default(false)
                    ->after('is_read_by_user')
                    ->comment('Explicit customer seen state for customer-visible records');
            }

            if (!Schema::hasColumn('customer_ticket_records', 'customer_seen_at')) {
                $table->timestamp('customer_seen_at')
                    ->nullable()
                    ->after('customer_seen')
                    ->comment('Timestamp when customer first opened/seen this record');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_ticket_records', function (Blueprint $table) {
            if (Schema::hasColumn('customer_ticket_records', 'customer_seen_at')) {
                $table->dropColumn('customer_seen_at');
            }
            if (Schema::hasColumn('customer_ticket_records', 'customer_seen')) {
                $table->dropColumn('customer_seen');
            }
            if (Schema::hasColumn('customer_ticket_records', 'show_to_customer')) {
                $table->dropColumn('show_to_customer');
            }
        });
    }
};
