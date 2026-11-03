<?php

/*
| Reglas de arquitectura del monolito modular (docs/adr/0001-monolito-modular.md).
*/

arch('no quedan funciones de depuración')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('el código usa tipos estrictos de PHP')
    ->preset()->php();

arch('los contratos de un módulo no dependen de su infraestructura')
    ->expect('App\Modules\Identity\Contracts')
    ->not->toUse('App\Modules\Identity\Infrastructure');

// Un módulo solo puede usar de otro sus Contracts (y eventos). Se comprueba para cada par.
$modules = array_map('basename', glob(__DIR__.'/../../app/Modules/*', GLOB_ONLYDIR) ?: []);

foreach ($modules as $module) {
    arch("el dominio de {$module} no depende de la web, del panel ni de proveedores")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->not->toUse(['Illuminate\Http', 'Filament', 'Livewire', 'Stripe']);

    foreach (array_diff($modules, [$module]) as $other) {
        arch("{$module} solo usa los contratos de {$other}")
            ->expect("App\\Modules\\{$module}")
            ->not->toUse([
                "App\\Modules\\{$other}\\Domain",
                "App\\Modules\\{$other}\\Application",
                "App\\Modules\\{$other}\\Infrastructure",
            ]);
    }
}
