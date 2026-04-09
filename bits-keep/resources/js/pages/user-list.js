/**
 * ユーザー管理ページ（SCR-008）
 * admin ロールのみアクセス
 */
import { ref, reactive, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const users = ref([]);

    // 招待モーダル
    const inviteModal = reactive({
        open: false,
        form: { name: '', email: '', role: 'viewer' },
        result: null,
    });

    const fetchUsers = async () => {
        try { const r = await api.get('/users'); users.value = r.data; }
        catch { toastError('ユーザー一覧の取得に失敗しました'); }
    };

    // ロール変更（インライン select）
    const changeRole = async (user, role) => {
        try {
            await api.patch(`/users/${user.id}/role`, { role });
            user.role = role;
            toastSuccess('ロールを変更しました');
        } catch (e) { toastError(e.message); }
    };

    // 有効/無効切り替え
    const toggleActive = async (user) => {
        try {
            const r = await api.patch(`/users/${user.id}/active`, { is_active: !user.is_active });
            user.is_active = r.data.is_active;
            toastSuccess(user.is_active ? '有効化しました' : '無効化しました');
        } catch (e) { toastError(e.message); }
    };

    // 招待
    const invite = async () => {
        try {
            const r = await api.post('/users/invite', inviteModal.form);
            inviteModal.result = r.data;
            await fetchUsers();
        } catch (e) { toastError(e.message); }
    };

    const openInvite = () => {
        Object.assign(inviteModal, { open: true, form: { name: '', email: '', role: 'viewer' }, result: null });
    };

    const roleLabel = (r) => ({ admin: '管理者', editor: '編集者', viewer: '閲覧者' }[r] ?? r);
    const roleBadgeClass = (r) => ({
        admin: 'bg-red-100 text-red-700', editor: 'bg-blue-100 text-blue-700', viewer: 'bg-gray-100 text-gray-600'
    }[r] ?? '');

    onMounted(fetchUsers);
    return { toasts, users, inviteModal, openInvite, invite, changeRole, toggleActive, roleLabel, roleBadgeClass };
}
