import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    /**
     * Server config — forzamos IPv4 (127.0.0.1) en lugar del default que en Windows
     * usa IPv6 ([::1]:5173).
     *
     * Razón: Content Security Policy de Chrome NO procesa correctamente la notación
     * IPv6 con corchetes (`http://[::1]:5173`) como host-source. CSP con `127.0.0.1:5173`
     * funciona perfecto. Esto NO afecta producción (donde Vite no corre) — solo dev local.
     */
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
    },

    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
