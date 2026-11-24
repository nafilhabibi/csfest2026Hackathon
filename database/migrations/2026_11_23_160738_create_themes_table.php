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
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX themes_title_unique ON themes (LOWER(title))');
        } else {
            Schema::table('themes', function (Blueprint $table) {
                $table->unique('title', 'themes_title_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('themes') && DB::table('themes')->exists()) {
            throw new RuntimeException('Cannot roll back the themes migration while study cases exist.');
        }

        Schema::dropIfExists('themes');
    }
};
