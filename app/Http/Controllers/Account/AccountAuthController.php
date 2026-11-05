<?php

namespace App\Http\Controllers\Account;

use App\Modules\Identity\Application\RegisterCustomer;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Storefront\Seo\SeoBuilder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use InvalidArgumentException;

/**
 * «Mi cuenta»: Acceder, Registrarse, Salir, «¿Has olvidado tu contraseña?» y la
 * confirmación del email (ADR 0021). Los mensajes no revelan si un email tiene cuenta.
 */
class AccountAuthController
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login_email' => ['required', 'email'],
            'login_password' => ['required', 'string'],
        ], [], ['login_email' => 'email', 'login_password' => 'contraseña']);

        if (! Auth::attempt(['email' => mb_strtolower(trim($data['login_email'])), 'password' => $data['login_password']], $request->boolean('remember'))) {
            return back()->withErrors(['login_email' => __('auth.failed')])->onlyInput('login_email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('account.show'));
    }

    public function register(Request $request, RegisterCustomer $register): RedirectResponse
    {
        $data = $request->validate([
            'reg_email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'reg_password' => ['required', 'string', PasswordRule::min(8)],
        ], ['reg_email.unique' => 'Ya hay una cuenta registrada con tu dirección de correo electrónico. Accede o recupera tu contraseña.'], ['reg_email' => 'email', 'reg_password' => 'contraseña']);

        try {
            $user = $register($data['reg_email'], $data['reg_password']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reg_email' => $e->getMessage()])->onlyInput('reg_email');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.show'))
            ->with('account.status', 'Tu cuenta se ha creado. Te hemos enviado un email para confirmar tu dirección.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('account.show')->with('account.status', 'Has cerrado la sesión.');
    }

    public function lostPassword(): View
    {
        return view('account.lost-password', ['seo' => $this->seo->checkout('Recuperar contraseña')]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);

        // Mismo mensaje exista o no la cuenta (salvo si se repite demasiado).
        return $status === Password::RESET_THROTTLED
            ? back()->withErrors(['email' => __($status)])->onlyInput('email')
            : back()->with('account.status', __(Password::RESET_LINK_SENT));
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('account.reset-password', [
            'seo' => $this->seo->checkout('Nueva contraseña'),
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ], [], ['password' => 'contraseña']);

        $status = Password::reset(
            ['email' => mb_strtolower(trim($data['email'])), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET
            ? to_route('account.show')->with('account.status', __($status))
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return to_route('account.show')->with('account.status', 'Email confirmado. Ya ves en «Pedidos» también los que hiciste sin cuenta.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('account.status', 'Te hemos enviado otra vez el email para confirmar tu dirección.');
    }
}
