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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->comment('null = ระบบ / login ล้มเหลว')->constrained('users');
            $table->string('action', 60)->comment('login, logout, login_failed, view, download, upload, rename, move, delete, restore, share, unshare, request, approve, deny, admin_access');
            $table->unsignedBigInteger('node_id')->nullable()->comment('ไม่ผูก FK เพื่อให้ log อยู่ต่อแม้ลบไฟล์');
            $table->string('node_name', 255)->nullable()->comment('เก็บชื่อ ณ ตอนทำ');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('node_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
