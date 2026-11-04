<?php

namespace App\Storefront\Seo;

/**
 * Todo lo que una página pública dice a buscadores y redes sociales.
 */
final readonly class SeoMeta
{
    /**
     * @param  list<array<string, mixed>>  $jsonLd  bloques de datos estructurados (schema.org)
     * @param  array<string, string>  $extraOg  p. ej. product:price:amount
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public bool $index,
        public ?string $image = null,
        public string $type = 'website',
        public array $jsonLd = [],
        public array $extraOg = [],
    ) {}

    public function robots(): string
    {
        return $this->index ? 'index, follow, max-image-preview:large' : 'noindex, nofollow';
    }
}
