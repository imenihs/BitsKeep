import './bootstrap';
import { createApp } from 'vue';
import ProjectComboBox from './components/ProjectComboBox.vue';

const THEME_KEY = 'bitskeep-theme';

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
}

function resolveInitialTheme() {
    const saved = window.localStorage.getItem(THEME_KEY);
    if (saved === 'light' || saved === 'dark') return saved;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function ensureThemeToggle() {
    const current = resolveInitialTheme();
    applyTheme(current);

    if (document.querySelector('[data-theme-toggle]')) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.themeToggle = 'true';
    button.className = 'theme-toggle-fab';

    const syncLabel = () => {
        const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        button.textContent = theme === 'dark' ? '☀️' : '🌙';
        button.setAttribute('aria-label', theme === 'dark' ? 'ライトモードへ切り替え' : 'ダークモードへ切り替え');
        button.title = theme === 'dark' ? 'ライトモード' : 'ダークモード';
    };

    button.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        window.localStorage.setItem(THEME_KEY, next);
        applyTheme(next);
        syncLabel();
    });

    syncLabel();
    // ヘッダーのユーザーエリアに挿入、なければbodyに追加
    const userArea = document.querySelector('.app-shell-user');
    if (userArea) {
        userArea.appendChild(button);
    } else {
        document.body.appendChild(button);
    }
}

ensureThemeToggle();

// 各ページのVueアプリを動的マウント
// Bladeテンプレートが <div id="app" data-page="components-list"> を持つ
const container = document.getElementById('app');
const page = container?.dataset?.page;

if (container && page) {
    import(`./pages/${page}.js`).then(({ default: setup }) => {
        // Blade が返した HTML をそのまま Vue テンプレートとして再利用する。
        const template = container.innerHTML;
        const app = createApp({ template, setup });
        // 全ページで利用できる共通コンポーネントを登録
        app.component('ProjectComboBox', ProjectComboBox);
        app.mount(container);
    }).catch(() => {
        console.error(`Page module not found: ${page}`);
    });
}
