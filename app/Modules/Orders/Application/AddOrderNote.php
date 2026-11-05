<?php

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Infrastructure\Mail\OrderNoteMail;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * «Añadir nota» al pedido, como en WooCommerce (ADR 0025): privada (solo el equipo) o
 * para el cliente, que la recibe por email y la ve en la página de su pedido.
 */
class AddOrderNote
{
    public const MAX = 500;

    public function __invoke(Order $order, string $text, bool $forCustomer, ?string $author = null): void
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('Escribe la nota.');
        }
        if (mb_strlen($text) > self::MAX) {
            throw new InvalidArgumentException('La nota no puede pasar de '.self::MAX.' caracteres.');
        }

        $order->log($forCustomer ? 'customer_note' : 'note', $text, $author);

        if ($forCustomer) {
            try {
                Mail::to($order->email, $order->customer_name)->send(new OrderNoteMail($order, $text));
            } catch (Throwable $e) {
                Log::error('No se pudo enviar la nota del pedido '.$order->reference.': '.$e->getMessage());
                $order->log('email_failed', 'Nota para el cliente: '.$e->getMessage());
            }
        }
    }
}
