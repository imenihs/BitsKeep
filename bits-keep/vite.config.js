import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
// import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                 'resources/js/app.js',
                 'resources/css/theme.css',
                 'resources/css/components/component-list.css',
                 'resources/js/components/component-list.js',
                ],
            refresh: true,
        }),
        // tailwindcss(),
        vue(),
    ],
});
