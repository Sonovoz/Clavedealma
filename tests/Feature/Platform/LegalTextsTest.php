<?php

use App\Filament\Pages\InvoicingSettings;
use App\Filament\Pages\LegalTexts;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Legal\LegalDocument;
use App\Platform\Legal\LegalGenerator;
use App\Platform\Legal\LegalPage;
use App\Platform\Security\SecurityEvent;
use App\Platform\Security\SecurityEventType;
use App\Storefront\Urls;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->withoutVite();
    $artist = Artist::factory()->create(['display_name' => 'Luna Bruma']);
    $this->store = Store::factory()->create(['artist_id' => $artist->id, 'name' => 'Luna Bruma', 'subdomain' => 'luna', 'status' => StoreStatus::Published]);
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::value('id'), 'active' => true, 'free_from_cents' => 6000, 'min_days' => 1, 'max_days' => 2]);
    $rate->bands()->create(['max_weight_grams' => 1000, 'price_cents' => 495]);
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create(['email' => 'admin@example.com']));
});

function guardarDatosFiscales(): void
{
    Livewire::test(InvoicingSettings::class)->callAction('edit', [
        'legal_name' => 'Empresa Ejemplo S.L.', 'nif' => 'B00000000', 'address' => 'Calle Ejemplo 1', 'postal_code' => '28001',
        'city' => 'Madrid', 'email' => 'hola@example.com', 'simplified_series' => 'T', 'rectifying_series' => 'R',
    ])->assertHasNoActionErrors();
}

it('sin datos fiscales la página legal dice que está pendiente', function () {
    $this->get(route('legal', 'aviso-legal'))->assertOk()->assertSee('Texto pendiente');
});

it('al guardar los datos fiscales genera los 5 textos de Clave de Alma y de cada tienda con datos reales', function () {
    guardarDatosFiscales();

    expect(LegalDocument::whereNull('store_id')->count())->toBe(5)
        ->and(LegalDocument::where('store_id', $this->store->id)->count())->toBe(5);

    $notice = LegalDocument::current(LegalPage::Notice, $this->store->id);
    expect($notice->content)->toContain('Empresa Ejemplo S.L.')->toContain('B00000000')->toContain('Luna Bruma')
        ->and($notice->missing)->toContain('teléfono', 'datos registrales (Registro Mercantil)');

    $shipping = LegalDocument::current(LegalPage::Shipping, null)->content;
    expect($shipping)->toContain('Península')->toContain('4,95 €')->toContain('60,00 €')->toContain('no enviamos a Canarias');

    $cookies = LegalDocument::current(LegalPage::Cookies, null)->content;
    expect($cookies)->toContain(config('session.cookie'))->toContain('Redsys')->not->toContain('__stripe_mid')->not->toContain('Google Analytics')
        ->and(LegalDocument::current(LegalPage::Privacy, null)->content)->toContain('Redsys y nuestro banco')->not->toContain('Stripe');

    $this->get(route('legal', 'condiciones-de-venta'))->assertOk()->assertSee('Derecho de desistimiento')->assertSee('Borrador pendiente de revisión');
    $this->get(Urls::legal($this->store, 'aviso-legal'))->assertOk()->assertSee('La tienda oficial de')->assertSee('Luna Bruma');
    $this->get(Urls::home($this->store))->assertSee(Urls::legal($this->store, 'privacidad'), false);

    expect(SecurityEvent::where('type', SecurityEventType::LegalGenerated)->exists())->toBeTrue();
});

it('solo crea versión nueva cuando cambia el texto; una tienda nueva tiene los suyos', function () {
    guardarDatosFiscales();
    expect(app(LegalGenerator::class)->generateAll())->toBe(0);

    ZoneRate::where('zone', ShippingZone::Peninsula)->update(['free_from_cents' => 8000]);
    expect(app(LegalGenerator::class)->generateAll())->toBe(2) // envíos de Clave de Alma y de la tienda
        ->and(LegalDocument::current(LegalPage::Shipping, null)->version)->toBe(2);

    $new = Store::factory()->create(['name' => 'Otra tienda', 'subdomain' => 'otra']);
    expect(LegalDocument::where('store_id', $new->id)->count())->toBe(5);
});

it('la asesoría marca los textos como revisados y queda anotado; los textos no se modifican', function () {
    guardarDatosFiscales();
    $terms = LegalDocument::current(LegalPage::Terms, null);

    Livewire::test(LegalTexts::class)->callAction(TestAction::make('approve')->table($terms))->assertHasNoActionErrors();
    expect($terms->refresh()->approved_by)->toBe('admin@example.com')
        ->and(SecurityEvent::where('type', SecurityEventType::LegalApproved)->exists())->toBeTrue();
    $this->get(route('legal', 'condiciones-de-venta'))->assertDontSee('Borrador pendiente de revisión');

    expect(fn () => $terms->update(['content' => 'otro']))->toThrow(LogicException::class)
        ->and(fn () => $terms->delete())->toThrow(LogicException::class);
});

it('el pedido guarda la versión de las condiciones que aceptó el cliente', function () {
    guardarDatosFiscales();
    config()->set('payments.driver', 'fake');
    $product = Product::factory()->create(['store_id' => $this->store->id, 'status' => ProductStatus::Published, 'price_cents' => 2000]);
    $variant = $product->variants()->create(['sku' => 'LG-1', 'size' => 'M']);

    $this->postJson(route('cart.add'), ['variant_id' => $variant->id]);
    $this->post(route('checkout.place'), [
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1',
    ])->assertRedirect();

    expect(Order::query()->sole()->terms_version)->toBe('condiciones-de-venta v1');
});

it('solo el superadministrador gestiona los textos legales', function () {
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get('/admin/textos-legales')->assertForbidden();
});
