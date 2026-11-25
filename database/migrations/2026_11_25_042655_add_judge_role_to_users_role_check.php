<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table users drop constraint if exists users_role_check');
        DB::statement("alter table users add constraint users_role_check check (role in ('admin', 'participant', 'judge'))");
    }

    public function down(): void
    {
        DB::statement('alter table users drop constraint if exists users_role_check');
        DB::statement("alter table users add constraint users_role_check check (role in ('admin', 'participant'))");
    }
};
