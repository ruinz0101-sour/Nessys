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
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('ใช้ใน URL และเป็นชื่อไฟล์บนดิสก์');
            $table->foreignId('parent_id')->nullable()->comment('null = root ของ root_type นั้น')->constrained('nodes');
            $table->enum('type', ['file', 'folder']);
            $table->string('name', 255)->comment('ชื่อที่แสดง (ไม่ใช้เป็น path บนดิสก์)');
            $table->enum('root_type', ['shared', 'department', 'personal']);
            $table->foreignId('department_id')->nullable()->comment('ใช้เมื่อ root_type = department')->constrained('departments');
            $table->foreignId('owner_id')->constrained('users');
            $table->string('storage_path', 500)->nullable()->comment('เฉพาะ file, path บนดิสก์');
            $table->bigInteger('size_bytes')->default(0);
            $table->string('mime_type', 150)->nullable();
            $table->string('extension', 20)->nullable();
            $table->enum('category', ['image', 'video', 'document', 'other'])->nullable()->comment('เฉพาะ file');
            $table->char('sha256', 64)->nullable()->comment('ใช้ตรวจไฟล์ซ้ำ/ความสมบูรณ์');
            $table->boolean('is_important')->default(false)->comment('ไฟล์สำคัญ ห้ามแชร์ออกนอก');
            $table->timestamp('deleted_at')->nullable()->comment('soft delete (ถังขยะ)');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->timestamp('purge_at')->nullable()->comment('วันที่ลบถาวร (deleted_at + 30 วัน)');
            $table->timestamps();

            $table->index('parent_id');
            $table->index('owner_id');
            $table->index(['root_type', 'department_id']);
            $table->index('category');
            $table->index('sha256');
            $table->index('deleted_at');
            $table->index('purge_at');
            $table->unique(['parent_id', 'name', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
