<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                $request = request();

                AuditLog::create([
                    'user_id' => $event->user->id,
                    'action' => 'logout',
                    'ip' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'created_at' => now(),
                ]);
            }
        });
    }
}
