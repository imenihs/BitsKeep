<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>保管棚管理 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($isAdmin = auth()->user()->isAdmin())
@include('partials.app-header', ['current' => '保管棚管理'])
<div id="app" data-page="location-list" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => 'マスタ管理', 'url' => route('master.index')],
    ['label' => '保管棚管理', 'current' => true],
  ]])

  <header class="flex justify-between items-center mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">保管棚管理</h1>
    <div class="flex items-center gap-2">
      <button @click="inventoryMode = !inventoryMode"
        class="px-4 py-2 rounded text-sm border transition-colors"
        :class="inventoryMode ? 'bg-[var(--color-tag-warning)] text-white border-[var(--color-tag-warning)]' : 'border-[var(--color-border)]'">
        @{{ inventoryMode ? '棚卸し中 ▸ 保存' : '棚卸しモード' }}
      </button>
      <button v-if="inventoryMode" @click="saveInventory" class="btn btn-primary px-4 py-2 rounded text-sm">確定</button>
      @if ($isAdmin)
      <button @click="openAdd" class="btn btn-primary px-4 py-2 rounded text-sm"><span class="feature-lock">管</span> + 棚を追加</button>
      @else
      <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)]">
        <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ 棚を追加</span></div>
        <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
      </div>
      @endif
    </div>
  </header>

  <!-- エラーカード -->
  <div v-if="fetchError" class="card p-5 bg-[var(--color-card-even)] mb-4 flex items-start gap-3 text-sm border border-[var(--color-tag-eol)]">
    <span class="text-[var(--color-tag-eol)] text-lg leading-none">⚠</span>
    <div class="flex-1">
      <div class="font-semibold text-[var(--color-tag-eol)]">棚情報の取得に失敗しました</div>
      <div class="opacity-70 mt-0.5">@{{ fetchError }}</div>
    </div>
    <button @click="fetchLocations" class="px-3 py-1.5 rounded border border-[var(--color-border)] text-xs">再試行</button>
  </div>

  <!-- 棚卸し警告バナー -->
  <div v-if="inventoryMode" class="mb-4 p-3 rounded bg-[var(--color-tag-warning)]/10 border border-[var(--color-tag-warning)] text-sm flex items-center gap-2">
    ⚠ 棚卸しモード中です。実数を入力して「確定」を押してください。
  </div>

  <!-- グループ別テーブル -->
  <div v-for="(locs, group) in grouped" :key="group" class="mb-6">
    <h2 class="font-bold text-sm mb-2 opacity-60 uppercase tracking-wide">@{{ group }}</h2>
    <div class="card overflow-hidden p-0">
      <table class="w-full text-sm">
        <thead class="bg-[var(--color-card-even)]">
          <tr>
            <th class="text-left px-4 py-2 font-semibold">棚コード</th>
            <th class="text-left px-4 py-2 font-semibold">名称</th>
            <th class="text-left px-4 py-2 font-semibold">在庫数</th>
            <th v-if="inventoryMode" class="text-left px-4 py-2 font-semibold">実数入力</th>
            <th v-if="inventoryMode" class="text-left px-4 py-2 font-semibold">差分</th>
            <th class="text-right px-4 py-2 font-semibold w-24">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(loc, i) in locs" :key="loc.id"
            class="border-t border-[var(--color-border)]"
            :class="i % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'">
            <td class="px-4 py-2 font-mono font-medium">@{{ loc.code }}</td>
            <td class="px-4 py-2 opacity-70">
              <div>@{{ loc.name || '—' }}</div>
              <div class="mt-1 text-xs opacity-60">代表棚: @{{ loc.primary_component_count ?? 0 }}件 / 子棚: @{{ loc.child_count ?? 0 }}</div>
            </td>
            <td class="px-4 py-2 font-mono">@{{ loc.stock_count ?? 0 }}</td>
            <td v-if="inventoryMode" class="px-4 py-2">
              <input v-model.number="countInputs[loc.id]" type="number" min="0"
                class="input-text text-sm py-1 w-24" />
            </td>
            <td v-if="inventoryMode" class="px-4 py-2 font-mono font-bold"
              :class="getCountDiff(loc) > 0 ? 'text-[var(--color-tag-ok)]' : getCountDiff(loc) < 0 ? 'text-[var(--color-tag-eol)]' : 'opacity-40'">
              @{{ getCountDiff(loc) > 0 ? '+' : '' }}@{{ getCountDiff(loc) || '—' }}
            </td>
            <td class="px-4 py-2 text-right">
              <div class="inline-flex items-center gap-2 flex-wrap justify-end">
                <span v-if="loc.deleted_at" class="inline-flex items-center rounded-full border border-amber-400/50 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                  廃止
                </span>
                <button @click="openEdit(loc)" class="px-3 py-1.5 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)] font-medium">編集</button>
                <button v-if="!loc.deleted_at" @click="archiveLocation(loc)" class="px-3 py-1.5 text-xs border border-amber-400 text-amber-700 rounded hover:bg-amber-50 font-medium">廃止</button>
                <button v-else @click="restoreLocation(loc)" class="px-3 py-1.5 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50 font-medium">復元</button>
                <button v-if="loc.can_force_delete" @click="forceDeleteLocation(loc)" class="px-3 py-1.5 text-xs border border-red-400 text-red-600 rounded hover:bg-red-50 font-medium">完全削除</button>
              </div>
              <div v-if="loc.deleted_at && !loc.can_force_delete" class="mt-1 text-[10px] opacity-60">@{{ loc.force_delete_reason }}</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div v-if="!loading && Object.keys(grouped).length === 0" class="text-center py-20 opacity-40">棚が登録されていません</div>

  <!-- 追加/編集モーダル -->
  <div v-if="locationModal.open" class="modal-overlay">
    <div class="modal-window modal-sm p-6">
      <h3 class="font-bold mb-4">@{{ locationModal.isEdit ? '棚を編集' : '棚を追加' }}</h3>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs font-semibold mb-1">棚コード <span class="text-[var(--color-tag-eol)]">*</span></label>
          <input v-model="locationModal.form.code" type="text" class="input-text w-full" placeholder="例: A-1" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">名称</label>
          <input v-model="locationModal.form.name" type="text" class="input-text w-full" placeholder="任意" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">グループ</label>
          <input v-model="locationModal.form.group" type="text" class="input-text w-full" placeholder="例: A棚" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">説明</label>
          <input v-model="locationModal.form.description" type="text" class="input-text w-full" placeholder="任意" />
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button @click="closeModal" class="btn text-sm">キャンセル</button>
        <button @click="saveLocation" class="btn btn-primary text-sm">保存</button>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
    <div v-for="t in toasts" :key="t.id" class="px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
      :class="t.type === 'error' ? 'bg-[var(--color-tag-eol)]' : 'bg-[var(--color-accent)]'">@{{ t.msg }}</div>
  </div>
  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => 'マスタ管理', 'url' => route('master.index')],
    ['label' => '保管棚管理', 'current' => true],
  ], 'class' => 'mt-6'])
</div>
</body>
</html>
