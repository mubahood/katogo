<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * customer_tickets — one ticket per user (1-to-1 ongoing support thread).
     *
     * status values:
     *   'open'       — newly created, waiting for support response
     *   'pending'    — support replied, waiting for user response
     *   'in_progress'— actively being worked on
     *   'resolved'   — issue resolved, still open for user to confirm
     *   'closed'     — ticket fully closed
     *   'escalated'  — escalated to admin
     */
    public function up(): void
    {
        Schema::create('customer_tickets', function (Blueprint $table) {
            $table->id();

            // The user who owns this ticket
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('admin_users')->onDelete('cascade');

            // Ticket metadata
            $table->string('status')->default('open')
                ->comment('open | pending | in_progress | resolved | closed | escalated');
            $table->string('subject')->nullable()
                ->comment('Optional subject/title the user may provide');

            // Context flags
            $table->string('account_origin')->nullable()
                ->comment('auto_device | manual | google — mirrors user account_origin at ticket creation time');
            $table->string('app_type')->nullable()
                ->comment('lugaflix | ugflix | muno_app — which app this ticket came from');
            $table->string('platform')->nullable()
                ->comment('android | ios | web');

            // Assignment
            $table->unsignedInteger('assigned_to')->nullable()
                ->comment('FK to admin_users — support team member assigned to this ticket');
            $table->foreign('assigned_to')->references('id')->on('admin_users')->onDelete('set null');

            // Tracking
            $table->timestamp('last_reply_at')->nullable();
            $table->unsignedInteger('reply_count')->default(0);
            $table->boolean('has_unread_user')->default(false)
                ->comment('True when support replied and user has not read it');
            $table->boolean('has_unread_support')->default(false)
                ->comment('True when user replied and support has not read it');

            $table->timestamps();

            // Indexes for fast lookups
            $table->index('user_id');
            $table->index('status');
            $table->index('assigned_to');
            $table->index('last_reply_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tickets');
    }
};
