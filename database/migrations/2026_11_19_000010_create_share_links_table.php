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
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('token', 64)->unique();
            $table->string('password_hash', 255)->nullable();
            $table->enum('permission', ['view', 'edit'])->default('view');
            $table->integer('max_downloads')->nullable();
            $table->integer('download_count')->default(0);
            $table->timestamp('expires_at')->comment('บังคับต้องมีวันหมดอายุ');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('node_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
