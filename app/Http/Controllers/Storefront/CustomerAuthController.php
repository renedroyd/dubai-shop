<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function registerForm(): View
    {
        return view('storefront.customer.auth', ['mode' => 'register']);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'is_admin' => false,
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'))->with('status', 'Tu cuenta fue creada correctamente.');
    }

    public function loginForm(): View
    {
        return view('storefront.customer.auth', ['mode' => 'login']);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = mb_strtolower($credentials['email']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Las credenciales no son correctas.'])->onlyInput('email');
        }

        if ($request->user()->is_admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Utiliza el acceso administrativo para esta cuenta.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

    public function requestPasswordResetForm(): View
    {
        return view('storefront.customer.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = mb_strtolower($data['email']);

        if (User::query()->where('email', $email)->where('is_admin', false)->exists()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'Si existe una cuenta de cliente con ese correo, recibirás un enlace para restablecer la contraseña.');
    }

    public function resetPasswordForm(Request $request, string $token): View
    {
        return view('storefront.customer.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        $data['email'] = mb_strtolower($data['email']);

        if (! User::query()->where('email', $data['email'])->where('is_admin', false)->exists()) {
            return back()->withErrors(['email' => 'No fue posible validar el enlace de recuperación.']);
        }

        $status = Password::reset($data, function (User $user, string $password): void {
            if ($user->is_admin) {
                return;
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return to_route('customer.login')->with('status', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');
        }

        return back()->withErrors(['email' => 'El enlace no es válido o ha caducado. Solicita uno nuevo.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
