import { ref } from 'vue';

const toasts = ref([]);
let _id = 0;

export function useToast() {
    const show = (msg, type = 'success', duration = 2800) => {
        const id = ++_id;
        toasts.value.push({ id, msg, type });
        setTimeout(() => {
            toasts.value = toasts.value.filter(t => t.id !== id);
        }, duration);
    };
    return {
        toasts,
        toastSuccess: (msg) => show(msg, 'success'),
        toastError:   (msg) => show(msg, 'error'),
    };
}
