import './bootstrap';
import { createApp } from 'vue';
import ProjectComboBox from './components/ProjectComboBox.vue';
import ConfirmLeaveModal from './components/ConfirmLeaveModal.vue';

const THEME_KEY = 'bitskeep-theme';

// 目的: 画面モジュールのapply Themeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
}

// 目的: 画面モジュールのresolve Initial Themeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 画面モジュールの初期化後に呼び出す。副作用: なし。
function resolveInitialTheme() {
    const saved = window.localStorage.getItem(THEME_KEY);
    if (saved === 'light' || saved === 'dark') return saved;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

// 目的: 画面モジュールのensure Theme Toggleを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function ensureThemeToggle() {
    const current = resolveInitialTheme();
    applyTheme(current);

    if (document.querySelector('[data-theme-toggle]')) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.themeToggle = 'true';
    button.className = 'theme-toggle-fab';

    // 目的: 画面モジュールのsync Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
        // ConfirmLeaveModal を末尾に差し込むことで全ページで自前確認モーダルが使える。
        const template = container.innerHTML + '<ConfirmLeaveModal />';
        const app = createApp({ template, setup });
        // 全ページで利用できる共通コンポーネントを登録
        app.component('ProjectComboBox', ProjectComboBox);
        app.component('ConfirmLeaveModal', ConfirmLeaveModal);
        // v-esc ディレクティブ: modal-overlay の v-if と連動してESCリスナーを自動ON/OFF
        // v-if=false → DOM から外れた瞬間に unmounted が走りリスナーが消える
        app.directive('esc', {
            mounted(el, { value }) {
                el._escValue = value;
                el._escHandler = async (e) => {
                    if (e.key !== 'Escape') return;
                    e.preventDefault();
                    await el._escValue();
                };
                window.addEventListener('keydown', el._escHandler);
            },
            updated(el, { value }) {
                // バインド先関数が差し替わっても最新を参照する
                el._escValue = value;
            },
            unmounted(el) {
                window.removeEventListener('keydown', el._escHandler);
                delete el._escValue;
                delete el._escHandler;
            },
        });
        app.mount(container);
    }).catch(() => {
        console.error(`Page module not found: ${page}`);
    });
}
