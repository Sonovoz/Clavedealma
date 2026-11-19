<?php

namespace App\Http\Controllers\Recovery;

use App\Http\Controllers\Controller;
use App\Platform\Security\RecoverAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Throwable;

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

        if (! $recover->matches($data['key'])) {
            return back()->withInput($request->only('email'))->withErrors(['key' => 'La clave de recuperación no es correcta.']);
        }

        try {
            $user = $recover($data['key'], $data['email'], $data['password']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput($request->only('email'))->withErrors(['password' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->only('email'))->withErrors(['key' => 'No se ha podido poner la contraseña (la clave sigue valiendo): '.$e->getMessage()]);
        }

        return view('recovery.done', ['email' => $user->email]);
    }
}
