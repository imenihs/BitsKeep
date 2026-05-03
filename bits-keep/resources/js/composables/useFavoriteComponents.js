import { ref } from 'vue';
import { api } from '../api.js';

const PREFERENCE_KEY = 'favorite_component_ids';
const favoriteIds = ref([]);
const loaded = ref(false);

// 目的: Vue共通composableのnormalize Idsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: Vue共通composableの初期化後に呼び出す。副作用: なし。
const normalizeIds = (value) => {
    if (!Array.isArray(value)) return [];
    return [...new Set(value.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0))];
};

async function persistFavorites() {
    await api.put(`/preferences/${PREFERENCE_KEY}`, {
        value: favoriteIds.value,
    });
}

async function loadFavorites(force = false) {
    if (loaded.value && !force) return favoriteIds.value;
    const res = await api.get(`/preferences/${PREFERENCE_KEY}`);
    favoriteIds.value = normalizeIds(res?.data?.value ?? res?.data?.data?.value ?? []);
    loaded.value = true;
    return favoriteIds.value;
}

async function toggleFavorite(componentId) {
    const id = Number(componentId);
    if (!Number.isInteger(id) || id <= 0) return favoriteIds.value;
    if (favoriteIds.value.includes(id)) {
        favoriteIds.value = favoriteIds.value.filter((value) => value !== id);
    } else {
        favoriteIds.value = [...favoriteIds.value, id];
    }
    await persistFavorites();
    return favoriteIds.value;
}

// 目的: Vue共通composableのis Favoriteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: Vue共通composableの初期化後に呼び出す。副作用: なし。
function isFavorite(componentId) {
    return favoriteIds.value.includes(Number(componentId));
}

// 目的: Vue共通composableのFavorite Componentsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useFavoriteComponents() {
    return {
        favoriteIds,
        loadFavorites,
        toggleFavorite,
        isFavorite,
    };
}
