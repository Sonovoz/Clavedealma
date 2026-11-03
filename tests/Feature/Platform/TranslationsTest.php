<?php

it('muestra los errores de validación en español', function () {
    app()->setLocale('es');

    expect(__('validation.required', ['attribute' => 'nombre']))->toBe('Nombre es obligatorio.')
        ->and(__('validation.unique', ['attribute' => 'subdominio']))->toBe('Ya existe un registro con ese valor en subdominio.');
});
