<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_tickets', 'ticket_type')) {
                $table->string('ticket_type')->default('general')->after('status')
                    ->comment('general | account_opening | payment_thanks | payment_fail | auto_account_issue | subscription_issue | technical_issue | billing_issue | content_issue');
            }

            if (!Schema::hasColumn('customer_tickets', 'resolution_state')) {
                $table->string('resolution_state')->default('unresolved')->after('ticket_type')
                    ->comment('unresolved | resolved | cancelled');
            }

            if (!Schema::hasColumn('customer_tickets', 'platform_type')) {
                $table->string('platform_type')->nullable()->after('app_type')
                    ->comment('lugaflix | luga | ugflix | muno | muno_app');
            }

            if (!Schema::hasColumn('customer_tickets', 'rating_of_satisfaction')) {
                $table->unsignedTinyInteger('rating_of_satisfaction')->nullable()->after('reply_count')
                    ->comment('Optional 1-5 rating from customer once issue is handled');
            }

            if (!Schema::hasColumn('customer_tickets', 'agent_has_contacted_customer')) {
                $table->boolean('agent_has_contacted_customer')->default(false)->after('rating_of_satisfaction');
            }

            if (!Schema::hasColumn('customer_tickets', 'customer_has_responded')) {
                $table->boolean('customer_has_responded')->default(false)->after('agent_has_contacted_customer');
            }
        });

        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->index('ticket_type');
            $table->index('resolution_state');
        });
    }

    public function down(): void
    {
        Schema::table('customer_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('customer_tickets', 'ticket_type')) {
                $table->dropColumn('ticket_type');
            }
            if (Schema::hasColumn('customer_tickets', 'resolution_state')) {
                $table->dropColumn('resolution_state');
            }
            if (Schema::hasColumn('customer_tickets', 'platform_type')) {
                $table->dropColumn('platform_type');
            }
            if (Schema::hasColumn('customer_tickets', 'rating_of_satisfaction')) {
                $table->dropColumn('rating_of_satisfaction');
            }
            if (Schema::hasColumn('customer_tickets', 'agent_has_contacted_customer')) {
                $table->dropColumn('agent_has_contacted_customer');
            }
            if (Schema::hasColumn('customer_tickets', 'customer_has_responded')) {
                $table->dropColumn('customer_has_responded');
            }
        });

        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->dropIndex(['ticket_type']);
            $table->dropIndex(['resolution_state']);
        });
    }
};
