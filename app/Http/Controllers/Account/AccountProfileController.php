<?php

namespace App\Http\Controllers\Account;

use App\Http\Requests\AddressRequest;
use App\Modules\Identity\Infrastructure\Models\CustomerAddress;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Storefront\Checkout\Countries;
use App\Storefront\Seo\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * «Mi cuenta → Direcciones» y «Detalles de la cuenta», como en WooCommerce (ADR 0021).
 */
class AccountProfileController
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function addresses(Request $request): View
    {
        return view('account.addresses', ['seo' => $this->seo->checkout('Direcciones'), 'address' => $this->user($request)->address()->first()]);
    }

    public function editAddress(Request $request): View
    {
        $user = $this->user($request);

        return view('account.address-edit', [
            'seo' => $this->seo->checkout('Dirección de facturación y envío'),
            'address' => $user->address()->first(),
            'defaultName' => $user->name,
            'countries' => Countries::ALL,
        ]);
    }

    public function saveAddress(AddressRequest $request): RedirectResponse
    {
        CustomerAddress::query()->updateOrCreate(['user_id' => $this->user($request)->id], $request->address());

        return to_route('account.addresses')->with('account.status', 'Dirección cambiada correctamente.');
    }

    public function editDetails(Request $request): View
    {
        return view('account.details', ['seo' => $this->seo->checkout('Detalles de la cuenta'), 'user' => $this->user($request)]);
    }

    public function saveDetails(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
        ], [], ['name' => 'nombre', 'current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

        $email = mb_strtolower(trim($data['email']));
        $emailChanged = $email !== $user->email;
        $passwordChanged = ($data['password'] ?? null) !== null;

        // Cambiar el email o la contraseña pide la contraseña actual.
        if (($emailChanged || $passwordChanged) && ! Hash::check((string) ($data['current_password'] ?? ''), $user->getAuthPassword())) {
            return back()->withErrors(['current_password' => 'Para cambiar el email o la contraseña escribe tu contraseña actual.'])->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $user->name = trim($data['name']);
        if ($emailChanged) {
            // El email nuevo hay que confirmarlo otra vez antes de ver pedidos de invitado.
            $user->email = $email;
            $user->email_verified_at = null;
        }
        if ($passwordChanged) {
            $user->password = $data['password'];
        }
        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
        if ($passwordChanged) {
            // Identificador de sesión nuevo después de cambiar la contraseña.
            $request->session()->regenerate();
        }

        return to_route('account.details')->with('account.status', 'Detalles de la cuenta cambiados correctamente.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
