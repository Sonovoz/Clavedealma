import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Tipografías alojadas en nuestro servidor (@fontsource): nada se carga de Google Fonts.
// Cada diseño de tienda declara sus entradas en config/storefront.php y deben figurar aquí.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/storefront/base.css',
                'resources/js/storefront/app.js',
                'resources/css/storefront/designs/farruquito.css',
                'resources/js/storefront/designs/farruquito.js',
                'resources/css/storefront/matriz.css',
                'resources/js/storefront/matriz.js',
                'resources/css/storefront/checkout.css',
                'resources/js/storefront/checkout.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
