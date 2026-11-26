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
        Schema::create('node_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->integer('version_no');
            $table->string('storage_path', 500);
            $table->bigInteger('size_bytes');
            $table->char('sha256', 64)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique(['node_id', 'version_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_versions');
    }
};
