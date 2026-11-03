<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center bg-stone-50 text-stone-900 font-sans">
    {{-- Página provisional. La tienda real se construye en las fases 3 y 8. --}}
    <main class="text-center px-6">
        <h1 class="text-3xl font-semibold tracking-tight">Clave de Alma</h1>
        <p class="mt-3 text-stone-600">Tiendas de artistas. Muy pronto.</p>
    </main>
</body>
</html>
