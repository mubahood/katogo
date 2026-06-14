<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            // Only add columns that don't already exist
            if (!Schema::hasColumn('admin_users', 'job_title')) {
                $table->string('job_title', 120)->nullable()->after('bio');
            }
            if (!Schema::hasColumn('admin_users', 'employer')) {
                $table->string('employer', 150)->nullable();
            }
            if (!Schema::hasColumn('admin_users', 'industry')) {
                $table->string('industry', 100)->nullable();
            }
            if (!Schema::hasColumn('admin_users', 'linkedin_url')) {
                $table->string('linkedin_url', 255)->nullable();
            }
            if (!Schema::hasColumn('admin_users', 'professional_bio')) {
                $table->text('professional_bio')->nullable();
            }
            if (!Schema::hasColumn('admin_users', 'skills')) {
                $table->json('skills')->nullable()->comment('Array of skill strings');
            }
            if (!Schema::hasColumn('admin_users', 'experience_years')) {
                $table->tinyInteger('experience_years')->unsigned()->nullable();
            }
            if (!Schema::hasColumn('admin_users', 'education_level')) {
                $table->string('education_level', 60)->nullable()
                    ->comment('High School/Certificate/Diploma/Degree/Masters/PhD');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $cols = ['job_title','employer','industry','linkedin_url','professional_bio','skills','experience_years','education_level'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('admin_users', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
