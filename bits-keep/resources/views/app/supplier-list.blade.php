<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>商社管理 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($isAdmin = auth()->user()->isAdmin())
@include('partials.app-header', ['current' => '商社管理'])
<div id="app" data-page="supplier-list" class="px-4 py-4 sm:px-6 sm:py-6 max-w-5xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => 'マスタ管理', 'url' => route('master.index')],
    ['label' => '商社管理', 'current' => true],
  ]])

  <header class="flex justify-between items-center mb-6 pb-4 border-b border-[var(--color-border)]">
    <div>
      <h1 class="text-2xl font-bold">商社管理</h1>
      <p class="text-sm opacity-60 mt-1">発注先・仕入先の登録・編集</p>
    </div>
    @if ($isAdmin)
    <button @click="openAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium"><span class="feature-lock">管</span> + 新規追加</button>
    @else
    <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)]">
      <div class="flex items-center gap-2 text-sm font-semibold"><span class="feature-lock">管</span><span>+ 新規追加</span></div>
      <div class="mt-1 text-xs opacity-70">管理者のみ追加できます</div>
    </div>
    @endif
  </header>

  <!-- 商社リスト -->
  <div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-4">商社名</th>
          <th class="py-2 pr-4">URL</th>
          <th class="py-2 pr-4 text-right">リードタイム</th>
          <th class="py-2 pr-4 text-right">送料無料閾値</th>
          <th class="py-2 pr-4">備考</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in suppliers" :key="s.id"
          :class="s.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
          class="border-b border-[var(--color-border)] hover:opacity-90">
          <td class="py-2 pr-4">
            <div class="flex items-center gap-2">
              <span class="w-3 h-3 rounded-full inline-block flex-shrink-0"
                :style="{ backgroundColor: s.color || '#2563eb' }"></span>
              <span class="font-medium">@{{ s.name }}</span>
              <span v-if="s.deleted_at" class="inline-flex items-center rounded-full border border-amber-400/50 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                アーカイブ済み
              </span>
            </div>
          </td>
          <td class="py-2 pr-4">
            <a v-if="s.url" :href="s.url" target="_blank" rel="noopener"
              class="text-[var(--color-primary)] hover:underline text-xs truncate max-w-xs block">
              @{{ s.url }}
            </a>
            <span v-else class="opacity-40">-</span>
          </td>
          <td class="py-2 pr-4 text-right">@{{ s.lead_days != null ? s.lead_days + '日' : '-' }}</td>
          <td class="py-2 pr-4 text-right">
            @{{ s.free_shipping_threshold != null ? '¥' + Number(s.free_shipping_threshold).toLocaleString() : '-' }}
          </td>
          <td class="py-2 pr-4 text-xs opacity-70 max-w-xs">
            <div class="truncate">@{{ s.note || '-' }}</div>
            <div class="mt-1 opacity-60">使用件数: @{{ s.usage_count ?? 0 }}</div>
          </td>
          <td class="py-2">
            <div class="flex gap-2">
              <button @click="openEdit(s)"
                class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">
                編集
              </button>
              <button v-if="!s.deleted_at" @click="archiveSupplier(s)"
                class="px-2 py-1 text-xs border border-amber-400 text-amber-700 rounded hover:bg-amber-50">
                アーカイブ
              </button>
              <button v-else @click="restoreSupplier(s)"
                class="px-2 py-1 text-xs border border-emerald-400 text-emerald-700 rounded hover:bg-emerald-50">
                復元
              </button>
            </div>
          </td>
        </tr>
        <tr v-if="suppliers.length === 0">
          <td colspan="6" class="py-8 text-center opacity-40">商社が登録されていません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 追加/編集モーダル -->
  <div v-if="modal.open" class="modal-overlay">
    <div class="modal-window modal-lg">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ modal.isEdit ? '商社編集' : '商社追加' }}</h2>
        <button @click="modal.open = false" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">商社名 <span class="text-red-500">*</span></label>
          <input v-model="modal.form.name" type="text" placeholder="例: 秋月電子通商"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">URL</label>
          <input v-model="modal.form.url" type="url" placeholder="https://example.com"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium mb-1">リードタイム（日）</label>
            <input v-model.number="modal.form.lead_days" type="number" min="0" placeholder="3"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium mb-1">送料無料閾値（円）</label>
            <input v-model.number="modal.form.free_shipping_threshold" type="number" min="0" placeholder="3000"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">表示色</label>
          <div class="flex items-center gap-3">
            <input v-model="modal.form.color" type="color"
              class="w-10 h-10 rounded border border-[var(--color-border)] cursor-pointer" />
            <span class="text-sm font-mono opacity-70">@{{ modal.form.color }}</span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">備考</label>
          <textarea v-model="modal.form.note" rows="2" placeholder="メモ・注意事項"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm resize-none"></textarea>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="modal.open = false"
          class="px-4 py-2 border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">
          キャンセル
        </button>
        <button @click="save" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">
      @{{ t.msg }}
    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => 'マスタ管理', 'url' => route('master.index')],
    ['label' => '商社管理', 'current' => true],
  ], 'class' => 'mt-6'])

</div>
</body>
</html>
