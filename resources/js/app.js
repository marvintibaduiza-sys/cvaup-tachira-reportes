import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

/**
 * SEGURIDAD — Anti-bfcache (post-test 1.5)
 *
 * PROBLEMA: tras logout, presionar "atrás" en el navegador mostraba el dashboard
 * cacheado por el bfcache (back/forward cache) de Chrome. Aunque enviamos
 * `Cache-Control: no-store`, Chrome a veces lo ignora con SPAs como Inertia.
 *
 * SOLUCIÓN: el evento `pageshow` se dispara SIEMPRE que la página se muestra,
 * pero con la propiedad `event.persisted = true` SOLO cuando viene del bfcache.
 * Si detectamos eso, forzamos un reload completo — la nueva request pasa por el
 * servidor, y sin sesión activa redirige a /login.
 *
 * Costo: ~5 líneas de JS, 0 impacto en UX normal (solo se activa al usar back/forward).
 *
 * Ref: https://web.dev/articles/bfcache#how-to-test-bfcache
 */
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
