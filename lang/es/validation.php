<?php

/*
| Mensajes de validación en español. Las reglas no traducidas aquí usan el
| texto en inglés (APP_FALLBACK_LOCALE=en).
*/

return [
    'accepted' => 'Debes aceptar :attribute.',
    'active_url' => ':Attribute no es una URL válida.',
    'after' => ':Attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => ':Attribute debe ser una fecha igual o posterior a :date.',
    'alpha_dash' => ':Attribute solo puede contener letras, números, guiones y guiones bajos.',
    'array' => ':Attribute debe ser una lista.',
    'before' => ':Attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => ':Attribute debe ser una fecha igual o anterior a :date.',
    'between' => [
        'numeric' => ':Attribute debe estar entre :min y :max.',
        'file' => ':Attribute debe pesar entre :min y :max kilobytes.',
        'string' => ':Attribute debe tener entre :min y :max caracteres.',
        'array' => ':Attribute debe tener entre :min y :max elementos.',
    ],
    'boolean' => ':Attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña no es correcta.',
    'date' => ':Attribute no es una fecha válida.',
    'different' => ':Attribute y :other deben ser distintos.',
    'digits' => ':Attribute debe tener :digits dígitos.',
    'email' => ':Attribute debe ser un email válido.',
    'exists' => 'El valor elegido en :attribute no existe.',
    'file' => ':Attribute debe ser un archivo.',
    'filled' => ':Attribute es obligatorio.',
    'gt' => [
        'numeric' => ':Attribute debe ser mayor que :value.',
        'string' => ':Attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'numeric' => ':Attribute debe ser mayor o igual que :value.',
        'string' => ':Attribute debe tener al menos :value caracteres.',
    ],
    'image' => ':Attribute debe ser una imagen.',
    'in' => 'El valor elegido en :attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'lt' => [
        'numeric' => ':Attribute debe ser menor que :value.',
        'string' => ':Attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'numeric' => ':Attribute debe ser menor o igual que :value.',
        'string' => ':Attribute debe tener como máximo :value caracteres.',
    ],
    'max' => [
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'file' => ':Attribute no puede pesar más de :max kilobytes.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
        'array' => ':Attribute no puede tener más de :max elementos.',
    ],
    'mimes' => ':Attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'numeric' => ':Attribute debe ser al menos :min.',
        'file' => ':Attribute debe pesar al menos :min kilobytes.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
        'array' => ':Attribute debe tener al menos :min elementos.',
    ],
    'not_in' => 'El valor elegido en :attribute no es válido.',
    'numeric' => ':Attribute debe ser un número.',
    'password' => [
        'letters' => ':Attribute debe contener al menos una letra.',
        'mixed' => ':Attribute debe contener mayúsculas y minúsculas.',
        'numbers' => ':Attribute debe contener al menos un número.',
        'symbols' => ':Attribute debe contener al menos un símbolo.',
        'uncompromised' => ':Attribute ha aparecido en una filtración de datos. Elige otra.',
    ],
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':Attribute es obligatorio.',
    'required_if' => ':Attribute es obligatorio cuando :other es :value.',
    'required_with' => ':Attribute es obligatorio cuando :values está presente.',
    'same' => ':Attribute y :other deben coincidir.',
    'size' => [
        'numeric' => ':Attribute debe ser :size.',
        'file' => ':Attribute debe pesar :size kilobytes.',
        'string' => ':Attribute debe tener :size caracteres.',
        'array' => ':Attribute debe tener :size elementos.',
    ],
    'string' => ':Attribute debe ser un texto.',
    'unique' => 'Ya existe un registro con ese valor en :attribute.',
    'uploaded' => 'No se ha podido subir :attribute.',
    'url' => ':Attribute debe ser una URL válida.',

    'custom' => [],

    'attributes' => [],
];
