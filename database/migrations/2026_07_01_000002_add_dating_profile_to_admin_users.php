<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $add = [
                'relationship_status' => fn() => $table->enum('relationship_status', [
                    'Single','Taken','Married','Divorced','Widowed','Complicated'
                ])->nullable(),
                'date_of_birth'      => fn() => $table->date('date_of_birth')->nullable(),
                'district'           => fn() => $table->string('district', 80)->nullable()
                                          ->comment('Uganda district e.g. Kampala, Wakiso, Mbarara'),
                'region'             => fn() => $table->string('region', 60)->nullable()
                                          ->comment('Central, Eastern, Northern, Western'),
                'nationality'        => fn() => $table->string('nationality', 60)->nullable()->default('Ugandan'),
                'ethnicity'          => fn() => $table->string('ethnicity', 60)->nullable(),
                'religion'           => fn() => $table->string('religion', 60)->nullable(),
                'height_cm'          => fn() => $table->smallInteger('height_cm')->unsigned()->nullable(),
                'body_type'          => fn() => $table->string('body_type', 40)->nullable(),
                'short_bio'          => fn() => $table->string('short_bio', 300)->nullable()
                                          ->comment('Public-facing personal bio'),
                'interests'          => fn() => $table->json('interests')->nullable()
                                          ->comment('["Football","Movies","Travel"]'),
                'preferred_genres'   => fn() => $table->json('preferred_genres')->nullable(),
                'preferred_languages'=> fn() => $table->json('preferred_languages')->nullable()
                                          ->comment('["Luganda","English"]'),
                'looking_for'        => fn() => $table->string('looking_for', 60)->nullable()
                                          ->comment('Friendship/Dating/Networking'),
                'age_preference_min' => fn() => $table->tinyInteger('age_preference_min')->unsigned()->nullable(),
                'age_preference_max' => fn() => $table->tinyInteger('age_preference_max')->unsigned()->nullable(),
                'profile_photo_url'  => fn() => $table->string('profile_photo_url', 500)->nullable()
                                          ->comment('Public profile photo (distinct from admin avatar)'),
                'profile_visible'    => fn() => $table->boolean('profile_visible')->default(true),
                'profile_complete_pct'=> fn() => $table->tinyInteger('profile_complete_pct')
                                          ->unsigned()->default(0)->comment('0-100'),
            ];

            foreach ($add as $col => $definition) {
                if (!Schema::hasColumn('admin_users', $col)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $cols = [
                'relationship_status','date_of_birth','district','region','nationality',
                'ethnicity','religion','height_cm','body_type','short_bio','interests',
                'preferred_genres','preferred_languages','looking_for',
                'age_preference_min','age_preference_max','profile_photo_url',
                'profile_visible','profile_complete_pct',
            ];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('admin_users', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
    }
};
