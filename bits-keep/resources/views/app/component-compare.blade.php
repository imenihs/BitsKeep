<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>部品比較 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="component-compare" class="p-6 max-w-6xl mx-auto">

  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <a href="{{ route('components.index') }}">部品一覧</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">部品比較</span>
  </nav>

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold">部品比較</h1>
        <p class="text-sm opacity-60 mt-1">最大5部品の仕様・価格を横並び比較（差分をハイライト）</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm opacity-70">@{{ compareCountLabel }}</span>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="diffOnly" type="checkbox" class="h-4 w-4" />
          差異のある行のみ表示
        </label>
        <button @click="openAddModal" class="btn px-4 py-2 rounded border border-[var(--color-border)]">部品を追加</button>
      </div>
    </div>
  </header>

  <div v-if="loading" class="text-center py-16 opacity-50">読み込み中...</div>

  <div v-else-if="loadError" class="notice-card notice-card-error py-8 px-6">
    <div class="font-semibold text-[var(--color-tag-eol)]">比較データの取得に失敗しました</div>
    <p class="mt-2 text-sm opacity-80">@{{ loadError }}</p>
    <div class="mt-4 flex flex-wrap gap-3">
      <button @click="fetchCompare(compareIds)" class="btn-primary px-4 py-2 rounded">再試行</button>
      <a href="{{ route('components.index') }}" class="btn px-4 py-2 rounded border border-[var(--color-border)]">部品一覧へ戻る</a>
    </div>
  </div>

  <div v-else-if="emptyState === 'none'" class="text-center py-16 opacity-50">
    <p class="text-lg mb-2">比較対象が選択されていません</p>
    <p class="text-sm">部品一覧でチェックボックスを選択し「比較」ボタンを押してください</p>
    <a href="{{ route('components.index') }}" class="inline-block mt-4 btn-primary px-4 py-2 rounded">部品一覧へ</a>
  </div>

  <div v-else-if="emptyState === 'insufficient'" class="notice-card py-8 px-6">
    <div class="font-semibold">比較には2件以上の部品が必要です</div>
    <p class="mt-2 text-sm opacity-80">現在の比較対象では件数が不足しています。部品一覧からもう1件以上追加してください。</p>
    <div class="mt-4 flex flex-wrap gap-3">
      <a href="{{ route('components.index') }}" class="btn-primary px-4 py-2 rounded">部品一覧で選び直す</a>
    </div>
  </div>

  <div v-else-if="canShowTable" class="overflow-x-auto">
    <table class="w-full text-sm border-collapse min-w-[600px]">
      <!-- ヘッダ行: 部品名 -->
      <thead>
        <tr class="border-b-2 border-[var(--color-border)]">
          <th class="py-3 pr-4 text-left opacity-60 w-36">項目</th>
          <th v-for="comp in components" :key="comp.id"
            class="py-3 px-3 text-left border-l border-[var(--color-border)]">
            <div class="flex items-start justify-between gap-3">
              <div class="flex gap-3 min-w-0">
                <img v-if="comp.image_url" :src="comp.image_url" class="thumbnail flex-shrink-0" />
                <div v-else class="w-16 h-16 flex-shrink-0 rounded border border-[var(--color-border)] flex items-center justify-center opacity-30 text-xs">無</div>
                <div class="min-w-0">
                  <a :href="`/components/${comp.id}`" class="font-bold text-[var(--color-primary)] hover:underline block truncate">
                    @{{ comp.common_name || comp.part_number }}
                  </a>
                  <span class="text-xs font-normal opacity-60 block font-mono">@{{ comp.part_number }}</span>
                  <div class="mt-2 flex items-center gap-2">
                    <button @click="moveComponent(components.indexOf(comp), -1)" :disabled="components.indexOf(comp) === 0"
                      class="px-2 py-1 rounded border border-[var(--color-border)] text-xs disabled:opacity-30">←</button>
                    <button @click="moveComponent(components.indexOf(comp), 1)" :disabled="components.indexOf(comp) === components.length - 1"
                      class="px-2 py-1 rounded border border-[var(--color-border)] text-xs disabled:opacity-30">→</button>
                  </div>
                </div>
              </div>
              <button @click="removeComponent(comp.id)" class="text-xs opacity-60 hover:opacity-100">外す</button>
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <!-- 基本情報 -->
        <tr class="bg-[var(--color-card-odd)]">
          <td class="py-2 pr-4 opacity-70 text-xs font-semibold uppercase tracking-wide" colspan="100">基本情報</td>
        </tr>
        <tr class="border-b border-[var(--color-border)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">メーカー</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)]">
            @{{ comp.manufacturer || '-' }}
          </td>
        </tr>
        <tr class="border-b border-[var(--color-border)] bg-[var(--color-card-even)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">入手可否</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)]">
            <span :class="statusClass(comp.procurement_status)" class="tag px-2 py-0.5 rounded text-xs font-medium">
              @{{ statusLabel(comp.procurement_status) }}
            </span>
          </td>
        </tr>
        <tr class="border-b border-[var(--color-border)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">分類</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)]">
            @{{ comp.categories?.join(', ') || '-' }}
          </td>
        </tr>
        <tr class="border-b border-[var(--color-border)] bg-[var(--color-card-even)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">パッケージ</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)]">
            @{{ comp.packages?.join(', ') || '-' }}
          </td>
        </tr>

        <!-- 在庫 -->
        <tr class="bg-[var(--color-card-odd)]">
          <td class="py-2 pr-4 opacity-70 text-xs font-semibold uppercase tracking-wide" colspan="100">在庫</td>
        </tr>
        <tr class="border-b border-[var(--color-border)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">在庫（新品）</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)] font-mono">
            @{{ comp.quantity_new }}
          </td>
        </tr>
        <tr class="border-b border-[var(--color-border)] bg-[var(--color-card-even)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">最安値</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)] font-mono">
            @{{ comp.cheapest_price != null ? '¥' + comp.cheapest_price.toLocaleString() : '-' }}
          </td>
        </tr>

        <!-- スペック -->
        <tr v-if="visibleSpecTypes.length > 0" class="bg-[var(--color-card-odd)]">
          <td class="py-2 pr-4 opacity-70 text-xs font-semibold uppercase tracking-wide" colspan="100">スペック</td>
        </tr>
        <tr v-for="(st, i) in visibleSpecTypes" :key="st.id"
          :class="[i % 2 === 0 ? '' : 'bg-[var(--color-card-even)]',
                   hasDiff(st.id) ? 'ring-1 ring-inset ring-amber-400' : '']"
          class="border-b border-[var(--color-border)] hover:opacity-90">
          <td class="py-2 pr-4 text-xs" :class="hasDiff(st.id) ? 'text-amber-600 font-medium' : 'opacity-60'">
            @{{ st.name }}
            <span v-if="hasDiff(st.id)" class="ml-1 text-amber-500 text-xs">⚠差分</span>
          </td>
          <td v-for="comp in components" :key="comp.id"
            :class="hasDiff(st.id) ? 'font-medium text-amber-700' : ''"
            class="py-2 px-3 border-l border-[var(--color-border)] font-mono">
            <template v-if="comp.specs[st.id]?.value">
              @{{ comp.specs[st.id].value }}
              <span v-if="comp.specs[st.id].unit" class="opacity-60 text-xs">@{{ comp.specs[st.id].unit }}</span>
            </template>
            <span v-else class="opacity-30">-</span>
          </td>
        </tr>
        <tr class="bg-[var(--color-card-odd)]">
          <td class="py-2 pr-4 opacity-70 text-xs font-semibold uppercase tracking-wide" colspan="100">操作</td>
        </tr>
        <tr class="border-b border-[var(--color-border)]">
          <td class="py-2 pr-4 opacity-60 text-xs">案件追加</td>
          <td v-for="comp in components" :key="`action-${comp.id}`" class="py-2 px-3 border-l border-[var(--color-border)]">
            <div class="flex flex-wrap gap-2">
              <a :href="`/components/${comp.id}`" class="btn px-3 py-2 rounded border border-[var(--color-border)] text-xs">詳細へ</a>
              <button @click="openProjectDrawer(comp)" class="btn-primary px-3 py-2 rounded text-xs">案件に追加</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div v-else class="text-center py-16 opacity-50">
    <p class="text-lg mb-2">比較できる部品が不足しています</p>
    <p class="text-sm">比較を続けるには部品一覧から2件以上を選択してください</p>
    <a href="{{ route('components.index') }}" class="inline-block mt-4 btn-primary px-4 py-2 rounded">部品一覧へ</a>
  </div>

  <div v-if="drawer.open" class="modal-overlay" @click.self="drawer.open = false">
    <div class="modal-window modal-md p-6">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold">案件に追加</h2>
          <p class="mt-1 text-sm opacity-70">「@{{ drawer.part?.common_name || drawer.part?.part_number }}」を追加する案件を選択してください。</p>
        </div>
        <button @click="drawer.open = false" class="opacity-60 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="mt-5 space-y-3">
        <ProjectComboBox
          v-model="drawer.selectedProject"
          :allow-new="true"
          @new-project-created="handleNewProjectCreated"
        />
        <p class="text-xs opacity-60">事業名・案件名・案件番号で絞り込みできます。一致しない場合は、そのまま独自案件を作成して選択できます。</p>
      </div>
      <div class="mt-6 flex justify-end gap-2">
        <button @click="drawer.open = false" class="btn px-4 py-2 rounded border border-[var(--color-border)]">キャンセル</button>
        <button @click="addToProject" :disabled="!drawer.selectedProject || drawer.adding" class="btn-primary px-4 py-2 rounded disabled:opacity-40">
          @{{ drawer.adding ? '追加中...' : '案件に追加' }}
        </button>
      </div>
    </div>
  </div>

  <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
    <div class="modal-window modal-md p-6">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold">比較する部品を追加</h2>
          <p class="mt-1 text-sm opacity-70">最大5件まで比較できます。型番または通称で絞り込んで追加してください。</p>
        </div>
        <button @click="showAddModal = false" class="opacity-60 hover:opacity-100 text-xl">✕</button>
      </div>
      <div class="mt-5">
        <input v-model="addSearch" @input="searchParts" type="text" placeholder="部品名・型番で検索"
          class="input-text w-full text-sm" />
      </div>
      <div class="mt-4 space-y-2 max-h-80 overflow-y-auto">
        <div v-if="addLoading" class="py-8 text-center text-sm opacity-50">検索中...</div>
        <button v-for="part in addResults" :key="part.id" type="button"
          @click="addPart(part)"
          class="w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] px-4 py-3 text-left hover:border-[var(--color-primary)] transition-colors">
          <div class="flex items-center gap-3">
            <img v-if="part.image_url" :src="part.image_url" class="thumbnail flex-shrink-0" />
            <div v-else class="w-12 h-12 rounded border border-[var(--color-border)] flex items-center justify-center opacity-30 text-xs">無</div>
            <div class="min-w-0">
              <div class="font-semibold truncate">@{{ part.common_name || part.part_number }}</div>
              <div class="mt-1 text-xs opacity-60 font-mono truncate">@{{ part.part_number }} / @{{ part.manufacturer || 'メーカー不明' }}</div>
            </div>
          </div>
        </button>
        <div v-if="!addLoading && addResults.length === 0" class="py-8 text-center text-sm opacity-40">追加できる候補がありません</div>
      </div>
    </div>
  </div>

  <!-- トースト -->
  <div class="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
    <div v-for="t in toasts" :key="t.id"
      :class="t.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      class="text-white px-4 py-2 rounded shadow-lg text-sm">@{{ t.message }}</div>
  </div>

</div>
</body>
</html>
