<?php

// «Mi cuenta» → «¿Has olvidado tu contraseña?» (ADR 0021). No revela si el email existe.
return [
    'reset' => 'Tu contraseña se ha cambiado. Ya puedes acceder.',
    'sent' => 'Si hay una cuenta con ese email, te hemos enviado el enlace para elegir una contraseña nueva.',
    'throttled' => 'Espera un momento antes de volver a pedirlo.',
    'token' => 'El enlace para cambiar la contraseña no es válido o ha caducado. Pide otro.',
    'user' => 'Si hay una cuenta con ese email, te hemos enviado el enlace para elegir una contraseña nueva.',
];
