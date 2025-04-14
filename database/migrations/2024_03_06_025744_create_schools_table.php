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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('name')->nullable();
            $table->text('district')->nullable();
            $table->text('county')->nullable();
            $table->text('sub_county')->nullable();
            $table->text('parish')->nullable();
            $table->text('address')->nullable();
            $table->text('p_o_box')->nullable();
            $table->text('email')->nullable();
            $table->text('website')->nullable();
            $table->text('phone')->nullable();
            $table->text('fax')->nullable();
            $table->text('school_type')->nullable();
            $table->text('service_code')->nullable();
            $table->text('reg_no')->nullable();
            $table->text('center_no')->nullable();
            $table->text('operation_status')->nullable();
            $table->text('founder')->nullable();
            $table->text('funder')->nullable();
            $table->text('boys_girls')->nullable();
            $table->text('day_boarding')->nullable();
            $table->text('registry_status')->nullable();
            $table->text('nearest_school')->nullable();
            $table->text('nearest_school_distance')->nullable();
            $table->integer('founding_year')->nullable();
            $table->string('level')->nullable();
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->text('highest_class')->nullable();
            $table->text('access')->nullable();
            $table->text('details')->nullable(); 
            $table->string('has_email')->nullable();
            $table->string('has_website')->nullable();
            $table->string('has_phone')->nullable();
            $table->string('contated')->nullable()->default('No');
            $table->string('replied')->nullable()->default('No');
            $table->string('success')->nullable()->default('No');
            $table->string('reply_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
