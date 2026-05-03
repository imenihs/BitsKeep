import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                /**
                 * 依存パスからViteの分割チャンク名を決める。
                 * 入力はRollupから渡されるモジュールIDで、戻り値はチャンク名または未指定、ビルド設定以外の副作用はない。
                 */
                manualChunks(id) {
                    if (id.includes('/node_modules/mathjs/lib/esm/expression/')) {
                        return 'vendor-mathjs-expression';
                    }
                    if (id.includes('/node_modules/mathjs/lib/esm/function/')) {
                        return 'vendor-mathjs-function';
                    }
                    if (id.includes('/node_modules/mathjs/lib/esm/type/')) {
                        return 'vendor-mathjs-type';
                    }
                    if (id.includes('/node_modules/mathjs/lib/esm/utils/')) {
                        return 'vendor-mathjs-utils';
                    }
                    if (id.includes('/node_modules/mathjs/') || id.includes('/node_modules/typed-function/')) {
                        return 'vendor-mathjs-core';
                    }
                    if (id.includes('/node_modules/vue/')) {
                        return 'vendor-vue';
                    }
                    if (id.includes('/node_modules/axios/')) {
                        return 'vendor-axios';
                    }
                    return undefined;
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
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: false,
    },
});
