import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Layout from './Components/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'OllieSync';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        // Resolve the page
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        
        // MAGIC FIX: If it is NOT an authentication page (Login/Register), inject our Layout!
        if (!name.startsWith('Auth/')) {
            page.default.layout = page.default.layout || Layout;
        }
        
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: { color: '#4f46e5' }, // Indigo progress bar on top of page during clicks!
});