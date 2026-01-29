<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix foreign keys to reference admin_users instead of users
     * Also fix column type mismatch (admin_users.id is int, not bigint)
     */
    public function up(): void
    {
        // Check and drop existing foreign keys if they exist
        $existingFks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'coin_transactions' AND TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
        $fkNames = array_map(fn($fk) => $fk->CONSTRAINT_NAME, $existingFks);
        
        if (in_array('coin_transactions_user_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE coin_transactions DROP FOREIGN KEY coin_transactions_user_id_foreign');
        }
        if (in_array('coin_transactions_related_user_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE coin_transactions DROP FOREIGN KEY coin_transactions_related_user_id_foreign');
        }
        
        // Change column types to match admin_users.id (integer, not bigint)
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->change();
            $table->unsignedInteger('related_user_id')->nullable()->change();
        });
        
        // Add correct foreign keys referencing admin_users
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('admin_users')->onDelete('cascade');
            $table->foreign('related_user_id')->references('id')->on('admin_users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existingFks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'coin_transactions' AND TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
        $fkNames = array_map(fn($fk) => $fk->CONSTRAINT_NAME, $existingFks);
        
        if (in_array('coin_transactions_user_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE coin_transactions DROP FOREIGN KEY coin_transactions_user_id_foreign');
        }
        if (in_array('coin_transactions_related_user_id_foreign', $fkNames)) {
            DB::statement('ALTER TABLE coin_transactions DROP FOREIGN KEY coin_transactions_related_user_id_foreign');
        }
        
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
            $table->unsignedBigInteger('related_user_id')->nullable()->change();
        });
        
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('related_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
