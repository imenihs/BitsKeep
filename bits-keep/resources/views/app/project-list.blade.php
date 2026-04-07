<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>案件管理 - BitsKeep</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="project-list" class="p-6 max-w-7xl mx-auto">

  <nav class="breadcrumb mb-4">
    <a href="{{ route('dashboard') }}">🏠 BitsKeep</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">案件管理</span>
  </nav>

  <header class="flex justify-between items-center mb-6 pb-4 border-b border-[var(--color-border)]">
    <div>
      <h1 class="text-2xl font-bold">案件管理</h1>
      <p class="text-sm opacity-60 mt-1">使用部品・コスト積算を管理</p>
    </div>
    <button @click="openAdd" class="btn-primary px-4 py-2 rounded text-sm font-medium">+ 案件を作成</button>
  </header>

  <div class="flex gap-6" style="min-height: 60vh">
    <!-- 左: 案件リスト -->
    <div class="flex-1 min-w-0">
      <!-- フィルタ -->
      <div class="flex gap-3 mb-4">
        <input v-model="filters.q" @keyup.enter="applyFilter" type="text" placeholder="案件名で検索"
          class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm" />
        <select v-model="filters.status" @change="applyFilter"
          class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm">
          <option value="">すべて</option>
          <option value="active">進行中</option>
          <option value="archived">アーカイブ</option>
        </select>
      </div>

      <!-- リスト -->
      <div class="space-y-2">
        <div v-for="p in projects" :key="p.id"
          @click="openDetail(p)"
          :class="detailProject?.id === p.id ? 'ring-2 ring-[var(--color-primary)]' : ''"
          class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 cursor-pointer hover:opacity-90 transition-all">
          <div class="flex justify-between items-start">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-3 h-3 rounded-full flex-shrink-0"
                :style="{ backgroundColor: p.color || '#2563eb' }"></div>
              <div class="min-w-0">
                <div class="font-medium truncate">{{ p.name }}</div>
                <div class="text-xs opacity-60 mt-0.5 truncate">{{ p.description || '説明なし' }}</div>
              </div>
            </div>
            <div class="flex items-center gap-2 ml-3 flex-shrink-0">
              <span :class="statusClass(p.status)" class="tag text-xs px-2 py-0.5 rounded font-medium">
                {{ statusLabel(p.status) }}
              </span>
              <span class="text-xs opacity-50">{{ p.components_count }} 部品</span>
            </div>
          </div>
        </div>
        <div v-if="!loading && projects.length === 0" class="text-center py-8 opacity-40">案件がありません</div>
        <div v-if="loading" class="text-center py-8 opacity-50">読み込み中...</div>
      </div>

      <!-- ページネーション -->
      <div v-if="meta && meta.last_page > 1" class="flex justify-center gap-1 mt-4">
        <button v-for="pg in meta.last_page" :key="pg" @click="filters.page = pg; fetchProjects()"
          :class="pg === meta.current_page ? 'btn-primary' : 'border border-[var(--color-border)]'"
          class="w-8 h-8 rounded text-sm">{{ pg }}</button>
      </div>
    </div>

    <!-- 右: 案件詳細パネル -->
    <div class="w-96 flex-shrink-0" v-if="detailProject">
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-xl p-5">
        <div class="flex justify-between items-start mb-4">
          <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: detailProject.color || '#2563eb' }"></div>
            <h2 class="font-bold text-lg">{{ detailProject.name }}</h2>
          </div>
          <div class="flex gap-2">
            <button @click="openEdit(detailProject)" class="text-xs opacity-60 hover:opacity-100 px-2 py-1 border border-[var(--color-border)] rounded">編集</button>
            <button @click="deleteProject(detailProject)" class="text-xs text-red-500 px-2 py-1 border border-red-400 rounded hover:bg-red-50">削除</button>
          </div>
        </div>
        <p class="text-sm opacity-70 mb-4">{{ detailProject.description || '説明なし' }}</p>

        <!-- コスト積算 -->
        <div v-if="costSummary" class="mb-4 bg-[var(--color-bg)] border border-[var(--color-border)] rounded p-3">
          <div class="flex justify-between items-center">
            <span class="text-sm font-medium">部品コスト合計</span>
            <span class="font-mono font-bold text-lg">
              {{ costSummary.total != null ? '¥' + costSummary.total.toLocaleString() : '-' }}
            </span>
          </div>
          <p v-if="costSummary.warning" class="text-xs text-amber-600 mt-1">⚠ {{ costSummary.warning }}</p>
        </div>

        <!-- 使用部品一覧 -->
        <h3 class="text-sm font-medium mb-2">使用部品</h3>
        <div class="space-y-1 max-h-60 overflow-y-auto mb-3">
          <div v-for="comp in detailProject.components" :key="comp.id"
            class="flex items-center justify-between bg-[var(--color-bg)] border border-[var(--color-border)] rounded p-2 text-xs">
            <div class="min-w-0 flex-1 mr-2">
              <div class="font-medium truncate">{{ comp.common_name || comp.part_number }}</div>
              <div class="opacity-50">x{{ comp.pivot.required_qty }}</div>
            </div>
            <button @click="removeComponent(comp)" class="text-red-400 hover:text-red-600 flex-shrink-0">✕</button>
          </div>
          <div v-if="detailProject.components?.length === 0" class="text-center py-2 opacity-40 text-xs">部品が未登録です</div>
        </div>

        <!-- 部品追加フォーム -->
        <div class="border-t border-[var(--color-border)] pt-3">
          <p class="text-xs font-medium mb-2">部品を追加</p>
          <div class="relative mb-2">
            <input v-model="addCompForm.keyword" @input="searchComponents" type="text" placeholder="部品名・型番で検索"
              class="w-full bg-[var(--color-bg)] border border-[var(--color-border)] rounded px-2 py-1.5 text-xs" />
            <!-- 検索結果ドロップダウン -->
            <div v-if="addCompForm.searchResults.length > 0"
              class="absolute top-full left-0 right-0 z-10 bg-[var(--color-bg)] border border-[var(--color-border)] rounded shadow-lg mt-0.5 max-h-40 overflow-y-auto">
              <div v-for="c in addCompForm.searchResults" :key="c.id"
                @click="selectComp(c)"
                class="px-3 py-2 text-xs hover:bg-[var(--color-card-odd)] cursor-pointer">
                <div class="font-medium">{{ c.common_name || c.part_number }}</div>
                <div class="opacity-60">{{ c.part_number }}</div>
              </div>
            </div>
          </div>
          <div class="flex gap-2">
            <input v-model.number="addCompForm.required_qty" type="number" min="1" placeholder="数量"
              class="w-20 bg-[var(--color-bg)] border border-[var(--color-border)] rounded px-2 py-1.5 text-xs" />
            <button @click="addComponent" :disabled="!addCompForm.component_id"
              class="flex-1 btn-primary rounded text-xs py-1.5 disabled:opacity-40">追加</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 右パネル: 未選択時 -->
    <div class="w-96 flex-shrink-0 flex items-center justify-center opacity-30" v-else>
      <p class="text-sm">案件を選択してください</p>
    </div>
  </div>

  <!-- 追加/編集モーダル -->
  <div v-if="modal.open" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-[var(--color-bg)] rounded-xl shadow-xl w-full max-w-md mx-4">
      <div class="flex justify-between items-center p-6 border-b border-[var(--color-border)]">
        <h2 class="text-lg font-bold">{{ modal.isEdit ? '案件編集' : '案件作成' }}</h2>
        <button @click="modal.open = false" class="opacity-50 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">案件名 <span class="text-red-500">*</span></label>
          <input v-model="modal.form.name" type="text"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">説明</label>
          <textarea v-model="modal.form.description" rows="3"
            class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm resize-none"></textarea>
        </div>
        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium mb-1">状態</label>
            <select v-model="modal.form.status"
              class="w-full bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-2 text-sm">
              <option value="active">進行中</option>
              <option value="archived">アーカイブ</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">カラー</label>
            <input v-model="modal.form.color" type="color"
              class="w-10 h-10 rounded border border-[var(--color-border)] cursor-pointer" />
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 p-6 border-t border-[var(--color-border)]">
        <button @click="modal.open = false" class="px-4 py-2 border border-[var(--color-border)] rounded">キャンセル</button>
        <button @click="save" class="btn-primary px-4 py-2 rounded font-medium">保存</button>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">{{ t.message }}</div>
  </div>

</div>
</body>
</html>
