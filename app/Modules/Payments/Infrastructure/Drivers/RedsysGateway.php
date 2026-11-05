<?php

namespace App\Modules\Payments\Infrastructure\Drivers;

use App\Modules\Payments\Contracts\GatewayEvent;
use App\Modules\Payments\Contracts\InvalidWebhook;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentRequest;
use App\Modules\Payments\Contracts\PaymentUnavailable;
use App\Modules\Payments\Contracts\RefundResult;
use App\Modules\Payments\Contracts\StartedPayment;
use App\Modules\Payments\Infrastructure\Drivers\Redsys\RedsysSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Redsys, el TPV virtual del banco (ADR 0026): tarjeta y Bizum en la página de pago segura
 * de Redsys («Redirección»). La tarjeta la escribe el cliente en Redsys: nunca pasa por
 * nuestro servidor. El cobro se da por bueno con el aviso firmado de Redsys o con el
 * resultado firmado que trae el cliente al volver; las devoluciones van por su API REST.
 */
class RedsysGateway implements PaymentGateway
{
    private const URLS = [
        'test' => ['pay' => 'https://sis-t.redsys.es:25443/sis/realizarPago', 'rest' => 'https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST'],
        'live' => ['pay' => 'https://sis.redsys.es/sis/realizarPago', 'rest' => 'https://sis.redsys.es/sis/rest/trataPeticionREST'],
    ];

    /** Códigos de Redsys que el cliente entiende (el resto: «el banco lo ha rechazado»). */
    private const MESSAGES = [
        '0101' => 'La tarjeta está caducada.',
        '0116' => 'La tarjeta no tiene saldo suficiente.',
        '0129' => 'El código de seguridad (CVV) no es correcto.',
        '0180' => 'Esta tarjeta no admite este pago.',
        '0184' => 'No se ha podido verificar que eres el titular de la tarjeta.',
        '0190' => 'El banco ha rechazado el pago.',
        '9915' => 'Has cancelado el pago.',
    ];

    public function __construct(
        private readonly string $merchantCode,
        private readonly string $terminal,
        private readonly string $secretKey,
        private readonly string $environment = 'test',
        private readonly bool $bizum = false,
        private readonly string $merchantName = 'Clave de Alma',
        private readonly ?string $notifyUrl = null,
        private readonly bool $card = true,
    ) {}

    public function name(): string
    {
        return 'redsys';
    }

    public function publicKey(): ?string
    {
        return null;
    }

    public function methods(): array
    {
        $methods = ($this->card ? ['card' => 'Tarjeta de crédito o débito'] : []) + ($this->bizum ? ['bizum' => 'Bizum'] : []);

        // Nunca sin ningún método: la tarjeta, como mínimo.
        return $methods === [] ? ['card' => 'Tarjeta de crédito o débito'] : $methods;
    }

    public function testMode(): bool
    {
        return $this->environment !== 'live';
    }

    public function start(PaymentRequest $request): StartedPayment
    {
        $this->ensureConfigured();
        if (strtoupper($request->currency) !== 'EUR') {
            throw new PaymentUnavailable('Redsys solo cobra en euros.');
        }

        // Redsys no deja repetir un número de pedido: uno nuevo por cada visita a su página
        // (12 cifras, las 4 primeras del pedido). Hasta que el cliente paga allí no se cobra nada.
        $order = sprintf('%04d%08d', $request->orderId % 10000, random_int(0, 99_999_999));

        $params = array_filter([
            'DS_MERCHANT_AMOUNT' => (string) $request->amountCents,
            'DS_MERCHANT_ORDER' => $order,
            'DS_MERCHANT_MERCHANTCODE' => $this->merchantCode,
            'DS_MERCHANT_CURRENCY' => '978',
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_TERMINAL' => $this->terminal,
            'DS_MERCHANT_MERCHANTNAME' => mb_substr($this->merchantName, 0, 25),
            'DS_MERCHANT_PRODUCTDESCRIPTION' => mb_substr('Pedido '.$request->reference, 0, 125),
            'DS_MERCHANT_CONSUMERLANGUAGE' => '001',
            'DS_MERCHANT_PAYMETHODS' => $request->method === 'bizum' ? 'z' : 'C',
            'DS_MERCHANT_MERCHANTURL' => $this->notifyUrl,
            'DS_MERCHANT_URLOK' => $request->returnUrl,
            'DS_MERCHANT_URLKO' => $request->cancelUrl ?? $request->returnUrl,
        ], fn (?string $v): bool => $v !== null && $v !== '');

        return new StartedPayment($order, null, self::URLS[$this->env()]['pay'], $this->signature()->sign($params));
    }

    /** El aviso de Redsys llega como formulario (Ds_SignatureVersion, Ds_MerchantParameters, Ds_Signature). */
    public function parseWebhook(string $payload, ?string $signature): GatewayEvent
    {
        parse_str($payload, $fields);

        return $this->event($fields);
    }

    public function returnEvent(array $query): ?GatewayEvent
    {
        return isset($query['Ds_MerchantParameters']) ? $this->event($query) : null;
    }

    /** Redsys no tiene una consulta sencilla: el resultado llega por el aviso o con la vuelta del cliente. */
    public function fetch(string $providerReference): GatewayEvent
    {
        return new GatewayEvent('sync:'.$providerReference, GatewayEvent::OTHER, $providerReference);
    }

    /** No hay nada que anular: un número de pedido que no se paga caduca solo en Redsys. */
    public function cancel(string $providerReference): void {}

    /** Devolución (tipo de operación 3) por la API REST, sobre el mismo número de pedido. */
    public function refund(string $providerReference, int $amountCents, string $idempotencyKey): RefundResult
    {
        try {
            $this->ensureConfigured();
            $response = Http::asJson()->acceptJson()->timeout(30)->post(self::URLS[$this->env()]['rest'], $this->signature()->sign([
                'DS_MERCHANT_AMOUNT' => (string) $amountCents,
                'DS_MERCHANT_ORDER' => $providerReference,
                'DS_MERCHANT_MERCHANTCODE' => $this->merchantCode,
                'DS_MERCHANT_CURRENCY' => '978',
                'DS_MERCHANT_TRANSACTIONTYPE' => '3',
                'DS_MERCHANT_TERMINAL' => $this->terminal,
            ]));
        } catch (ConnectionException|PaymentUnavailable $e) {
            return new RefundResult(false, null, 'Redsys no ha respondido: '.$e->getMessage());
        }

        $body = (array) $response->json();
        if (isset($body['errorCode'])) {
            return new RefundResult(false, null, 'Redsys ha rechazado la devolución ('.$body['errorCode'].').');
        }

        try {
            $params = $this->signature()->verify((string) ($body['Ds_SignatureVersion'] ?? ''), (string) ($body['Ds_MerchantParameters'] ?? ''), (string) ($body['Ds_Signature'] ?? ''));
        } catch (InvalidWebhook $e) {
            return new RefundResult(false, null, 'Respuesta de Redsys no válida: '.$e->getMessage());
        }

        $code = self::code($params);

        // 0900: devolución hecha.
        return $code === '0900'
            ? new RefundResult(true, $providerReference.':'.($params['Ds_AuthorisationCode'] ?? substr(hash('sha256', $idempotencyKey), 0, 8)))
            : new RefundResult(false, null, 'Redsys no ha hecho la devolución (código '.$code.').');
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function event(array $fields): GatewayEvent
    {
        $params = $this->signature()->verify(
            (string) ($fields['Ds_SignatureVersion'] ?? ''),
            (string) ($fields['Ds_MerchantParameters'] ?? ''),
            (string) ($fields['Ds_Signature'] ?? ''),
        );
        $order = $params['Ds_Order'];
        $code = self::code($params);
        // 0000–0099: pago autorizado.
        $ok = ctype_digit($code) && (int) $code <= 99;

        return new GatewayEvent(
            eventId: 'redsys:'.$order.':'.$code,
            type: $ok ? GatewayEvent::SUCCEEDED : GatewayEvent::FAILED,
            providerReference: $order,
            amountCents: isset($params['Ds_Amount']) ? (int) $params['Ds_Amount'] : null,
            currency: ($params['Ds_Currency'] ?? '978') === '978' ? 'EUR' : (string) $params['Ds_Currency'],
            failureMessage: $ok ? null : (self::MESSAGES[$code] ?? 'El banco ha rechazado el pago (código '.$code.').'),
        );
    }

    /**
     * @param  array<string, string>  $params
     */
    private static function code(array $params): string
    {
        return str_pad((string) ($params['Ds_Response'] ?? '9999'), 4, '0', STR_PAD_LEFT);
    }

    private function signature(): RedsysSignature
    {
        return new RedsysSignature($this->secretKey);
    }

    private function env(): string
    {
        return $this->environment === 'live' ? 'live' : 'test';
    }

    private function ensureConfigured(): void
    {
        if ($this->merchantCode === '' || $this->secretKey === '') {
            throw new PaymentUnavailable('Faltan los datos del TPV de Redsys (REDSYS_MERCHANT_CODE y REDSYS_SECRET_KEY en el .env).');
        }
    }
}
