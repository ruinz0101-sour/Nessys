<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        return Inertia::render('Settings/Profile');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $avatarPath = $user->avatar_path;
        $avatarChanged = false;

        if ($request->boolean('remove_avatar') && ! empty($avatarPath)) {
            if (Storage::disk('local')->exists($avatarPath)) {
                Storage::disk('local')->delete($avatarPath);
            }
            $avatarPath = null;
            $avatarChanged = true;
        }

        if ($request->hasFile('avatar')) {
            if (! empty($avatarPath) && Storage::disk('local')->exists($avatarPath)) {
                Storage::disk('local')->delete($avatarPath);
            }

            $file = $request->file('avatar');
            $extension = Str::lower($file->extension() ?: $file->getClientOriginalExtension() ?: 'png');
            $filename = Str::uuid().'.'.$extension;
            $avatarPath = $file->storeAs('avatars', $filename, 'local');
            $avatarChanged = true;
        }

        $user->update([
            'name' => trim($validated['name']),
            'email' => Str::lower(trim($validated['email'])),
            'avatar_path' => $avatarPath,
        ]);

        if ($avatarChanged) {
            $user->touch();
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'sub_action' => 'update_profile',
                'avatar_changed' => $avatarChanged,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว');
    }

    public function showAvatar(User $user): StreamedResponse
    {
        if (empty($user->avatar_path) || ! Storage::disk('local')->exists($user->avatar_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($user->avatar_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
