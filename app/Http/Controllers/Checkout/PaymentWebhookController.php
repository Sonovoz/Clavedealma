<?php

namespace App\Http\Controllers\Checkout;

use App\Modules\Payments\Application\HandleGatewayEvent;
use App\Modules\Payments\Contracts\InvalidWebhook;
use App\Modules\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Avisos de Stripe (fuente de verdad del pago). Solo se aceptan con firma válida.
 */
class PaymentWebhookController
{
    public function __invoke(Request $request, PaymentGateway $gateway, HandleGatewayEvent $handle): Response
    {
        abort_unless($gateway->name() === 'stripe', 404);

        try {
            $event = $gateway->parseWebhook($request->getContent(), $request->header('Stripe-Signature'));
        } catch (InvalidWebhook $e) {
            Log::warning('Aviso de pago rechazado: '.$e->getMessage());

            return response('Firma no válida', 400);
        }

        $handle($gateway->name(), $event);

        return response('ok');
    }
}
