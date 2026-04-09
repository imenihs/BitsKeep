<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>マスタ管理 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="master-list" data-tab="categories" class="p-6 max-w-4xl mx-auto">

  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">マスタ管理</span>
  </nav>

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">マスタ管理</h1>
    <p class="text-sm opacity-60 mt-1">分類・パッケージ・スペック種別の管理</p>
  </header>

  <!-- タブ切り替え -->
  <div class="flex gap-1 mb-6 border-b border-[var(--color-border)]">
    <button v-for="tab in [{id:'categories',label:'分類'},{id:'packages',label:'パッケージ'},{id:'spec-types',label:'スペック種別'}]"
      :key="tab.id" @click="switchTab(tab.id)"
      :class="activeTab === tab.id
        ? 'border-b-2 border-[var(--color-primary)] text-[var(--color-primary)] font-medium'
        : 'opacity-60 hover:opacity-90'"
      class="px-4 py-2 text-sm transition-colors -mb-px">
      @{{ tab.label }}
    </button>
  </div>

  <!-- ═══════════════════════════════ 分類タブ ══════════════════════════════ -->
  <div v-if="activeTab === 'categories'">
    <div class="flex justify-end mb-4">
      <button @click="openCatAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium">+ 分類を追加</button>
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-4 w-8">順</th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">説明</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in categories" :key="c.id"
          :class="c.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
          class="border-b border-[var(--color-border)]">
          <td class="py-2 pr-4 text-center opacity-50">@{{ c.sort_order }}</td>
          <td class="py-2 pr-4 font-medium">@{{ c.name }}</td>
          <td class="py-2 pr-4 opacity-70 text-xs">@{{ c.description || '-' }}</td>
          <td class="py-2">
            <div class="flex gap-2">
              <button @click="openCatEdit(c)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button @click="deleteCategory(c)" class="px-2 py-1 text-xs border border-red-400 text-red-500 rounded hover:bg-red-50">削除</button>
            </div>
          </td>
        </tr>
        <tr v-if="categories.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">分類が登録されていません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ══════════════════════════ パッケージタブ ══════════════════════════════ -->
  <div v-if="activeTab === 'packages'">
    <div class="flex justify-end mb-4">
      <button @click="openPkgAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium">+ パッケージを追加</button>
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-4 w-8">順</th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">説明</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in packages" :key="p.id"
          :class="p.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
          class="border-b border-[var(--color-border)]">
          <td class="py-2 pr-4 text-center opacity-50">@{{ p.sort_order }}</td>
          <td class="py-2 pr-4 font-medium">@{{ p.name }}</td>
          <td class="py-2 pr-4 opacity-70 text-xs">@{{ p.description || '-' }}</td>
          <td class="py-2">
            <div class="flex gap-2">
              <button @click="openPkgEdit(p)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button @click="deletePackage(p)" class="px-2 py-1 text-xs border border-red-400 text-red-500 rounded hover:bg-red-50">削除</button>
            </div>
          </td>
        </tr>
        <tr v-if="packages.length === 0">
          <td colspan="4" class="py-8 text-center opacity-40">パッケージが登録されていません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ══════════════════════════ スペック種別タブ ═══════════════════════════ -->
  <div v-if="activeTab === 'spec-types'">
    <div class="flex justify-end mb-4">
      <button @click="openStAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium">+ スペック種別を追加</button>
    </div>
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-4 w-8">順</th>
          <th class="py-2 pr-4">名前</th>
          <th class="py-2 pr-4">型</th>
          <th class="py-2 pr-4">単位候補</th>
          <th class="py-2">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in specTypes" :key="s.id"
          :class="s.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
          class="border-b border-[var(--color-border)]">
          <td class="py-2 pr-4 text-center opacity-50">@{{ s.sort_order }}</td>
          <td class="py-2 pr-4 font-medium">@{{ s.name }}</td>
          <td class="py-2 pr-4 text-xs opacity-70">@{{ s.value_type }}</td>
          <td class="py-2 pr-4 text-xs">
            <span v-for="u in (s.units ?? [])" :key="u.id"
              class="inline-block bg-[var(--color-card-even)] border border-[var(--color-border)] rounded px-1.5 py-0.5 mr-1 mb-1">
              @{{ u.unit }}
            </span>
            <span v-if="!s.units?.length" class="opacity-40">-</span>
          </td>
          <td class="py-2">
            <div class="flex gap-2">
              <button @click="openStEdit(s)" class="px-2 py-1 text-xs border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">編集</button>
              <button @click="deleteSpecType(s)" class="px-2 py-1 text-xs border border-red-400 text-red-500 rounded hover:bg-red-50">削除</button>
            </div>
          </td>
        </tr>
        <tr v-if="specTypes.length === 0">
          <td colspan="5" class="py-8 text-center opacity-40">スペック種別が登録されていません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ═══════════════ 分類モーダル ════════════════ -->
  <div v-if="catModal.open" class="modal-overlay">
    <div class="modal-window modal-md">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ catModal.isEdit ? '分類編集' : '分類追加' }}</h2>
        <button @click="catModal.open = false" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="catModal.form.name" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="catModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">並び順</label>
          <input v-model.number="catModal.form.sort_order" type="number"
            class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="catModal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveCategory" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ パッケージモーダル ════════════════ -->
  <div v-if="pkgModal.open" class="modal-overlay">
    <div class="modal-window modal-md">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ pkgModal.isEdit ? 'パッケージ編集' : 'パッケージ追加' }}</h2>
        <button @click="pkgModal.open = false" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="pkgModal.form.name" type="text" placeholder="例: 0402, SOT-23"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="pkgModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">並び順</label>
          <input v-model.number="pkgModal.form.sort_order" type="number"
            class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="pkgModal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="savePackage" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ スペック種別モーダル ════════════════ -->
  <div v-if="stModal.open" class="modal-overlay modal-top">
    <div class="modal-window modal-lg max-h-[80vh] overflow-y-auto">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">@{{ stModal.isEdit ? 'スペック種別編集' : 'スペック種別追加' }}</h2>
        <button @click="stModal.open = false" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">名前 <span class="text-red-500">*</span></label>
          <input v-model="stModal.form.name" type="text" placeholder="例: 静電容量, 耐圧"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <input v-model="stModal.form.description" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium mb-1">値の型</label>
            <select v-model="stModal.form.value_type"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
              <option value="numeric">数値</option>
              <option value="text">テキスト</option>
              <option value="boolean">真偽値</option>
            </select>
          </div>
          <div class="w-24">
            <label class="block text-sm font-medium mb-1">並び順</label>
            <input v-model.number="stModal.form.sort_order" type="number"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
          </div>
        </div>

        <!-- 単位候補（数値型のみ） -->
        <div v-if="stModal.form.value_type === 'numeric'">
          <div class="flex justify-between items-center mb-2">
            <label class="text-sm font-medium">単位候補</label>
            <button @click="addUnit" type="button"
              class="text-xs px-2 py-1 border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">
              + 追加
            </button>
          </div>
          <div v-for="(u, i) in stModal.form.units" :key="i"
            class="flex gap-2 items-center mb-2">
            <input v-model="u.unit" type="text" placeholder="例: μF"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input v-model.number="u.factor" type="number" step="any" placeholder="係数"
              class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <button @click="removeUnit(i)" class="text-red-400 hover:text-red-600 px-1">✕</button>
          </div>
          <p class="text-xs opacity-50 mt-1">係数: 基準単位に対する倍率（例: μF=1, nF=0.001, pF=0.000001）</p>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="stModal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="saveSpecType" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">
      @{{ t.message }}
    </div>
  </div>

</div>
</body>
</html>
