<?php

namespace App\Http\Controllers\Recovery;

use App\Http\Controllers\Controller;
use App\Platform\Security\RecoverAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

/** /recuperar-acceso: solo existe mientras el .env tiene la clave RECUPERAR_ACCESO (ADR 0031). */
class RecoverAccessController extends Controller
{
    public function show(RecoverAccess $recover): View
    {
        abort_unless($recover->enabled(), 404);

        return view('recovery.form');
    }

    public function store(Request $request, RecoverAccess $recover): RedirectResponse|View
    {
        abort_unless($recover->enabled(), 404);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ], [], ['key' => 'clave de recuperación', 'email' => 'email', 'password' => 'contraseña']);

        try {
            $user = $recover($data['key'], $data['email'], $data['password']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput($request->only('email'))->withErrors(['key' => $e->getMessage()]);
        }

        return view('recovery.done', ['email' => $user->email]);
    }
}
