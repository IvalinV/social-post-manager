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
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('url', 2048)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('articles')
            ->whereNull('url')
            ->update(['url' => '']);

        Schema::table('articles', function (Blueprint $table): void {
            $table->string('url', 2048)->nullable(false)->change();
        });
    }
};
