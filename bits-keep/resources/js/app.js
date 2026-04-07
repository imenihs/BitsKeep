import './bootstrap';
import { createApp } from 'vue';

// 各ページのVueアプリを動的マウント
// Bladeテンプレートが <div id="app" data-page="components-list"> を持つ
const page = document.getElementById('app')?.dataset?.page;

if (page) {
    import(`./pages/${page}.js`).then(({ default: setup }) => {
        createApp({ setup }).mount('#app');
    }).catch(() => {
        console.error(`Page module not found: ${page}`);
    });
}
