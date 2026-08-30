<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    protected const MAX_LOGIN_ATTEMPTS = 5;
    protected const LOGIN_LOCKOUT_SECONDS = 60;

    public function login(Request $request)
    {
        if ($this->isBotSubmission($request)) {
            $this->rejectSuspiciousLogin($request, 'honeypot_or_timing');
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['required', 'string', 'max:255'],
            'company_website' => ['nullable', 'max:0'],
            '_form_loaded_at' => ['required', 'integer'],
        ], [
            'username.required' => 'Ingrese su usuario.',
            'username.regex' => 'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos.',
        ]);

        $throttleKey = $this->throttleKey($request, $credentials['username']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => "Demasiados intentos fallidos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        if (Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            $user = Auth::user();

            if (!$user->is_admin) {
                Auth::logout();
                RateLimiter::hit($throttleKey, self::LOGIN_LOCKOUT_SECONDS);
                return back()->withErrors([
                    'username' => 'Usuario o contraseña incorrectos.',
                ])->onlyInput('username');
            }

            if (!$user->isActive()) {
                Auth::logout();
                return back()->withErrors([
                    'username' => 'Tu cuenta está desactivada. Contacta al administrador.',
                ])->onlyInput('username');
            }

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        RateLimiter::hit($throttleKey, self::LOGIN_LOCKOUT_SECONDS);

        return back()->withErrors([
            'username' => 'Usuario o contraseña incorrectos.',
        ])->onlyInput('username');
    }

    protected function throttleKey(Request $request, string $username): string
    {
        return Str::lower($username).'|'.$request->ip();
    }

    protected function isBotSubmission(Request $request): bool
    {
        if ($request->filled('company_website')) {
            return true;
        }

        $loadedAt = (int) $request->input('_form_loaded_at', 0);
        $elapsed = time() - $loadedAt;

        return $loadedAt <= 0 || $elapsed < 2 || $elapsed > 3600;
    }

    protected function rejectSuspiciousLogin(Request $request, string $reason): never
    {
        Log::warning('Intento de login bloqueado por protección anti-bot', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'reason' => $reason,
        ]);

        usleep(random_int(300000, 800000));

        throw ValidationException::withMessages([
            'username' => 'Usuario o contraseña incorrectos.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
