<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Support\ActiveStore;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Ai\AiException;
use App\Platform\Ai\Drivers\NullTextGenerator;
use App\Platform\Ai\Drivers\OpenAiCompatibleTextGenerator;
use App\Platform\Ai\TextGenerator;
use App\Storefront\Seo\SeoAssistant;
use App\Storefront\Seo\SeoAudit;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function openAi(array $overrides = []): OpenAiCompatibleTextGenerator
{
    return new OpenAiCompatibleTextGenerator($overrides + [
        'base_url' => 'https://ia.ejemplo.test/v1', 'api_key' => 'clave-de-prueba', 'model' => 'modelo-x',
        'timeout' => 5, 'json_mode' => true, 'max_tokens_param' => 'max_tokens',
    ]);
}

/** Proveedor falso que devuelve lo que se le diga y guarda la última petición. */
function fakeAi(array $answer): TextGenerator
{
    return new class($answer) implements TextGenerator
    {
        public ?string $prompt = null;

        public function __construct(private array $answer) {}

        public function available(): bool
        {
            return true;
        }

        public function json(string $system, string $prompt, int $maxTokens = 1500): array
        {
            $this->prompt = $system."\n".$prompt;

            return $this->answer;
        }
    };
}

beforeEach(function () {
    $this->store = Store::factory()->create(['name' => 'Farruquito', 'subdomain' => 'farruquito']);
    $garment = BaseGarment::factory()->create(['store_id' => $this->store->id, 'name' => 'Camiseta oversize', 'composition' => '100 % algodón']);
    $this->product = Product::factory()->create(['store_id' => $this->store->id, 'name' => 'La esencia', 'base_garment_id' => $garment->id, 'description' => '<p>El artista frente a su sombra.</p>']);
    $this->product->variants()->create(['sku' => 'FQ-1-M', 'size' => 'M', 'color' => 'Negro']);
});

it('sin configurar, la IA está desactivada y lo explica', function () {
    expect(app(TextGenerator::class))->toBeInstanceOf(NullTextGenerator::class)
        ->and(app(SeoAssistant::class)->available())->toBeFalse();
    expect(fn () => app(SeoAssistant::class)->productSeo($this->product))->toThrow(AiException::class, 'no está configurada');
});

it('el driver compatible OpenAI envía modelo, mensajes y JSON, y entiende la respuesta', function () {
    Http::fake(['ia.ejemplo.test/*' => Http::response(['choices' => [['message' => ['content' => "```json\n{\"seo_title\": \"Camiseta La esencia · Farruquito\", \"seo_description\": \"x\"}\n```"]]]])]);

    $data = openAi()->json('sistema', 'tarea', 300);

    expect($data['seo_title'])->toBe('Camiseta La esencia · Farruquito');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://ia.ejemplo.test/v1/chat/completions'
        && $r->hasHeader('Authorization', 'Bearer clave-de-prueba')
        && $r['model'] === 'modelo-x'
        && $r['max_tokens'] === 300
        && $r['response_format'] === ['type' => 'json_object']
        && $r['messages'][0] === ['role' => 'system', 'content' => 'sistema']);
});

it('traduce los errores del proveedor a mensajes claros', function (int $status, string $text) {
    Http::fake(['*' => Http::response(['error' => 'x'], $status)]);

    expect(fn () => openAi()->json('s', 'p'))->toThrow(AiException::class, $text);
})->with([
    'clave' => [401, 'rechazado la clave'],
    'modelo' => [404, 'no reconoce el modelo'],
    'saldo' => [429, 'demasiadas peticiones o saldo'],
]);

it('el asistente pasa solo datos del catálogo, con las reglas, y recorta a las longitudes de Google', function () {
    $ai = fakeAi(['seo_title' => str_repeat('Camiseta La esencia Farruquito ', 4), 'seo_description' => 'Camiseta oversize oficial de Farruquito con el diseño La esencia: el artista frente a su sombra. Algodón 100 %, fabricada bajo pedido.']);
    $this->app->instance(TextGenerator::class, $ai);

    $s = app(SeoAssistant::class)->productSeo($this->product);

    expect(mb_strlen($s['seo_title']))->toBeLessThanOrEqual(60)
        ->and($s['seo_description'])->toStartWith('Camiseta oversize oficial')
        ->and($ai->prompt)->toContain('No inventes nada')
        ->toContain('Nombre del diseño: La esencia')
        ->toContain('Composición: 100 % algodón')
        ->not->toContain('@');  // ningún email ni dato de clientes
});

it('el semáforo SEO puntúa lo que falta', function () {
    $audit = app(SeoAudit::class)->product($this->product);
    expect($audit['score'])->toBeLessThan(60)
        ->and(collect($audit['checks'])->pluck('message')->implode(' '))->toContain('Sin fotos');

    $this->product->update([
        'seo_title' => 'Camiseta La esencia · Merchandising Farruquito',
        'seo_description' => 'Camiseta oversize oficial de Farruquito con el diseño La esencia: el artista frente a su sombra. Algodón, bajo pedido.',
        'description' => '<p>'.str_repeat('palabra ', 45).'</p>',
        'status' => ProductStatus::Published,
    ]);
    foreach (['frontal', 'espalda', 'detalle'] as $k => $vista) {
        $this->product->images()->create(['path' => "p/{$k}.jpg", 'alt' => "Camiseta La esencia negra, vista {$vista}"]);
    }
    expect(app(SeoAudit::class)->product($this->product->fresh())['score'])->toBe(100);
});

it('desde la ficha, «Sugerir título y descripción» rellena los campos sin guardar', function () {
    $this->app->instance(TextGenerator::class, fakeAi(['seo_title' => 'Camiseta La esencia · Farruquito', 'seo_description' => 'Descripción propuesta por la IA para Google, con la prenda y el diseño oficial de Farruquito en algodón.']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    ActiveStore::set($this->store->id);

    Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
        ->callAction(TestAction::make('aiSeo')->schemaComponent('seo-ai', schema: 'form'))
        ->assertSchemaStateSet(['seo_title' => 'Camiseta La esencia · Farruquito'])
        ->assertNotified('Propuesta lista: revísala y guarda');

    expect($this->product->fresh()->seo_title)->toBeNull(); // no se guarda solo
});
