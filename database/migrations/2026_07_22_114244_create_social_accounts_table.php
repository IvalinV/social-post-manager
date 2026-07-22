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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            // Single-user tool: at most one connected account per platform.
            $table->string('platform')->unique();
            $table->string('account_id')->nullable();
            $table->string('account_handle')->nullable();
            $table->string('account_urn')->nullable();
            // Token columns are encrypted at rest, so they must be text-sized.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
