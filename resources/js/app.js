import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// PrimeVue Imports
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import { definePreset } from '@primevue/themes';
import 'primeicons/primeicons.css';

const appName = import.meta.env.VITE_APP_NAME || 'SIVOP - PCPB';

// Preset customizado baseado no Aura
const CustomPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{zinc.50}',
            100: '{zinc.100}',
            200: '{zinc.200}',
            300: '{zinc.300}',
            400: '{zinc.400}',
            500: '{zinc.500}',
            600: '{zinc.600}',
            700: '{zinc.700}',
            800: '{zinc.800}',
            900: '{zinc.900}',
            950: '{zinc.950}'
        }
    },
    components: {
        menubar: {
            root: {
                background: '#000000',
                borderColor: 'transparent',
                borderRadius: '0',
                color: '#ffffff'
            },
            item: {
                focusBackground: '#c1a85a',
                activeBackground: '#c1a85a',
                color: '#ffffff',
                focusColor: '#ffffff',
                activeColor: '#ffffff',
                borderRadius: '0.375rem',
                icon: {
                    color: '#ffffff',
                    focusColor: '#ffffff',
                    activeColor: '#ffffff'
                }
            },
            submenuIcon: {
                color: '#ffffff',
                focusColor: '#ffffff',
                activeColor: '#ffffff'
            },
            submenu: {
                background: '#000000'
            }
        }
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: CustomPreset,
                    options: {
                        darkModeSelector: false,
                        cssLayer: false
                    }
                },
                ripple: true
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
