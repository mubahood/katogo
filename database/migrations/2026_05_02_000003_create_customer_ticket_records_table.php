<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * customer_ticket_records — individual messages/interactions inside a ticket.
     *
     * sender_type:
     *   'user'         — message from the app user
     *   'support_team' — reply from a support team member
     *   'system'       — automated system message (e.g. status change notification)
     *
     * action_type:
     *   'none'              — plain informational message
     *   'needs_user_action' — support asks user to do something (e.g. send screenshot)
     *   'needs_support_action' — internal flag: support team needs to follow up
     *   'status_change'     — record marking a status transition
     */
    public function up(): void
    {
        Schema::create('customer_ticket_records', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_ticket_id');
            $table->foreign('customer_ticket_id')->references('id')->on('customer_tickets')->onDelete('cascade');

            // Who sent this record
            $table->string('sender_type')
                ->comment('user | support_team | system');
            $table->unsignedInteger('sender_id')->nullable()
                ->comment('FK to admin_users — null for system messages');

            // Content
            $table->text('message');

            // Action flags
            $table->string('action_type')->default('none')
                ->comment('none | needs_user_action | needs_support_action | status_change');
            $table->string('action_description')->nullable()
                ->comment('Human-readable description of what action is needed');

            // Internal notes (only visible to support team / admin — hidden from user)
            $table->boolean('is_internal_note')->default(false);

            // Attachment (optional — URL or base64 reference)
            $table->string('attachment_url')->nullable();

            // Read receipts
            $table->boolean('is_read_by_user')->default(false);
            $table->boolean('is_read_by_support')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('customer_ticket_id');
            $table->index(['customer_ticket_id', 'created_at']);
            $table->index('sender_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ticket_records');
    }
};
