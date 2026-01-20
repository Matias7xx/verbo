import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: [
                'app/**',           // Controllers, Models, etc
                'routes/**',        // Rotas
                'resources/views/**', // Views Blade
            ],
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
    server: { //Necessário para Hot Reload no Docker
        host: '0.0.0.0',  // Permitir conexões externas
        port: 5173,
        hmr: {
            host: 'localhost',  // Host para HMR (atualizar navegador)
        },
        watch: {
            usePolling: true,  // Necessário para Docker
        },
    },
});
