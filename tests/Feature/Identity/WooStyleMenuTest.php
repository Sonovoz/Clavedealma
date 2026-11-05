<?php

use App\Filament\Pages\LegalTexts;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\SecurityEvents\Pages\ListSecurityEvents;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Contracts\OrderStatus;
use Livewire\Livewire;

it('ordena el menú del panel como WooCommerce', function () {
    $admin = User::factory()->withRole(Role::Superadmin)->create();

    $this->actingAs($admin)->get('/admin')->assertOk()
        ->assertSeeInOrder(['Escritorio', 'Comercio', 'Pedidos', 'Clientes', 'Fabricación', 'Devoluciones', 'Facturas',
            'Productos', 'Todos los productos', 'Categorías', 'Prendas base',
            'Análisis', 'Informes', 'Tiendas', 'Artistas',
            'Ajustes', 'Datos de facturación', 'Zonas de envío', 'Transportistas', 'Textos legales',
            'Herramientas', 'Usuarios', 'Copias de seguridad', 'Registro de seguridad', 'Estado del sistema', 'Desarrollo'])
        ->assertSee('Estado de la tienda')
        ->assertSee('Ventas netas este mes');
});

it('no enseña las ventas del Escritorio a quien no es superadministrador', function () {
    $maker = User::factory()->withRole(Role::Manufacturer)->create();

    $this->actingAs($maker)->get('/admin')->assertOk()->assertDontSee('Estado de la tienda');
});

it('llama a los estados del pedido como WooCommerce', function () {
    expect(OrderStatus::processing())->toBe([OrderStatus::Paid, OrderStatus::InProduction, OrderStatus::ReadyToShip])
        ->and(OrderStatus::Delivered->label())->toBe('Completado')
        ->and(OrderStatus::Paid->label())->toBe('Procesando · por fabricar');
});

it('abre todas las pestañas y filtros de las listas del panel', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    foreach (['todos', 'pendiente', 'procesando', 'enviados', 'completados', 'cancelados', 'a_revisar'] as $tab) {
        $this->get("/admin/pedidos?tab={$tab}")->assertOk();
    }
    foreach (['por_revisar', 'esperando', 'dinero_pendiente'] as $tab) {
        $this->get("/admin/devoluciones?tab={$tab}")->assertOk();
    }

    Livewire::test(ListOrders::class)->filterTable('review')->assertOk();
    Livewire::test(ListSecurityEvents::class)->filterTable('dates', ['from' => '2026-01-01', 'until' => '2026-12-31'])->assertOk();
    Livewire::test(LegalTexts::class)->filterTable('scope', 'market')->filterTable('state', 'draft')->assertOk();
});
