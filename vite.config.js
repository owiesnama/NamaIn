import { defineConfig } from 'vite';
import { resolve } from 'path';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    resolve: {
        alias: {
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('chart.js') || id.includes('vue-chartjs')) {
                        return 'vendor-charts';
                    }
                    if (id.includes('filepond')) {
                        return 'vendor-filepond';
                    }
                    if (id.includes('flatpickr')) {
                        return 'vendor-flatpickr';
                    }
                    if (id.includes('pusher-js') || id.includes('laravel-echo')) {
                        return 'vendor-echo';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
