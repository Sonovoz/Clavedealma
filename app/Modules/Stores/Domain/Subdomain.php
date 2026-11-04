<?php

namespace App\Modules\Stores\Domain;

use InvalidArgumentException;

/**
 * Clave de la dirección de una tienda: clavedealma.es/<clave> (dirección principal, ADR 0013).
 * <clave>.clavedealma.es y /tienda/<clave> redirigen a ella.
 */
final readonly class Subdomain
{
    /**
     * Claves que nunca puede usar una tienda: subdominios técnicos y, como la tienda vive en
     * clavedealma.es/<clave>, las páginas propias del market (presentes y previstas).
     */
    public const RESERVED = [
        // Subdominios y servicios técnicos
        'www', 'admin', 'api', 'app', 'staging', 'pruebas', 'test', 'dev', 'mail', 'smtp',
        'static', 'cdn', 'assets', 'media', 'status', 'clavedealma',
        // Rutas del sistema
        'livewire', 'storage', 'build', 'filament', 'sitemap', 'robots', 'favicon', 'login',
        'logout', 'register', 'password', 'webhook', 'webhooks', 'stripe', 'health',
        // Páginas del market (presentes y futuras)
        'tienda', 'tiendas', 'artistas', 'artista', 'coleccion', 'colecciones', 'producto',
        'productos', 'buscar', 'busqueda', 'carrito', 'cesta', 'checkout', 'pago', 'pagos',
        'pedido', 'pedidos', 'cuenta', 'mi-cuenta', 'ayuda', 'soporte', 'contacto', 'blog',
        'noticias', 'club', 'comunidad', 'novedades', 'rebajas', 'ofertas', 'regalos',
        'envios', 'devoluciones', 'legal', 'aviso-legal', 'privacidad', 'cookies',
        'condiciones', 'terminos', 'sobre-nosotros', 'quienes-somos', 'faq', 'prensa',
        'empleo', 'factura', 'facturas',
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

    /**
     * Expresión para las rutas: una clave válida que no sea una palabra reservada
     * (así /admin, /livewire… nunca se confunden con una tienda).
     */
    public static function routePattern(): string
    {
        return '(?!(?:'.implode('|', array_map(fn (string $w): string => preg_quote($w, '#'), self::RESERVED)).')(?![a-z0-9-]))'
            .'[a-z0-9][a-z0-9-]{1,38}[a-z0-9]';
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
