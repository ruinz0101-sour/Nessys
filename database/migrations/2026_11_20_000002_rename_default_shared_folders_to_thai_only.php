<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $renames = [
            'ที่ส่งงานส่วนกลาง (Public Dropzone)' => 'ที่ส่งงานส่วนกลาง',
            'แบบฟอร์มบริษัท (Company Forms)' => 'แบบฟอร์มบริษัท',
            'สื่อประชาสัมพันธ์ (PR & Media)' => 'สื่อประชาสัมพันธ์',
        ];

        foreach ($renames as $oldName => $newName) {
            DB::table('nodes')
                ->where('root_type', 'shared')
                ->where('type', 'folder')
                ->where('name', $oldName)
                ->update(['name' => $newName]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
