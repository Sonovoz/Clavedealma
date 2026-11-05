<?php

namespace App\Storefront\Account;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qué pedidos son de un cliente («Mi cuenta», ADR 0021):
 * - los que hizo con la sesión iniciada (orders.user_id), y
 * - los que hizo como invitado con su email, solo si lo ha confirmado. Así nadie ve
 *   pedidos ajenos registrándose con un email que no es suyo.
 */
final class CustomerOrders
{
    /**
     * @return Builder<Order>
     */
    public static function query(User $user): Builder
    {
        return Order::query()->where(function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
            if ($user->hasVerifiedEmail()) {
                $q->orWhere(fn (Builder $guest) => $guest->whereNull('user_id')->where('email', mb_strtolower($user->email)));
            }
        });
    }

    /** Pedidos como invitado que aparecerán al confirmar el email. */
    public static function waitingForVerification(User $user): int
    {
        if ($user->hasVerifiedEmail()) {
            return 0;
        }

        return Order::query()->whereNull('user_id')->where('email', mb_strtolower($user->email))->count();
    }
}
