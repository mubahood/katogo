<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('admin_users')->onDelete('cascade');

            $table->unsignedBigInteger('customer_ticket_id')->nullable();
            $table->foreign('customer_ticket_id')->references('id')->on('customer_tickets')->onDelete('set null');

            $table->string('status')->default('submitted')
                ->comment('submitted | reviewing | in_progress | fulfilled | rejected | cancelled');
            $table->string('request_source')->default('search')
                ->comment('search | support | manual');

            $table->string('platform_type')->nullable()
                ->comment('lugaflix | luga | ugflix | muno | muno_app');
            $table->string('app_type')->nullable()
                ->comment('lugaflix | ugflix | muno_app');

            $table->string('searched_query')->nullable();
            $table->json('requested_movies');
            $table->text('user_message')->nullable();

            $table->text('support_reply')->nullable();
            $table->timestamp('support_reply_at')->nullable();

            $table->unsignedInteger('handled_by')->nullable();
            $table->foreign('handled_by')->references('id')->on('admin_users')->onDelete('set null');

            $table->timestamps();

            $table->index('user_id');
            $table->index('customer_ticket_id');
            $table->index('status');
            $table->index('request_source');
            $table->index('platform_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_requests');
    }
};
