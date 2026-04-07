<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>部品比較 - BitsKeep</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="component-compare" class="p-6 max-w-6xl mx-auto">

  <nav class="breadcrumb mb-4">
    <a href="{{ route('dashboard') }}">🏠 BitsKeep</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <a href="{{ route('components.index') }}">部品一覧</a>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">部品比較</span>
  </nav>

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">部品比較</h1>
    <p class="text-sm opacity-60 mt-1">最大5部品の仕様・価格を横並び比較（差分をハイライト）</p>
  </header>

  <div v-if="loading" class="text-center py-16 opacity-50">読み込み中...</div>

  <div v-else-if="components.length === 0" class="text-center py-16 opacity-50">
    <p class="text-lg mb-2">比較対象が選択されていません</p>
    <p class="text-sm">部品一覧でチェックボックスを選択し「比較」ボタンを押してください</p>
    <a href="{{ route('components.index') }}" class="inline-block mt-4 btn-primary px-4 py-2 rounded">部品一覧へ</a>
  </div>

  <div v-else class="overflow-x-auto">
    <table class="w-full text-sm border-collapse min-w-[600px]">
      <!-- ヘッダ行: 部品名 -->
      <thead>
        <tr class="border-b-2 border-[var(--color-border)]">
          <th class="py-3 pr-4 text-left opacity-60 w-36">項目</th>
          <th v-for="comp in components" :key="comp.id"
            class="py-3 px-3 text-left border-l border-[var(--color-border)]">
            <a :href="`/components/${comp.id}`" class="font-bold text-[var(--color-primary)] hover:underline block">
              {{ comp.common_name || comp.part_number }}
            </a>
            <span class="text-xs font-normal opacity-60">{{ comp.part_number }}</span>
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
            {{ comp.manufacturer || '-' }}
          </td>
        </tr>
        <tr class="border-b border-[var(--color-border)] bg-[var(--color-card-even)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">入手可否</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)]">
            <span :class="statusClass(comp.procurement_status)" class="tag px-2 py-0.5 rounded text-xs font-medium">
              {{ statusLabel(comp.procurement_status) }}
            </span>
          </td>
        </tr>
        <tr class="border-b border-[var(--color-border)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">分類</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)]">
            {{ comp.categories?.join(', ') || '-' }}
          </td>
        </tr>
        <tr class="border-b border-[var(--color-border)] bg-[var(--color-card-even)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">パッケージ</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)]">
            {{ comp.packages?.join(', ') || '-' }}
          </td>
        </tr>

        <!-- 在庫 -->
        <tr class="bg-[var(--color-card-odd)]">
          <td class="py-2 pr-4 opacity-70 text-xs font-semibold uppercase tracking-wide" colspan="100">在庫</td>
        </tr>
        <tr class="border-b border-[var(--color-border)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">在庫（新品）</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)] font-mono">
            {{ comp.quantity_new }}
          </td>
        </tr>
        <tr class="border-b border-[var(--color-border)] bg-[var(--color-card-even)] hover:opacity-90">
          <td class="py-2 pr-4 opacity-60 text-xs">最安値</td>
          <td v-for="comp in components" :key="comp.id" class="py-2 px-3 border-l border-[var(--color-border)] font-mono">
            {{ comp.cheapest_price != null ? '¥' + comp.cheapest_price.toLocaleString() : '-' }}
          </td>
        </tr>

        <!-- スペック -->
        <tr v-if="specTypes.length > 0" class="bg-[var(--color-card-odd)]">
          <td class="py-2 pr-4 opacity-70 text-xs font-semibold uppercase tracking-wide" colspan="100">スペック</td>
        </tr>
        <tr v-for="(st, i) in specTypes" :key="st.id"
          :class="[i % 2 === 0 ? '' : 'bg-[var(--color-card-even)]',
                   hasDiff(st.id) ? 'ring-1 ring-inset ring-amber-400' : '']"
          class="border-b border-[var(--color-border)] hover:opacity-90">
          <td class="py-2 pr-4 text-xs" :class="hasDiff(st.id) ? 'text-amber-600 font-medium' : 'opacity-60'">
            {{ st.name }}
            <span v-if="hasDiff(st.id)" class="ml-1 text-amber-500 text-xs">⚠差分</span>
          </td>
          <td v-for="comp in components" :key="comp.id"
            :class="hasDiff(st.id) ? 'font-medium text-amber-700' : ''"
            class="py-2 px-3 border-l border-[var(--color-border)] font-mono">
            <template v-if="comp.specs[st.id]?.value">
              {{ comp.specs[st.id].value }}
              <span v-if="comp.specs[st.id].unit" class="opacity-60 text-xs">{{ comp.specs[st.id].unit }}</span>
            </template>
            <span v-else class="opacity-30">-</span>
          </td>
        </tr>
      </tbody>
    </table>
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
