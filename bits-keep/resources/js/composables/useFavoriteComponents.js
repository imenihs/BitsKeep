import { ref } from 'vue';
import { api } from '../api.js';

const PREFERENCE_KEY = 'favorite_component_ids';
const favoriteIds = ref([]);
const loaded = ref(false);

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

function isFavorite(componentId) {
    return favoriteIds.value.includes(Number(componentId));
}

export function useFavoriteComponents() {
    return {
        favoriteIds,
        loadFavorites,
        toggleFavorite,
        isFavorite,
    };
}
