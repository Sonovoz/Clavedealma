<?php

namespace Database\Seeders;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Domain\Sku;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Application\AdjustStock;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Contracts\StockMovementReason;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Primera tienda de prueba: Farruquito, con sus colecciones On Stage, Artist Voice e Iconic.
 *
 *   php artisan db:seed --class=FarruquitoSeeder
 *
 * Los PRECIOS, la COMISIÓN y las UNIDADES son de ejemplo, para ver el sistema funcionando.
 * La tienda se crea OCULTA. Generado a partir de docs/diseno/farruquito (catálogo del prototipo).
 * Se puede ejecutar varias veces: no duplica nada. No se ejecuta en producción.
 */
class FarruquitoSeeder extends Seeder
{
    /** Unidades de ejemplo por variante en las piezas de edición limitada (On Stage). */
    private const UNIDADES_EDICION_LIMITADA = 25;

    /** @var array<string, array{0: string, 1: string}> */
    private const PRENDAS = [
        'FQ-CAM-OS' => ['Camiseta oversize 280 g', 'Algodón 280 g/m² · 100 % natural'],
        'FQ-CAM-BOXY' => ['Camiseta boxy oversize', 'Algodón premium'],
        'FQ-CAM-REG' => ['Camiseta regular fit', 'Algodón premium de tacto suave'],
        'FQ-SUD-FELPA' => ['Sudadera de felpa 280 g', 'Felpa 280 g/m² · 100 % algodón'],
        'FQ-SUD-CREW' => ['Sudadera crewneck', 'Felpa de algodón premium'],
        'FQ-SUD-HOOD' => ['Sudadera hoodie', 'Felpa de algodón premium'],
        'FQ-GOR-DAD' => ['Gorra dad cap', 'Algodón · cierre ajustable'],
        'FQ-TOTE' => ['Tote bag', 'Canvas de algodón 340 g/m²'],
    ];

    /** @var array<string, array{0: string, 1: string}> nombre y descripción de cada colección */
    private const COLECCIONES = [
        'onstage' => ['On Stage', 'Serás Farruquito · El Musical. Dos lenguajes, una misma verdad: el arte hecho legado.'],
        'artistvoice' => ['Artist Voice', 'Expresa. Crea. Trasciende. Tu voz es tu legado.'],
        'iconic' => ['Iconic', 'Built on heritage. Made to be iconic.'],
    ];

    private const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    public function run(AdjustStock $adjustStock): void
    {
        if (app()->isProduction()) {
            $this->command?->error('FarruquitoSeeder usa datos de ejemplo y no se ejecuta en producción.');

            return;
        }

        $limitadas = [];

        DB::transaction(function () use (&$limitadas): void {
            $artist = Artist::query()->firstOrCreate(['display_name' => 'Farruquito'], [
                'country' => 'ES',
                'notes' => 'Datos fiscales y acuerdo de autofacturación pendientes.',
            ]);

            $store = Store::query()->firstOrCreate(['subdomain' => 'farruquito'], [
                'artist_id' => $artist->id,
                'name' => 'Farruquito',
                'status' => StoreStatus::Hidden,
                'commission_bps' => 2500, // ejemplo: pendiente del acuerdo con el artista
                'tagline' => 'Tienda oficial · Since 1982',
                'bio' => 'Tienda oficial de Farruquito en Clave de Alma: colecciones On Stage, Artist Voice e Iconic.',
            ]);

            $prendas = [];
            foreach (self::PRENDAS as $codigo => [$nombre, $material]) {
                $prendas[$codigo] = BaseGarment::query()->firstOrCreate(['code' => $codigo], ['name' => $nombre, 'material' => $material]);
            }

            $colecciones = [];
            $orden = 0;
            foreach (self::COLECCIONES as $clave => [$nombre, $descripcion]) {
                $colecciones[$clave] = Collection::query()->firstOrCreate(
                    ['store_id' => $store->id, 'slug' => Str::slug($nombre)],
                    ['name' => $nombre, 'description' => $descripcion, 'position' => $orden++],
                );
            }

            foreach ($this->piezas() as $k => [$col, $mod, $nombre, $tipo, $prenda, $precio, $conTallas, $colores, $imagenes, $descripcion]) {
                $slug = Str::slug($mod.' '.$nombre);
                $product = Product::query()->firstOrCreate(['store_id' => $store->id, 'slug' => $slug], [
                    'name' => $nombre,
                    'base_garment_id' => $prendas[$prenda]->id,
                    'description' => "<p>{$tipo}.</p><p>{$descripcion}</p>",
                    'price_cents' => $precio,
                    'status' => ProductStatus::Published,
                    'lead_time_min_days' => $conTallas ? 5 : 3,
                    'lead_time_max_days' => $conTallas ? 7 : 5,
                ]);
                $product->collections()->syncWithoutDetaching([$colecciones[$col]->id => ['position' => $k]]);

                $this->imagenes($product, $imagenes);

                $posicion = 0;
                foreach ($colores as $color) {
                    foreach ($conTallas ? self::TALLAS : [null] as $talla) {
                        $variant = $product->variants()->firstOrCreate(
                            ['sku' => Sku::suggest('farruquito', $slug, $talla, $color)],
                            ['size' => $talla, 'color' => $color, 'position' => $posicion++],
                        );

                        if ($col === 'onstage') {
                            $limitadas[] = $variant->id;
                        }
                    }
                }
            }
        });

        // Las fichas de stock nacen al confirmar la transacción (evento VariantCreated): se ajustan después.
        foreach ($limitadas as $variantId) {
            $this->edicionLimitada($variantId, $adjustStock);
        }

        $this->command?->info('Tienda Farruquito creada (oculta) con 3 colecciones y '.count($this->piezas()).' piezas de ejemplo.');
    }

    /**
     * Copia las imágenes del prototipo al disco público, si están disponibles.
     *
     * @param  list<string>  $imagenes
     */
    private function imagenes(Product $product, array $imagenes): void
    {
        if ($product->images()->exists()) {
            return;
        }

        foreach ($imagenes as $posicion => $fichero) {
            $origen = base_path('docs/diseno/farruquito/img/'.$fichero);
            if (! File::exists($origen)) {
                continue; // en el ZIP de Hostinger no viaja docs/: la tienda se crea sin imágenes
            }
            $ruta = 'products/farruquito/'.$fichero;
            Storage::disk('public')->put($ruta, File::get($origen));
            $product->images()->create(['path' => $ruta, 'alt' => $product->name, 'position' => $posicion]);
        }
    }

    private function edicionLimitada(int $variantId, AdjustStock $adjustStock): void
    {
        $level = StockLevel::query()->where('product_variant_id', $variantId)->firstOrFail();
        if ($level->mode !== StockMode::OnDemand) {
            return;
        }
        $level->update(['mode' => StockMode::LimitedThenSoldOut, 'low_stock_threshold' => 5]);
        $adjustStock($variantId, self::UNIDADES_EDICION_LIMITADA, StockMovementReason::Initial, 'Edición limitada de ejemplo');
    }

    /**
     * colección, modelo, nombre, tipo, prenda base, precio con IVA (céntimos, ejemplo), ¿tallas?, colores, imágenes, descripción
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6: bool, 7: list<string>, 8: list<string>, 9: string}>
     */
    private function piezas(): array
    {
        return [
            ['onstage', 'Mod. 01', 'La esencia', 'Camiseta oversize · negra', 'FQ-CAM-OS', 3990, true, ['Negro'], ['art-01a-sepia.webp', 'look-01.webp'], 'Una imagen que trasciende lo visible. El artista frente a su propia sombra: el gesto abierto, la entrega total y el símbolo en la espalda que habla de raíces y de linaje.'],
            ['onstage', 'Mod. 01B', 'El llamado del alma', 'Camiseta oversize · negra', 'FQ-CAM-OS', 3990, true, ['Negro'], ['art-01b-llamado.webp', 'look-01b.webp'], 'Una sombra que abraza. Una espalda que eleva. El instante en que el alma reconoce su misión y se entrega al arte como única verdad.'],
            ['onstage', 'Mod. 01C', 'La luz del duende', 'Camiseta oversize · negra', 'FQ-CAM-OS', 3990, true, ['Negro'], ['art-01c-luz.webp', 'look-01c.webp'], 'La misma raíz, abierta al color como una revelación. La sombra sigue siendo memoria, pero ahora respira luz, impulso y expansión.'],
            ['onstage', 'Mod. 02', 'El don hecho color', 'Camiseta oversize · negra', 'FQ-CAM-OS', 4290, true, ['Negro'], ['art-02a-vitral.webp', 'look-02.webp'], 'El universo de Serás Farruquito en clave cromática: la figura se eleva como una invocación luminosa y el color irrumpe sobre la sombra.'],
            ['onstage', 'Mod. 02B', 'La huella del movimiento', 'Camiseta oversize · negra', 'FQ-CAM-OS', 4290, true, ['Negro'], ['art-02b-huella.webp'], 'Aquí el baile deja de ser instante para convertirse en estela. Las botas aparecen como memoria del compás; negro, blanco y rojo construyen la tensión dramática.'],
            ['onstage', 'Mod. 06', 'El espejo del origen', 'Camiseta oversize · ecru', 'FQ-CAM-OS', 3990, true, ['Ecru'], ['art-06-espejo.webp', 'look-06.webp'], 'Dos tiempos, un alma, un mismo camino. El retrato dividido revela el origen que nos forma y el destino que elegimos abrazar.'],
            ['onstage', 'Mod. 08', 'El pulso del compás', 'Sudadera de felpa · negra', 'FQ-SUD-FELPA', 6490, true, ['Negro'], ['art-08-pulso.webp', 'look-08.webp'], 'Un círculo que no se cierra: se expande. El movimiento se vuelve mantra y la huella del baile se ordena en torno al centro de la memoria.'],
            ['onstage', 'Mod. 09', 'El don en silencio', 'Sudadera · ecru', 'FQ-SUD-FELPA', 6490, true, ['Ecru'], ['art-09-silencio.webp', 'look-09.webp'], 'Una imagen que no necesita alzar la voz. La obra respira sobriedad, calma y profundidad, como si el alma se vistiera de silencio para seguir bailando.'],
            ['onstage', 'Tote', 'Memoria circular', 'Tote bag · negra', 'FQ-TOTE', 1990, false, ['Negro'], ['tote-1.webp'], 'Ritmo, raíz y movimiento en una sola órbita.'],
            ['onstage', 'Tote', 'El duende cromático', 'Tote bag · negra', 'FQ-TOTE', 1990, false, ['Negro'], ['tote-2.webp'], 'El arte se eleva en color, fuerza y gratitud.'],
            ['onstage', 'Tote', 'Pulso en movimiento', 'Tote bag · negra', 'FQ-TOTE', 1990, false, ['Negro'], ['tote-3.webp'], 'La huella del baile convertida en impulso vivo.'],
            ['onstage', 'Tote', 'Sombra del origen', 'Tote bag · negra', 'FQ-TOTE', 1990, false, ['Negro'], ['tote-4.webp'], 'El gesto interior, el don y la presencia.'],
            ['artistvoice', 'AV 01', 'Alma flamenca', 'Camiseta boxy oversize · blanca', 'FQ-CAM-BOXY', 3490, true, ['Blanco'], ['av-d01-alma-flamenca.webp', 'av-m01.webp'], 'Manifiesto principal de la serie: el universo flamenco desde la fuerza de la palabra, el gesto del artista y la energía del cartel urbano. Print trasero de gran formato y detalle en pecho.'],
            ['artistvoice', 'AV 02', 'Bailo… luego existo', 'Camiseta boxy oversize · negro lavado', 'FQ-CAM-BOXY', 3490, true, ['Negro lavado'], ['av-d02-bailo-luego-existo.webp', 'av-m02.webp'], 'Frase directa, icónica y muy reconocible: el claim de la colección en un mensaje tipográfico frontal de gran presencia.'],
            ['artistvoice', 'AV 03', 'Se hace camino', 'Camiseta boxy oversize · blanca', 'FQ-CAM-BOXY', 3490, true, ['Blanco'], ['av-d03-se-hace-camino.webp', 'av-m03.webp'], 'Se hace camino al andar, y al bailar marcas camino. Diseño tipográfico de acabado desgastado en espalda y firma mínima en pecho.'],
            ['artistvoice', 'AV 04', 'Trata de volar', 'Camiseta boxy oversize · negro lavado', 'FQ-CAM-BOXY', 3490, true, ['Negro lavado'], ['av-d04-trata-de-volar.webp', 'av-m04.webp'], 'Pieza poética: sombra, cuerpo y mensaje en una composición de gran carga emocional en la espalda, con firma ornamental en el pecho.'],
            ['artistvoice', 'AV 05', 'Que la vida te pille bailando', 'Camiseta boxy oversize · blanca', 'FQ-CAM-BOXY', 3490, true, ['Blanco'], ['av-d07-que-la-vida.webp', 'av-m05.webp'], 'Composición vertical de fuerte presencia tipográfica: la figura en movimiento y los lugares clave del flamenco, de Triana al Sacromonte.'],
            ['artistvoice', 'AV 06', 'Bailo mejor que Farruquito', 'Camiseta boxy oversize · negro lavado', 'FQ-CAM-BOXY', 3490, true, ['Negro lavado'], ['av-d06-bailo-mejor.webp', 'av-m06.webp'], 'Diseño multicolor de máxima energía con las palabras clave de la serie: alma, herencia, flamenco, dinastía, duende.'],
            ['artistvoice', 'AV 07', 'St@y Farruquito', 'Camiseta boxy oversize · negra', 'FQ-CAM-BOXY', 3490, true, ['Negro'], ['av-d08-stay.webp', 'av-m07.webp'], 'Logo-frase de estética spray: urbano, directo y de alto contraste.'],
            ['artistvoice', 'AV 08', 'Dad cap Artist Voice', 'Gorra dad cap · blanca o negra', 'FQ-GOR-DAD', 2490, false, ['Blanco', 'Negro'], ['av-d05-cuando-el-alma-grita.webp', 'av-m08.webp'], 'Set de gorras con dos mensajes icónicos de la línea: “Cuando el alma grita” en blanco y “Bailo… luego existo” en negro.'],
            ['artistvoice', 'AV 09', 'Bailo… luego existo', 'Sudadera crewneck · negra o ecru', 'FQ-SUD-CREW', 5490, true, ['Negro', 'Ecru'], ['av-d02-bailo-luego-existo.webp', 'av-m09.webp'], 'Sudadera crewneck con el mensaje tipográfico de la colección: versátil, urbana y de uso diario.'],
            ['artistvoice', 'AV 10', 'Bailo mejor que Farruquito', 'Sudadera hoodie · negra', 'FQ-SUD-HOOD', 6490, true, ['Negro'], ['av-d06-bailo-mejor.webp', 'av-m10.webp'], 'Hoodie con firma compacta en pecho y la composición multicolor a gran formato en la espalda.'],
            ['artistvoice', 'AV 11', 'Alma flamenca', 'Sudadera hoodie · negra', 'FQ-SUD-HOOD', 6490, true, ['Negro'], ['av-d01-alma-flamenca.webp', 'av-m11.webp'], 'El universo de Alma flamenca en una silueta urbana: gran formato en espalda y aplicación compacta en pecho.'],
            ['artistvoice', 'AV 13', 'Cuando el alma grita', 'Camiseta oversize · negra', 'FQ-CAM-OS', 3490, true, ['Negro'], ['av-d05-cuando-el-alma-grita.webp', 'av-m13.webp'], '“Cuando el alma grita, el corazón baila”: gráfica frontal de lenguaje sticker y lectura circular.'],
            ['iconic', 'IC 01', 'Classic Embroidery', 'Camiseta boxy oversize · blanca', 'FQ-CAM-BOXY', 3990, true, ['Blanco'], ['ic-d01-emblema.webp', 'ic-m01.webp'], 'El emblema oficial con efecto bordado en contraste en el pecho: la base del lenguaje de la colección.'],
            ['iconic', 'IC 02', 'Classic Words · Regular', 'Camiseta regular fit · negra o blanca', 'FQ-CAM-REG', 3490, true, ['Negro', 'Blanco'], ['ic-d02-words.webp', 'ic-m02r.webp'], 'Camiseta regular fit con el emblema de palabras: algodón premium de tacto suave y estructura ligera.'],
            ['iconic', 'IC 02B', 'Classic Embroidery', 'Camiseta boxy oversize · negra', 'FQ-CAM-BOXY', 3990, true, ['Negro'], ['ic-d01-emblema.webp', 'ic-m02.webp'], 'Print en pecho de efecto bordado en crema sobre negro: icónica y atemporal.'],
            ['iconic', 'IC 03', 'Emblema mayor', 'Camiseta boxy oversize · negra', 'FQ-CAM-BOXY', 3990, true, ['Negro'], ['ic-m03.webp'], 'El emblema a gran tamaño en blanco, con la silueta del artista recortada en negro.'],
            ['iconic', 'IC 04', 'Bordado compacto', 'Camiseta boxy oversize · negra', 'FQ-CAM-BOXY', 3990, true, ['Negro'], ['ic-m04.webp'], 'Bordado frontal de formato compacto y firma en la espalda.'],
            ['iconic', 'IC 05', 'Dad cap arena', 'Gorra dad cap · arena vintage', 'FQ-GOR-DAD', 2790, false, ['Arena'], ['ic-m05.webp'], 'Gorra de algodón efecto vintage en tono arena con el emblema bordado en el frontal.'],
            ['iconic', 'IC 06', 'Dad cap Iconic', 'Gorra dad cap · arena, blanca o negra', 'FQ-GOR-DAD', 2790, false, ['Arena', 'Blanco', 'Negro'], ['ic-m06.webp'], 'Bordado frontal de alto contraste y firma en la parte trasera. Cierre ajustable.'],
            ['iconic', 'IC 07', 'Classic Signature', 'Sudadera crewneck · cruda', 'FQ-SUD-CREW', 5490, true, ['Crudo'], ['ic-d03-signature.webp', 'ic-m07.webp'], 'Sudadera en tono crudo con el emblema de palabras y la firma: premium y versátil.'],
            ['iconic', 'IC 08', 'Classic Embroidery', 'Sudadera hoodie · negra', 'FQ-SUD-HOOD', 6490, true, ['Negro'], ['ic-d01-emblema.webp', 'ic-m08.webp'], 'Hoodie con el emblema de efecto bordado en el frontal: el lenguaje Iconic en clave premium.'],
            ['iconic', 'IC 09', 'Classic Shadow', 'Camiseta oversize · negra', 'FQ-CAM-OS', 3990, true, ['Negro'], ['ic-d04-shadow.webp', 'ic-m09.webp'], 'Bordado frontal de alta definición y, en la espalda, el símbolo convertido en luz y sombra.'],
            ['iconic', 'IC 10', 'Tote bag Iconic', 'Tote bag · negra o natural', 'FQ-TOTE', 2290, false, ['Negro', 'Natural'], ['ic-m10.webp'], 'Canvas premium de algodón 340 g/m², asas reforzadas y gran capacidad. 38 × 42 cm.'],
        ];
    }
}
