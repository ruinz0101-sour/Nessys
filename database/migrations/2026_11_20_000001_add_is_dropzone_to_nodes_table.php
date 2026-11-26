<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->boolean('is_dropzone')
                ->default(false)
                ->after('is_important')
                ->comment('โฟลเดอร์แลกเปลี่ยนส่วนกลาง (Public Dropzone) ที่ User ทุกคนอัปโหลดไฟล์ได้');
        });

        $adminId = DB::table('users')
            ->where('role', 'admin')
            ->value('id') ?? DB::table('users')->value('id');

        if ($adminId !== null) {
            $now = now();

            $hasDropzone = DB::table('nodes')
                ->where('root_type', 'shared')
                ->where('type', 'folder')
                ->where('is_dropzone', true)
                ->whereNull('deleted_at')
                ->exists();

            if (! $hasDropzone) {
                DB::table('nodes')->insert([
                    'uuid' => (string) Str::uuid(),
                    'parent_id' => null,
                    'type' => 'folder',
                    'name' => 'ที่ส่งงานส่วนกลาง',
                    'root_type' => 'shared',
                    'department_id' => null,
                    'owner_id' => $adminId,
                    'size_bytes' => 0,
                    'is_important' => false,
                    'is_dropzone' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (['แบบฟอร์มบริษัท', 'สื่อประชาสัมพันธ์'] as $officialName) {
                $exists = DB::table('nodes')
                    ->where('root_type', 'shared')
                    ->where('type', 'folder')
                    ->whereNull('parent_id')
                    ->where('name', $officialName)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $exists) {
                    DB::table('nodes')->insert([
                        'uuid' => (string) Str::uuid(),
                        'parent_id' => null,
                        'type' => 'folder',
                        'name' => $officialName,
                        'root_type' => 'shared',
                        'department_id' => null,
                        'owner_id' => $adminId,
                        'size_bytes' => 0,
                        'is_important' => false,
                        'is_dropzone' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropColumn('is_dropzone');
        });
    }
};
