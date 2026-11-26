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
        Schema::create('raid_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('level', 30)->nullable()->comment('RAID1, RAID5, RAIDZ1 ...');
            $table->enum('state', ['healthy', 'degraded', 'rebuilding', 'failed', 'unknown'])->default('unknown');
            $table->bigInteger('total_bytes')->nullable();
            $table->bigInteger('used_bytes')->nullable();
            $table->timestamp('checked_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raid_pools');
    }
};
