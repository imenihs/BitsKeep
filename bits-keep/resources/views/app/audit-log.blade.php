<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>操作ログ - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '操作ログ'])
<div id="app" data-page="audit-log" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '操作ログ', 'current' => true]]])

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
              @{{ formatDate(log.created_at, { time: true }) }}
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
          <td colspan="5" class="py-8 px-4">
            <div v-if="hasActiveFilter()" class="flex flex-col items-center gap-3">
              <div class="text-center opacity-50">
                <p class="font-medium">条件に合致するログがありません</p>
                <p class="text-sm mt-1">フィルタ条件が厳しすぎる可能性があります</p>
              </div>
              <button @click="clearFilters" class="btn-primary px-4 py-1.5 rounded text-sm">フィルタをクリア</button>
            </div>
            <div v-else class="text-center opacity-40">
              <p class="font-medium">操作ログはまだ記録されていません</p>
              <p class="text-sm mt-1">部品やマスタデータが作成・更新・削除されると記録されます</p>
            </div>
          </td>
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
      class="text-white px-4 py-2 rounded shadow-lg text-sm">@{{ t.msg }}</div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '操作ログ', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
