import { ref } from 'vue';

const STORAGE_KEY = 'bitskeep-stock-order-draft';
const orderDraft = ref(loadDraft());

function loadDraft() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function persistDraft() {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(orderDraft.value));
}

function upsertOrderItem(item) {
    const index = orderDraft.value.findIndex((draft) => draft.id === item.id);
    if (index >= 0) {
        orderDraft.value.splice(index, 1, item);
    } else {
        orderDraft.value.push(item);
    }
    persistDraft();
}

function removeOrderItem(id) {
    const index = orderDraft.value.findIndex((draft) => draft.id === id);
    if (index >= 0) {
        orderDraft.value.splice(index, 1);
        persistDraft();
    }
}

function replaceOrderDraft(items) {
    orderDraft.value = Array.isArray(items) ? items : [];
    persistDraft();
}

function clearOrderDraft() {
    replaceOrderDraft([]);
}

export function useStockOrderDraft() {
    return {
        orderDraft,
        upsertOrderItem,
        removeOrderItem,
        replaceOrderDraft,
        clearOrderDraft,
    };
}
