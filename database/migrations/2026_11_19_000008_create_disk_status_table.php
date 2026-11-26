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
        Schema::create('disk_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->nullable()->constrained('raid_pools');
            $table->string('device', 50)->comment('/dev/sda');
            $table->string('model', 150)->nullable();
            $table->string('serial', 100)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->bigInteger('used_bytes')->nullable();
            $table->smallInteger('temperature_c')->nullable();
            $table->integer('power_on_hours')->nullable();
            $table->integer('reallocated_sectors')->nullable();
            $table->enum('smart', ['ok', 'warning', 'failing', 'unknown'])->default('unknown');
            $table->timestamp('checked_at');

            $table->index('device');
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disk_status');
    }
};
