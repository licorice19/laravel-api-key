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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('key_hash', 255)->unique();
            $table->string('name')->nullable();
            $table->string('tag')->default('default')->after('name');
            $table->index('tag');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('rate_limit')->nullable()->after('expires_at');
            $table->unsignedInteger('rate_limit_period')->nullable()->after('rate_limit');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};