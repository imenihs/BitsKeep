import { api } from '../../api.js';

/**
 * マスタ一覧のドラッグ&ドロップ並び替え処理を生成する。
 * 目的: 分類一覧など、sort_order を持つ単純な一覧の並び替え保存を共通化する。
 * 入力: 並び替え対象 ref、保存先URL/本文を作る関数、再取得関数、ドラッグ状態 ref、通知関数。
 * 出力: Vue テンプレートの dragstart/dragover/dragend/drop へ渡す操作関数群。
 * 動作条件: 対象項目に id があり、buildPayload が PUT 先と基本本文を返すこと。
 * 副作用: 一覧を楽観的に並び替え、API 保存後に再取得し、成功/失敗 toast を表示する。
 */
export function createMasterReorderDnd({ arr, buildPayload, fetchFn, dragSrc, dragTarget, toastSuccess, toastError }) {
    return {
        start: (i) => { dragSrc.value = i; dragTarget.value = i; },
        over: (e, i) => { e.preventDefault(); dragTarget.value = i; },
        end: () => { dragSrc.value = null; dragTarget.value = null; },
        drop: async (i) => {
            const from = dragSrc.value;
            dragSrc.value = null;
            dragTarget.value = null;
            if (from === null || from === i) return;
            const items = [...arr.value];
            const [moved] = items.splice(from, 1);
            items.splice(i, 0, moved);
            arr.value = items;
            try {
                await Promise.all(items.map((item, idx) => api.put(buildPayload(item).url, {
                    ...buildPayload(item).body,
                    sort_order: (idx + 1) * 10,
                })));
                toastSuccess('並び順を更新しました');
                await fetchFn();
            } catch (e) {
                toastError(e.message);
                await fetchFn();
            }
        },
    };
}

/**
 * パッケージ詳細の同一分類内並び替え処理を生成する。
 * 目的: パッケージ分類配下の詳細一覧を、専用 reorder API へ保存する。
 * 入力: 表示中パッケージ一覧、選択中分類ID、再取得関数、ドラッグ状態 ref、通知関数。
 * 出力: Vue テンプレートの D&D 操作関数群。
 * 動作条件: selectedPackageGroupId が有効で、activePackages が同一分類の一覧であること。
 * 副作用: 一覧を楽観的に更新し、/package-groups/{id}/packages/reorder へ保存する。
 */
export function createPackageDnd({ activePackages, selectedPackageGroupId, fetchPackages, dragSrc, dragTarget, toastSuccess, toastError }) {
    return {
        start: (i) => { dragSrc.value = i; dragTarget.value = i; },
        over: (e, i) => { e.preventDefault(); dragTarget.value = i; },
        end: () => { dragSrc.value = null; dragTarget.value = null; },
        drop: async (i) => {
            const from = dragSrc.value;
            dragSrc.value = null;
            dragTarget.value = null;
            if (from === null || from === i) return;
            const items = [...activePackages.value];
            const [moved] = items.splice(from, 1);
            items.splice(i, 0, moved);
            activePackages.value = items;
            try {
                await api.put(`/package-groups/${selectedPackageGroupId.value}/packages/reorder`, {
                    package_ids: items.map((item) => item.id),
                });
                toastSuccess('並び順を更新しました');
                await fetchPackages();
            } catch (e) {
                toastError(e.message);
                await fetchPackages();
            }
        },
    };
}

/**
 * 候補スペック詳細の並び替え処理を生成する。
 * 目的: 部品分類に紐づく候補スペック詳細の表示順を即時保存する。
 * 入力: 選択中部品分類 computed、候補保存関数、ドラッグ状態 ref。
 * 出力: Vue テンプレートの D&D 操作関数群。
 * 動作条件: currentSpecGroup に spec_types が読み込まれていること。
 * 副作用: currentSpecGroup.spec_types を並び替え、候補同期 API を呼ぶ保存関数へ委譲する。
 */
export function createCandidateMemberDnd({ currentSpecGroup, persistSpecGroupMemberMutation, dragSrc, dragTarget }) {
    return {
        start: (i) => { dragSrc.value = i; dragTarget.value = i; },
        over: (e, i) => { e.preventDefault(); dragTarget.value = i; },
        end: () => { dragSrc.value = null; dragTarget.value = null; },
        drop: async (i) => {
            const from = dragSrc.value;
            dragSrc.value = null;
            dragTarget.value = null;
            if (from === null || from === i || !currentSpecGroup.value) return;
            const members = [...(currentSpecGroup.value.spec_types ?? [])];
            const [moved] = members.splice(from, 1);
            members.splice(i, 0, moved);
            await persistSpecGroupMemberMutation(() => {
                currentSpecGroup.value.spec_types = members;
                return true;
            }, '並び順を更新しました');
        },
    };
}
