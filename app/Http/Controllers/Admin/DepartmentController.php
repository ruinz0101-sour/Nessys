<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $department = Department::create($validated);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'admin_access',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'sub_action' => 'create_department',
                'department_id' => $department->id,
                'name' => $department->name,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "สร้างแผนก {$department->name} เรียบร้อยแล้ว");
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($department->id)],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $department->update($validated);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'admin_access',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'sub_action' => 'update_department',
                'department_id' => $department->id,
                'name' => $department->name,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "อัปเดตแผนก {$department->name} เรียบร้อยแล้ว");
    }
}
