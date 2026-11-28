<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * ตั้งค่าระบบยืนยันตัวตน (Fortify): หน้า Login/Register, Redirect หลังเข้าสู่ระบบ,
 * กฎล็อกบัญชีเมื่อใส่รหัสผ่านผิด และ Rate Limiter
 */
class FortifyServiceProvider extends ServiceProvider
{
    /** จำนวนครั้งสูงสุดที่ใส่รหัสผ่านผิดก่อนล็อกบัญชีชั่วคราว */
    public const int MAX_FAILED_ATTEMPTS = 5;

    /** ระยะเวลาล็อกบัญชีชั่วคราว (นาที) */
    public const int LOCKOUT_MINUTES = 15;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, function (): LoginResponseContract {
            return new class implements LoginResponseContract
            {
                public function toResponse($request): Response
                {
                    if ($request->hasSession()) {
                        $request->session()->forget('url.intended');
                    }

                    $user = $request->user();
                    $home = $user?->role === UserRole::Admin
                        ? '/admin/dashboard'
                        : '/files';

                    return $request->wantsJson()
                        ? new JsonResponse('', 204)
                        : redirect()->to($home);
                }
            };
        });
        $this->app->singleton(RegisterResponseContract::class, function (): RegisterResponseContract {
            return new class implements RegisterResponseContract
            {
                public function toResponse($request): Response
                {
                    if ($request->hasSession()) {
                        $request->session()->forget('url.intended');
                    }

                    $user = $request->user();
                    $home = $user?->role === UserRole::Admin
                        ? '/admin/dashboard'
                        : '/files';

                    return $request->wantsJson()
                        ? new JsonResponse('', 201)
                        : redirect()->to($home);
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn (): InertiaResponse => Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]));

        Fortify::registerView(fn (): InertiaResponse => Inertia::render('Auth/Register'));

        Fortify::requestPasswordResetLinkView(fn (): InertiaResponse => Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request): InertiaResponse => Inertia::render('Auth/ResetPassword', [
            'email' => $request->input('email'),
            'token' => $request->route('token'),
        ]));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower($request->string(Fortify::username())->trim()->toString());
            $user = User::where('email', $email)->first();

            if (! $user) {
                AuditLog::create([
                    'user_id' => null,
                    'action' => 'login_failed',
                    'ip' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'meta' => ['email' => $email, 'reason' => 'user_not_found'],
                    'created_at' => now(),
                ]);

                return null;
            }

            if (! $user->is_active) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'login_failed',
                    'ip' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'meta' => ['reason' => 'account_inactive'],
                    'created_at' => now(),
                ]);

                throw ValidationException::withMessages([
                    Fortify::username() => ['บัญชีของคุณถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'],
                ]);
            }

            if ($user->locked_until !== null && $user->locked_until->isFuture()) {
                $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until) / 60));

                throw ValidationException::withMessages([
                    Fortify::username() => ["บัญชีถูกล็อกชั่วคราวเนื่องจากใส่รหัสผ่านผิดหลายครั้ง กรุณาลองใหม่ในอีก {$minutes} นาที"],
                ]);
            }

            if (! Hash::check($request->string('password')->toString(), $user->password)) {
                $failedCount = ($user->locked_until !== null && $user->locked_until->isPast())
                    ? 1
                    : $user->failed_login_count + 1;

                $lockedUntil = $failedCount >= self::MAX_FAILED_ATTEMPTS
                    ? now()->addMinutes(self::LOCKOUT_MINUTES)
                    : null;

                $user->update([
                    'failed_login_count' => $failedCount,
                    'locked_until' => $lockedUntil,
                ]);

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'login_failed',
                    'ip' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'meta' => [
                        'failed_login_count' => $failedCount,
                        'locked' => $lockedUntil !== null,
                    ],
                    'created_at' => now(),
                ]);

                if ($lockedUntil !== null) {
                    throw ValidationException::withMessages([
                        Fortify::username() => ['ใส่รหัสผ่านผิดเกินกำหนด บัญชีของคุณถูกล็อกชั่วคราวเป็นเวลา '.self::LOCKOUT_MINUTES.' นาที'],
                    ]);
                }

                return null;
            }

            $user->update([
                'failed_login_count' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'login',
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'created_at' => now(),
            ]);

            return $user;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(10)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
