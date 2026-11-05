<?php

namespace App\Http\Controllers\Checkout;

use App\Modules\Payments\Application\HandleGatewayEvent;
use App\Modules\Payments\Contracts\InvalidWebhook;
use App\Modules\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Avisos de la pasarela (fuente de verdad del pago): Redsys (/webhooks/redsys) o Stripe
 * (/webhooks/stripe). Solo se aceptan con firma válida y de la pasarela activa.
 */
class PaymentWebhookController
{
    public function __invoke(Request $request, PaymentGateway $gateway, HandleGatewayEvent $handle, string $provider): Response
    {
        abort_unless($gateway->name() === $provider, 404);

        try {
            // Stripe firma el cuerpo tal cual; Redsys manda un formulario con su firma dentro.
            $payload = $provider === 'stripe' ? $request->getContent() : http_build_query($request->post());
            $event = $gateway->parseWebhook($payload, $request->header('Stripe-Signature'));
        } catch (InvalidWebhook $e) {
            Log::warning('Aviso de pago rechazado: '.$e->getMessage());

            return response('Firma no válida', 400);
        }

        $handle($gateway->name(), $event);

        return response('ok');
    }
}
