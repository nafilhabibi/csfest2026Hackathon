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
            $table->string('role', 20)->default('participant');
        });

        DB::statement(
            "alter table users add constraint users_role_check check (role in ('admin', 'participant'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('alter table users drop constraint if exists users_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
