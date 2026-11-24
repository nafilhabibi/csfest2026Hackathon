<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login_name')->nullable();
        });

        DB::table('users')->update([
            'login_name' => DB::raw("LOWER(TRIM(COALESCE(NULLIF(TRIM(login_name), ''), name)))"),
        ]);

        if (DB::table('users')->whereNull('login_name')->orWhereRaw("TRIM(login_name) = ''")->exists()) {
            throw new RuntimeException('Cannot create team login names because one or more users have an empty name.');
        }

        $hasDuplicateLoginNames = DB::table('users')
            ->selectRaw('LOWER(login_name)')
            ->groupByRaw('LOWER(login_name)')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateLoginNames) {
            throw new RuntimeException('Cannot create unique team login names because duplicate team names exist.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('login_name')->nullable(false)->change();
            $table->string('email')->nullable()->change();
            $table->unique('login_name', 'users_login_name_unique');
            $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasEmailWithoutValue = DB::table('users')->whereNull('email')->exists();
        $hasAssignedTheme = DB::table('users')->whereNotNull('theme_id')->exists();

        if ($hasEmailWithoutValue || $hasAssignedTheme) {
            throw new RuntimeException('Cannot roll back team login and theme assignment while teams have no email or assigned themes.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['theme_id']);
            $table->dropColumn(['theme_id', 'login_name']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
