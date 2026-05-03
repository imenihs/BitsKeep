/**
 * ユーザー管理ページ（SCR-008）
 * admin ロールのみアクセス
 */
import { ref, reactive, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useFormatter } from '../composables/useFormatter.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';

// 目的: 画面モジュールのsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { formatDate } = useFormatter();
    const { ask } = useConfirmModal();
    const users = ref([]);

    // 招待モーダル
    const inviteModal = reactive({
        open: false,
        form: { name: '', email: '', role: 'viewer' },
        result: null,
    });

    // ロール変更モーダル
    const roleModal = reactive({
        open: false,
        user: null,
        selectedRole: null,
    });

    // 名前編集モーダル
    const nameModal = reactive({
        open: false,
        user: null,
        editedName: '',
    });

    // メールアドレス変更モーダル
    const emailModal = reactive({
        open: false,
        user: null,
        editedEmail: '',
    });

    // パスワードリセットモーダル
    const passwordModal = reactive({
        open: false,
        user: null,
        newPassword: '',
        newPasswordConfirmation: '',
    });

    // 目的: 画面モジュールのfetch Usersを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchUsers = async () => {
        try { const r = await api.get('/users'); users.value = r.data; }
        catch { toastError('ユーザー一覧の取得に失敗しました'); }
    };

    // ロール変更モーダルを開く
    // 目的: 画面モジュールのopen Role Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openRoleChange = (user) => {
        roleModal.user = user;
        roleModal.selectedRole = user.role;
        roleModal.open = true;
    };

    // ロール変更を確定
    // 目的: 画面モジュールのconfirm Role Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const confirmRoleChange = async () => {
        if (!roleModal.user || roleModal.selectedRole === roleModal.user.role) {
            roleModal.open = false;
            return;
        }
        try {
            await api.patch(`/users/${roleModal.user.id}/role`, { role: roleModal.selectedRole });
            roleModal.user.role = roleModal.selectedRole;
            toastSuccess('ロールを変更しました');
            roleModal.open = false;
        } catch (e) { toastError(e.message); }
    };

    // 名前編集モーダルを開く
    // 目的: 画面モジュールのopen Name Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openNameEdit = (user) => {
        nameModal.user = user;
        nameModal.editedName = user.name;
        nameModal.open = true;
    };

    // 名前編集を確定
    // 目的: 画面モジュールのconfirm Name Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const confirmNameChange = async () => {
        if (!nameModal.user || !nameModal.editedName.trim()) {
            toastError('名前を入力してください');
            return;
        }
        if (nameModal.editedName === nameModal.user.name) {
            nameModal.open = false;
            return;
        }
        try {
            await api.patch(`/users/${nameModal.user.id}/name`, { name: nameModal.editedName });
            nameModal.user.name = nameModal.editedName;
            toastSuccess('名前を変更しました');
            nameModal.open = false;
        } catch (e) { toastError(e.message); }
    };

    // 有効/無効切り替え（確認付き）
    // 目的: 画面モジュールのtoggle Activeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: なし。
    const toggleActive = async (user) => {
        const action = user.is_active ? '無効化' : '有効化';
        if (!await ask(`${user.name} を${action}しますか？`)) return;
        try {
            const r = await api.patch(`/users/${user.id}/active`, { is_active: !user.is_active });
            user.is_active = r.data.is_active;
            toastSuccess(user.is_active ? '有効化しました' : '無効化しました');
        } catch (e) { toastError(e.message); }
    };

    // メールアドレス変更モーダルを開く
    // 目的: 画面モジュールのopen Email Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openEmailEdit = (user) => {
        emailModal.user = user;
        emailModal.editedEmail = user.email;
        emailModal.open = true;
    };

    // メールアドレス変更を確定
    // 目的: 画面モジュールのconfirm Email Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const confirmEmailChange = async () => {
        if (!emailModal.user || !emailModal.editedEmail.trim()) {
            toastError('メールアドレスを入力してください');
            return;
        }
        if (emailModal.editedEmail === emailModal.user.email) {
            emailModal.open = false;
            return;
        }
        try {
            await api.patch(`/users/${emailModal.user.id}/email`, { email: emailModal.editedEmail });
            emailModal.user.email = emailModal.editedEmail;
            toastSuccess('メールアドレスを変更しました');
            emailModal.open = false;
        } catch (e) { toastError(e.message); }
    };

    // パスワードリセットモーダルを開く
    // 目的: 画面モジュールのopen Password Resetを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openPasswordReset = (user) => {
        passwordModal.user = user;
        passwordModal.newPassword = '';
        passwordModal.newPasswordConfirmation = '';
        passwordModal.open = true;
    };

    // パスワードリセットを確定
    // 目的: 画面モジュールのconfirm Password Resetを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const confirmPasswordReset = async () => {
        if (!passwordModal.newPassword) {
            toastError('新しいパスワードを入力してください');
            return;
        }
        if (passwordModal.newPassword !== passwordModal.newPasswordConfirmation) {
            toastError('パスワードと確認が一致しません');
            return;
        }
        try {
            await api.patch(`/users/${passwordModal.user.id}/password`, {
                password: passwordModal.newPassword,
                password_confirmation: passwordModal.newPasswordConfirmation,
            });
            toastSuccess('パスワードをリセットしました');
            passwordModal.open = false;
        } catch (e) { toastError(e.message); }
    };

    // 招待
    // 目的: 画面モジュールのinviteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const invite = async () => {
        try {
            const r = await api.post('/users/invite', inviteModal.form);
            inviteModal.result = r.data;
            await fetchUsers();
        } catch (e) { toastError(e.message); }
    };

    // 目的: 画面モジュールのopen Inviteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openInvite = () => {
        Object.assign(inviteModal, { open: true, form: { name: '', email: '', role: 'viewer' }, result: null });
    };

    // 目的: 画面モジュールのrole Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const roleLabel = (r) => ({ admin: '管理者', editor: '編集者', viewer: '閲覧者' }[r] ?? r);
    // 目的: 画面モジュールのrole Badge Classを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const roleBadgeClass = (r) => ({
        admin: 'bg-red-100 text-red-700', editor: 'bg-blue-100 text-blue-700', viewer: 'bg-gray-100 text-gray-600'
    }[r] ?? '');
    // 目的: 画面モジュールのprovider Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const providerLabel = (provider) => ({ google: 'Google', github: 'GitHub' }[provider] ?? provider);
    // 目的: 画面モジュールのprovider Summaryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const providerSummary = (user) => {
        const providers = Array.isArray(user.auth_providers) ? user.auth_providers : [];
        if (!providers.length) return '未連携';
        return providers.map((provider) => providerLabel(provider.provider)).join(' / ');
    };

    onMounted(fetchUsers);
    return {
        toasts, users,
        inviteModal, openInvite, invite,
        roleModal, openRoleChange, confirmRoleChange,
        nameModal, openNameEdit, confirmNameChange,
        emailModal, openEmailEdit, confirmEmailChange,
        passwordModal, openPasswordReset, confirmPasswordReset,
        toggleActive, roleLabel, roleBadgeClass, providerLabel, providerSummary, formatDate,
    };
}
