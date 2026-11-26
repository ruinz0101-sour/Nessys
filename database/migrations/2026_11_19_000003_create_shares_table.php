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
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users');
            $table->foreignId('shared_with_user_id')->nullable()->comment('แชร์ให้รายคน')->constrained('users');
            $table->foreignId('shared_with_department_id')->nullable()->comment('แชร์ให้ทั้งแผนก')->constrained('departments');
            $table->enum('permission', ['view', 'edit'])->default('view');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('node_id');
            $table->index('shared_with_user_id');
            $table->index('shared_with_department_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};
