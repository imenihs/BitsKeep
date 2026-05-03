import { ref } from 'vue';

const STORAGE_KEY = 'bitskeep-stock-order-draft';
const orderDraft = ref(loadDraft());

// 目的: Vue共通composableのload Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function loadDraft() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

// 目的: Vue共通composableのpersist Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function persistDraft() {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(orderDraft.value));
}

// 目的: Vue共通composableのupsert Order Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function upsertOrderItem(item) {
    const index = orderDraft.value.findIndex((draft) => draft.id === item.id);
    if (index >= 0) {
        orderDraft.value.splice(index, 1, item);
    } else {
        orderDraft.value.push(item);
    }
    persistDraft();
}

// 目的: Vue共通composableのremove Order Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function removeOrderItem(id) {
    const index = orderDraft.value.findIndex((draft) => draft.id === id);
    if (index >= 0) {
        orderDraft.value.splice(index, 1);
        persistDraft();
    }
}

// 目的: Vue共通composableのreplace Order Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function replaceOrderDraft(items) {
    orderDraft.value = Array.isArray(items) ? items : [];
    persistDraft();
}

// 目的: Vue共通composableのclear Order Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function clearOrderDraft() {
    replaceOrderDraft([]);
}

// 目的: Vue共通composableのStock Order Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useStockOrderDraft() {
    return {
        orderDraft,
        upsertOrderItem,
        removeOrderItem,
        replaceOrderDraft,
        clearOrderDraft,
    };
}
