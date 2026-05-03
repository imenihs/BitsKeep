// マスタ管理画面のタブID。URL正規化とタブ切替の許可リストとして使う。
export const MASTER_TAB_IDS = ['package-groups', 'packages', 'part-categories', 'spec-types', 'spec-candidates', 'common-spec-types', 'tolerance-spec-types', 'spec-templates'];

// 旧URL/旧タブ名から現行タブへ寄せる互換マップ。
const LEGACY_TAB_MAP = {
    categories: 'part-categories',
    'spec-groups': 'part-categories',
};

/**
 * 目的: URLや旧タブ名を現行のマスタ管理タブIDへ正規化する。
 * 機能: 旧名称を置換し、未知タブはパッケージ分類へフォールバックする。
 * 入力: 任意のタブID文字列。
 * 出力: MASTER_TAB_IDS内のタブID。
 * 動作条件: MASTER_TAB_IDSが画面で使うタブ定義と一致していること。
 * 副作用: なし。
 */
export const normalizeTab = (tab) => {
    const normalized = LEGACY_TAB_MAP[tab] ?? tab;
    return MASTER_TAB_IDS.includes(normalized) ? normalized : 'package-groups';
};

/**
 * 目的: 初期表示タブをURLまたはdata属性から決定する。
 * 機能: query、hash、app data属性の順に参照し、normalizeTabで安全化する。
 * 入力: #app要素。
 * 出力: 初期タブID。
 * 動作条件: ブラウザのwindow.locationが利用できること。
 * 副作用: なし。
 */
export const tabFromUrl = (appEl) => {
    const params = new URLSearchParams(window.location.search);
    const fromQuery = params.get('tab');
    const fromHash = window.location.hash?.startsWith('#tab=') ? window.location.hash.slice(5) : '';
    return normalizeTab(fromQuery || fromHash || appEl?.dataset?.tab);
};

/**
 * 目的: URLから初期選択する部品分類IDを取り出す。
 * 機能: group_idを正の整数へ変換し、不正値はnullへ落とす。
 * 入力: なし。
 * 出力: 部品分類IDまたはnull。
 * 動作条件: ブラウザのwindow.locationが利用できること。
 * 副作用: なし。
 */
export const specGroupIdFromUrl = () => {
    const value = Number.parseInt(new URLSearchParams(window.location.search).get('group_id') ?? '', 10);
    return Number.isFinite(value) && value > 0 ? value : null;
};

/**
 * 目的: 現在タブをURLへ反映する。
 * 機能: tab queryを更新し、旧hash形式を消してhistoryへpush/replaceする。
 * 入力: タブIDとreplace指定。
 * 出力: なし。
 * 動作条件: history APIが利用できるブラウザで実行すること。
 * 副作用: ブラウザURL履歴を更新する。
 */
export const syncTabToUrl = (tab, { replace = false } = {}) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', normalizeTab(tab));
    if (url.hash?.startsWith('#tab=')) url.hash = '';
    const method = replace ? 'replaceState' : 'pushState';
    window.history?.[method]?.({ tab: normalizeTab(tab) }, '', url);
};

/**
 * 目的: スペック系タブの選択部品分類をURLへ反映する。
 * 機能: 対象タブ以外では何もせず、group_id queryだけを同期する。
 * 入力: activeTab ref、部品分類ID、replace指定。
 * 出力: なし。
 * 動作条件: activeTab.valueが現行タブIDであること。
 * 副作用: ブラウザURL履歴を更新する。
 */
export const syncSpecGroupToUrl = (activeTab, groupId, { replace = true } = {}) => {
    if (!['spec-types', 'spec-candidates', 'common-spec-types', 'tolerance-spec-types', 'spec-templates'].includes(activeTab.value)) return;
    const url = new URL(window.location.href);
    const normalizedTab = normalizeTab(activeTab.value);
    url.searchParams.set('tab', normalizedTab);
    if (groupId) url.searchParams.set('group_id', String(groupId));
    else url.searchParams.delete('group_id');
    const method = replace ? 'replaceState' : 'pushState';
    window.history?.[method]?.({ tab: normalizedTab, group_id: groupId ? String(groupId) : null }, '', url);
};

/** 目的: 編集前スナップショット用に値をJSON複製する。機能: Vue proxyを素の値へ落とす。入力: 任意値。出力: 複製値。動作条件: JSON化可能な値であること。副作用: なし。 */
export const clone = (value) => JSON.parse(JSON.stringify(value));

/** 目的: モーダル変更有無を判定する。機能: JSON文字列比較でフォーム差分を検出する。入力: 比較する2値。出力: 真偽値。動作条件: JSON化可能な値であること。副作用: なし。 */
export const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

/** 目的: アクティブなマスタ行だけを抽出する。機能: deleted_atが空の行を残す。入力: 行配列。出力: アクティブ行配列。動作条件: 行がdeleted_atを持つこと。副作用: なし。 */
export const splitActive = (items) => items.filter((item) => !item.deleted_at);

/** 目的: アーカイブ済みマスタ行だけを抽出する。機能: deleted_atがある行を残す。入力: 行配列。出力: アーカイブ行配列。動作条件: 行がdeleted_atを持つこと。副作用: なし。 */
export const splitArchived = (items) => items.filter((item) => item.deleted_at);

/** 目的: 末尾追加時の並び順を決める。機能: アクティブ行の最後のsort_orderへ10加算する。入力: 行配列。出力: 次のsort_order。動作条件: sort_orderが数値または未設定であること。副作用: なし。 */
export const nextSortOrder = (items) => (splitActive(items).at(-1)?.sort_order ?? 0) + 10;

/** 目的: 複製作成時の仮名称を作る。機能: 元名称へコピー接尾辞を付ける。入力: 元名称。出力: 複製名。動作条件: nameが文字列であること。副作用: なし。 */
export const copyName = (name) => `${name} コピー`;

/**
 * 目的: 未保存変更があるモーダルを閉じる前に確認する。
 * 機能: snapshotと現フォームを比較し、必要なら確認モーダルを挟んで閉じる。
 * 入力: 確認関数、対象モーダル、スナップショット。
 * 出力: なし。
 * 動作条件: modal.open/formとask関数が利用できること。
 * 副作用: modal.openをfalseへ更新する場合がある。
 */
export const closeModalWithConfirm = async (ask, modal, snapshot) => {
    if (modal.open && !same(modal.form, snapshot) && !await ask('未保存の変更があります。閉じてもよいですか？')) return;
    modal.open = false;
};

/** 目的: 部品分類詳細から候補スペック詳細配列を取り出す。機能: snake/camelのAPI差を吸収する。入力: 部品分類。出力: 候補配列。動作条件: groupがAPIレスポンスであること。副作用: なし。 */
export const specGroupSpecTypes = (group) => group?.spec_types ?? group?.specTypes ?? [];

/** 目的: 部品分類詳細からテンプレート配列を取り出す。機能: 未読込時は空配列へ正規化する。入力: 部品分類。出力: テンプレート配列。動作条件: groupがAPIレスポンスであること。副作用: なし。 */
export const specGroupTemplates = (group) => group?.templates ?? [];

/**
 * 目的: 部品分類詳細の候補数サマリを作る。
 * 機能: 必須/推奨/任意/テンプレート件数を画面カード用に集計する。
 * 入力: 部品分類詳細。
 * 出力: 件数サマリオブジェクト。
 * 動作条件: spec_types/templatesが読込済み、または未読込を空配列扱いできること。
 * 副作用: なし。
 */
export const specGroupCandidateCounts = (group) => {
    const members = specGroupSpecTypes(group);
    // 目的: マスタ管理画面のis Commonを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const isCommon = (item) => (item?.spec_scope ?? 'group_local') === 'common';
    // 目的: マスタ管理画面のis Toleranceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const isTolerance = (item) => (item?.spec_kind ?? 'normal') === 'tolerance';

    return {
        total: members.length,
        local: members.filter((item) => !isCommon(item) && !isTolerance(item)).length,
        common: members.filter((item) => isCommon(item) && !isTolerance(item)).length,
        tolerance: members.filter(isTolerance).length,
        templates: specGroupTemplates(group).length,
        templateItems: specGroupTemplates(group).reduce((sum, template) => sum + (template.items?.length ?? 0), 0),
        series: Number(group?.series_count ?? 0),
    };
};

/** 目的: タブ別サイドバー件数を取得する。機能: タブ種別に応じたAPI countフィールドを読む。入力: 部品分類とタブID。出力: 件数。動作条件: groupがcountフィールドを持つこと。副作用: なし。 */
export const specGroupSidebarCount = (group, activeTab) => {
    // 目的: マスタ管理画面のcount Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const countValue = (value) => Number(value ?? 0);
    if (activeTab === 'spec-types') return countValue(group?.owned_spec_type_count);
    if (activeTab === 'spec-candidates') return countValue(group?.usage_count);
    if (activeTab === 'spec-templates') return countValue(group?.template_count);
    if (activeTab === 'common-spec-types') return countValue(group?.common_candidate_count);
    if (activeTab === 'tolerance-spec-types') return countValue(group?.tolerance_candidate_count);
    return 0;
};

/** 目的: サイドバー件数の短い表示文字列を作る。機能: タブ別件数へ登録ラベルを付ける。入力: 部品分類とタブID。出力: 表示文字列。動作条件: specGroupSidebarCountが使えること。副作用: なし。 */
export const specGroupSidebarMeta = (group, activeTab) => `登録:${specGroupSidebarCount(group, activeTab)}個`;

/** 目的: 部品分類の系列生成モードを短く表示する。機能: mode値を日本語ラベルへ変換する。入力: 部品分類。出力: ラベルまたは空文字。動作条件: component_series_modeがAPIから返ること。副作用: なし。 */
export const specGroupSeriesModeLabel = (group) => ({
    series_recommended: 'シリーズ登録を推奨',
    series_optional: 'シリーズ登録も使う',
    single: '',
})[group?.series_management_mode] ?? '';
