<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ダッシュボード - BitsKeep</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app"
  data-page="dashboard"
  data-user-name="{{ auth()->user()->name }}"
  data-role="{{ auth()->user()->role }}"
  class="p-6 max-w-5xl mx-auto">

  <!-- ヘッダ -->
  <header class="flex justify-between items-center mb-8">
    <div>
      <h1 class="text-2xl font-bold">🏠 BitsKeep</h1>
      <p class="text-sm opacity-60 mt-1">おはようございます、{{ auth()->user()->name }} さん</p>
    </div>
    <button @click="openSearch"
      class="flex items-center gap-2 px-4 py-2 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg text-sm hover:opacity-90 transition-opacity">
      <span class="opacity-60">🔍 検索...</span>
      <kbd class="opacity-40 text-xs bg-[var(--color-bg)] border border-[var(--color-border)] rounded px-1.5 py-0.5">Ctrl+K</kbd>
    </button>
  </header>

  <!-- 今日の確認事項 -->
  <section class="mb-8">
    <h2 class="text-sm font-semibold opacity-60 uppercase tracking-wide mb-3">今日の確認事項</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <!-- 在庫警告カード -->
      <a href="{{ route('stock.alert') }}"
        :class="alertCount > 0 ? 'border-amber-400 bg-amber-50' : 'border-[var(--color-border)] bg-[var(--color-card-odd)]'"
        class="rounded-xl border p-4 flex items-center gap-4 hover:opacity-90 transition-opacity no-underline text-inherit">
        <span class="text-3xl">{{ alertCount > 0 ? '⚠️' : '✅' }}</span>
        <div>
          <div class="font-medium">在庫警告</div>
          <div class="text-sm" :class="alertCount > 0 ? 'text-amber-700' : 'opacity-60'">
            {{ alertCount > 0 ? alertCount + ' 件の部品が発注点を下回っています' : '全て問題ありません' }}
          </div>
        </div>
      </a>
    </div>
  </section>

  <!-- クイックアクション -->
  <section class="mb-8">
    <h2 class="text-sm font-semibold opacity-60 uppercase tracking-wide mb-3">クイックアクション</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
      <a v-for="action in quickActions" :key="action.url"
        :href="action.url"
        class="relative bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-xl p-4 flex flex-col items-center gap-2 hover:border-[var(--color-primary)] hover:shadow-md transition-all no-underline text-inherit">
        <span class="text-2xl">{{ action.icon }}</span>
        <span class="text-sm font-medium text-center">{{ action.label }}</span>
        <span v-if="action.badge"
          class="absolute top-2 right-2 bg-amber-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
          {{ action.badge > 9 ? '9+' : action.badge }}
        </span>
      </a>
    </div>
  </section>

  <!-- 最近の部品 -->
  <section>
    <h2 class="text-sm font-semibold opacity-60 uppercase tracking-wide mb-3">最近の部品</h2>
    <div class="space-y-1">
      <a v-for="p in recentParts" :key="p.id"
        :href="`/components/${p.id}`"
        class="flex items-center justify-between bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg px-4 py-2.5 hover:opacity-90 no-underline text-inherit transition-opacity">
        <div class="flex items-center gap-3">
          <span class="text-lg">🔩</span>
          <div>
            <div class="text-sm font-medium">{{ p.common_name || p.part_number }}</div>
            <div class="text-xs opacity-50">{{ p.part_number }}</div>
          </div>
        </div>
        <div class="text-xs opacity-50">在庫 {{ p.quantity_new }}</div>
      </a>
      <div v-if="recentParts.length === 0" class="text-center py-4 opacity-30 text-sm">部品が登録されていません</div>
    </div>
  </section>

  <!-- グローバル検索オーバーレイ -->
  <div v-if="searchOpen" class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-20 px-4"
    @click.self="closeSearch">
    <div class="bg-[var(--color-bg)] rounded-2xl shadow-2xl w-full max-w-lg">
      <div class="flex items-center gap-3 p-4 border-b border-[var(--color-border)]">
        <span class="opacity-50">🔍</span>
        <input v-model="searchQuery" @input="onSearchInput" type="text"
          placeholder="部品名・型番・案件名で検索..."
          class="flex-1 bg-transparent text-base outline-none"
          ref="searchInput" autofocus />
        <button @click="closeSearch" class="opacity-40 hover:opacity-70 text-sm">Esc</button>
      </div>

      <!-- 検索結果 -->
      <div class="max-h-72 overflow-y-auto">
        <div v-if="searching" class="p-4 text-center opacity-50 text-sm">検索中...</div>
        <div v-else-if="searchResults.length === 0 && searchQuery" class="p-4 text-center opacity-40 text-sm">
          「{{ searchQuery }}」は見つかりません
        </div>
        <div v-for="r in searchResults" :key="r.url + r.label"
          @click="navigate(r.url)"
          class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-[var(--color-card-odd)] transition-colors border-b border-[var(--color-border)] last:border-0">
          <span class="text-xl flex-shrink-0">{{ r.icon }}</span>
          <div>
            <div class="text-sm font-medium">{{ r.label }}</div>
            <div class="text-xs opacity-50">{{ r.sub }}</div>
          </div>
          <span class="ml-auto text-xs opacity-40 bg-[var(--color-card-odd)] px-2 py-0.5 rounded">
            {{ r.type === 'component' ? '部品' : '案件' }}
          </span>
        </div>
      </div>

      <div class="p-3 border-t border-[var(--color-border)] flex gap-4 text-xs opacity-40">
        <span>↵ 選択</span>
        <span>Esc 閉じる</span>
      </div>
    </div>
  </div>

</div>
</body>
</html>
