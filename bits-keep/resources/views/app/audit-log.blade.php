<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>操作ログ - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="audit-log" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">

  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span>管理</span>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">操作ログ</span>
  </nav>

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">操作ログ</h1>
    <p class="text-sm opacity-60 mt-1">部品・マスタの作成/更新/削除の操作履歴</p>
  </header>

  <!-- フィルタ -->
  <div class="flex flex-wrap gap-3 mb-4">
    <select v-model="filters.action" @change="applyFilter"
      class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm">
      <option value="">すべての操作</option>
      <option value="created">作成</option>
      <option value="updated">更新</option>
      <option value="deleted">削除</option>
    </select>
    <input v-model="filters.resource_type" @change="applyFilter" type="text" placeholder="リソース種別（例: Component）"
      class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm w-48" />
    <input v-model="filters.date_from" @change="applyFilter" type="date"
      class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm" />
    <span class="self-center opacity-50">〜</span>
    <input v-model="filters.date_to" @change="applyFilter" type="date"
      class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm" />
    <button @click="applyFilter" class="btn-primary px-3 py-1.5 rounded text-sm">検索</button>
  </div>

  <!-- テーブル -->
  <div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b border-[var(--color-border)] text-left opacity-70">
          <th class="py-2 pr-3 whitespace-nowrap">日時</th>
          <th class="py-2 pr-3">操作者</th>
          <th class="py-2 pr-3">操作</th>
          <th class="py-2 pr-3">対象</th>
          <th class="py-2">差分</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="log in logs" :key="log.id">
          <tr :class="log.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'"
            class="border-b border-[var(--color-border)] cursor-pointer hover:opacity-90"
            @click="toggleDiff(log.id)">
            <td class="py-2 pr-3 whitespace-nowrap text-xs opacity-70">
              @{{ new Date(log.created_at).toLocaleString('ja-JP') }}
            </td>
            <td class="py-2 pr-3 text-xs">
              <div>@{{ log.user?.name ?? '-' }}</div>
              <div class="opacity-50">@{{ log.ip_address }}</div>
            </td>
            <td class="py-2 pr-3">
              <span :class="actionClass(log.action)" class="px-2 py-0.5 rounded text-xs font-medium">
                @{{ actionLabel(log.action) }}
              </span>
            </td>
            <td class="py-2 pr-3 text-xs">
              <div class="font-medium">@{{ log.resource_type }}</div>
              <div class="opacity-60">#@{{ log.resource_id }}</div>
            </td>
            <td class="py-2 text-xs">
              <span v-if="log.diff" class="opacity-60 hover:opacity-90 underline">
                @{{ expandedId === log.id ? '▲ 閉じる' : '▼ 差分を見る' }}
              </span>
              <span v-else class="opacity-30">-</span>
            </td>
          </tr>
          <!-- 差分展開行 -->
          <tr v-if="expandedId === log.id && log.diff"
            :class="log.id % 2 === 0 ? 'bg-[var(--color-card-even)]' : 'bg-[var(--color-card-odd)]'">
            <td colspan="5" class="pb-3 px-4">
              <div class="bg-black/5 rounded p-3 font-mono text-xs space-y-1">
                <div v-if="diffLines(log.diff).length === 0" class="opacity-50">差分なし</div>
                <div v-for="dl in diffLines(log.diff)" :key="dl.key" class="flex gap-4">
                  <span class="w-32 shrink-0 opacity-70 truncate">@{{ dl.key }}</span>
                  <span class="text-red-500 line-through truncate max-w-xs">@{{ JSON.stringify(dl.before) }}</span>
                  <span class="opacity-50">→</span>
                  <span class="text-emerald-600 truncate max-w-xs">@{{ JSON.stringify(dl.after) }}</span>
                </div>
              </div>
            </td>
          </tr>
        </template>
        <tr v-if="!loading && logs.length === 0">
          <td colspan="5" class="py-8 text-center opacity-40">ログがありません</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ローディング -->
  <div v-if="loading" class="text-center py-8 opacity-50">読み込み中...</div>

  <!-- ページネーション -->
  <div v-if="meta && meta.last_page > 1" class="flex justify-center gap-1 mt-4">
    <button v-for="p in meta.last_page" :key="p" @click="goPage(p)"
      :class="p === meta.current_page ? 'btn-primary' : 'border border-[var(--color-border)] hover:bg-[var(--color-card-odd)]'"
      class="w-8 h-8 rounded text-sm">@{{ p }}</button>
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
