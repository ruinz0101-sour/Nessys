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
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('owner_id')->comment('เจ้าของไฟล์ ณ ตอนขอ')->constrained('users');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'denied', 'cancelled'])->default('pending');
            $table->foreignId('decided_by')->nullable()->comment('เจ้าของ / Manager / Admin')->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->enum('granted_permission', ['view', 'edit'])->nullable();
            $table->timestamp('granted_until')->nullable()->comment('null = ไม่หมดอายุ');
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index('node_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
