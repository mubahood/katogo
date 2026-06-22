<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('project_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('whatsapp_number');
            $table->string('country')->default('Uganda');
            $table->string('email')->nullable();
            $table->string('category'); // Mobile App, Web App, School System, etc.
            $table->text('details');
            $table->unsignedBigInteger('proposed_budget_ugx')->default(0);
            $table->string('status')->default('Pending'); // Pending, Under Discussion, Accepted, In Progress, Declined
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // if submitted while logged in
            $table->timestamps();
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('project_requests');
    }
};
