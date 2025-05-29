// resources/js/app.js
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import Vue3Toasify from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import '../css/app.css'; // Import Tailwind CSS

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(Vue3Toasify, {
                autoClose: 3000, // Auto-close after 3 seconds
                position: 'top-right', // Default position
            }) // Add vue3-toastify plugin
            .mount(el);
    },
});