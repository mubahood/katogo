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
            $table->string('is_imported')->default('No')->after('platform');
            $table->string('import_source')->nullable()->after('is_imported');
            $table->string('external_profile_url')->nullable()->unique()->after('import_source');
            $table->timestamp('imported_at')->nullable()->after('external_profile_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['is_imported', 'import_source', 'external_profile_url', 'imported_at']);
        });
    }
};
