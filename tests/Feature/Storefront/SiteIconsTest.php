<?php

/*
| Iconos del sitio (favicon, iOS y Android): la clave de sol de la marca sobre el rojo.
| Se generan con scripts/generar-iconos.py a partir del logo.
*/

it('la web y el panel enlazan el icono del sitio, y los archivos existen', function () {
    foreach (['favicon.ico', 'favicon-16x16.png', 'favicon-32x32.png', 'favicon-96x96.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'site.webmanifest'] as $file) {
        expect(public_path($file))->toBeFile();
        expect(filesize(public_path($file)))->toBeGreaterThan(0);
    }
    expect(json_decode((string) file_get_contents(public_path('site.webmanifest')), true))->name->toBe('Clave de Alma');

    $this->get(route('market'))->assertOk()
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="48x48">', false)
        ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">', false)
        ->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
    $this->get(route('cart.show'))->assertSee('/favicon-96x96.png', false);
    $this->get('/admin/login')->assertSee('favicon-96x96.png', false);
});
