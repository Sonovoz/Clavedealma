<?php

namespace App\Modules\Stores\Domain;

use InvalidArgumentException;

/**
 * Subdominio de una tienda: <subdominio>.clavedealma.es
 */
final readonly class Subdomain
{
    /** Subdominios que nunca puede usar una tienda. */
    public const RESERVED = [
        'www', 'admin', 'api', 'app', 'staging', 'pruebas', 'test', 'dev', 'mail', 'smtp',
        'static', 'cdn', 'assets', 'media', 'checkout', 'pago', 'pagos', 'cuenta', 'ayuda',
        'soporte', 'blog', 'tienda', 'tiendas', 'clavedealma', 'status',
    ];

    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/';

    public string $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));

        if (! self::isValid($value)) {
            throw new InvalidArgumentException("El subdominio [{$value}] no es válido o está reservado.");
        }

        $this->value = $value;
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1
            && ! str_contains($value, '--')
            && ! in_array($value, self::RESERVED, true);
    }

    public function host(string $rootDomain): string
    {
        return "{$this->value}.{$rootDomain}";
    }
}
