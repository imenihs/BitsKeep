<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>部品一覧 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">

<div id="app" data-page="components-list" class="flex w-full min-h-screen">

  <!-- ===== 左サイドバー: フィルター ===== -->
  <aside class="w-64 flex-shrink-0 border-r border-[var(--color-border)] p-4 overflow-y-auto bg-[var(--color-bg-alt)]">
    <div class="flex justify-between items-center mb-4">
      <span class="font-bold text-sm">絞り込み</span>
      <button @click="clearFilters" v-if="hasFilter"
        class="text-xs link-text flex items-center gap-1">✕ クリア</button>
    </div>

    <!-- 分類 -->
    <div class="mb-5">
      <p class="text-xs font-semibold opacity-60 mb-2 uppercase tracking-wide">分類</p>
      <div class="flex flex-col gap-1">
        <label v-for="cat in categories" :key="cat.id"
          class="flex items-center justify-between cursor-pointer hover:opacity-80 py-0.5">
          <div class="flex items-center gap-2">
            <input type="checkbox" :value="cat.id" v-model="filterCategories" class="rounded" />
            <span class="text-sm">@{{ cat.name }}</span>
          </div>
        </label>
      </div>
    </div>

    <!-- 入手可否 -->
    <div class="mb-5">
      <p class="text-xs font-semibold opacity-60 mb-2 uppercase tracking-wide">入手可否</p>
      <div class="flex flex-col gap-1 text-sm">
        <label v-for="opt in [{v:'',l:'すべて'},{v:'active',l:'量産中'},{v:'eol',l:'EOL'},{v:'last_time',l:'在庫限り'},{v:'nrnd',l:'新規非推奨'}]"
          :key="opt.v" class="flex items-center gap-2 cursor-pointer">
          <input type="radio" :value="opt.v" v-model="filterStatus" />@{{ opt.l }}
        </label>
      </div>
    </div>

    <!-- 在庫警告 -->
    <div class="mb-5">
      <label class="flex items-center gap-2 cursor-pointer text-sm font-semibold">
        <input type="checkbox" v-model="needsReorder" />
        <span class="text-[var(--color-tag-warning)]">⚠ 在庫警告のみ</span>
      </label>
    </div>

    <!-- 高度検索 -->
    <div class="border-t border-[var(--color-border)] pt-4">
      <button @click="advancedOpen = !advancedOpen"
        class="flex items-center justify-between w-full text-sm font-semibold opacity-80 mb-2">
        高度検索 <span>@{{ advancedOpen ? '▲' : '▼' }}</span>
      </button>
      <div v-if="advancedOpen" class="space-y-2">
        <div>
          <label class="text-xs opacity-60">スペック種別</label>
          <select v-model="advSpecTypeId" class="input-text text-sm py-1 w-full mt-1">
            <option value="">-- 選択 --</option>
            <option v-for="st in specTypes" :key="st.id" :value="st.id">@{{ st.name }}</option>
          </select>
        </div>
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="text-xs opacity-60">最小値</label>
            <input v-model="advMin" type="number" class="input-text text-sm py-1 w-full mt-1" placeholder="0" />
          </div>
          <div class="flex-1">
            <label class="text-xs opacity-60">最大値</label>
            <input v-model="advMax" type="number" class="input-text text-sm py-1 w-full mt-1" placeholder="∞" />
          </div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ===== 右メインエリア ===== -->
  <main class="flex-1 flex flex-col overflow-hidden">

    <!-- パンくず -->
    <nav class="breadcrumb px-4 pt-3">
      @include('partials.brand-home-link')
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      <span>部品管理</span>
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      <span class="current">部品一覧</span>
    </nav>

    <!-- トップバー -->
    <div class="p-4 border-b border-[var(--color-border)] flex items-center gap-3 flex-shrink-0">
      <div class="relative flex-1 max-w-sm">
        <input v-model="searchQuery" type="text" placeholder="部品名・型番・メーカーで検索..."
          class="input-text pl-4 text-sm w-full" />
      </div>
      <select v-model="sortOrder" class="input-text text-sm py-1 w-36">
        <option value="updated_at">更新順</option>
        <option value="name">名前順</option>
        <option value="part_number">型番順</option>
      </select>
      <!-- 在庫警告バッジ -->
      <a v-if="alertCount > 0" href="/stock-alert"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold text-white"
        style="background-color: var(--color-tag-warning);">
        ⚠ 在庫警告 @{{ alertCount }}件
      </a>
      <!-- 比較バー -->
      <div v-if="compareList.length" class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-[var(--color-primary)] text-xs">
        <span class="font-semibold text-[var(--color-primary)]">比較: @{{ compareList.length }}件</span>
        <a :href="compareUrl" class="btn btn-primary text-xs px-2 py-1 rounded">比較する</a>
      </div>
      <a href="{{ route('components.create') }}" class="btn btn-primary text-sm px-4 py-2 rounded ml-auto">
        + 新規登録
      </a>
    </div>

    <div v-if="masterError || listError" class="px-4 pt-4 space-y-3">
      <div v-if="masterError" class="rounded-2xl border border-[var(--color-tag-warning)] px-4 py-3 text-sm bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))]">
        <div class="font-semibold text-[var(--color-tag-warning)]">補助データの取得に失敗しました</div>
        <div class="mt-1 opacity-80">@{{ masterError }}</div>
        <div class="mt-2">
          <button @click="fetchMasters" class="px-3 py-2 rounded-xl border border-[var(--color-tag-warning)] text-sm">再読込</button>
        </div>
      </div>
      <div v-if="listError" class="rounded-2xl border border-[var(--color-tag-eol)] px-4 py-3 text-sm bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))]">
        <div class="font-semibold text-[var(--color-tag-eol)]">部品一覧の取得に失敗しました</div>
        <div class="mt-1 opacity-80">@{{ listError }}</div>
        <div class="mt-2 flex flex-wrap gap-2">
          <button @click="fetchParts" class="px-3 py-2 rounded-xl border border-[var(--color-tag-eol)] text-sm">再試行</button>
          <button @click="clearFilters" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm">条件をクリア</button>
        </div>
      </div>
    </div>

    <!-- 件数・ページネーション設定 -->
    <div class="px-4 py-2 flex items-center justify-between text-xs opacity-60 border-b border-[var(--color-border)] flex-shrink-0">
      <span>@{{ parts.length }}件 / 全@{{ total }}件</span>
      <div class="flex items-center gap-2">
        <span>表示件数:</span>
        <select v-model.number="perPage" class="input-text text-xs py-0.5 w-20">
          <option :value="20">20件</option>
          <option :value="50">50件</option>
          <option :value="100">100件</option>
        </select>
      </div>
    </div>

    <!-- ローディング -->
    <div v-if="loading" class="flex-1 flex items-center justify-center opacity-50">
      <span class="text-sm">読み込み中...</span>
    </div>

    <!-- 部品リスト -->
    <div v-else class="flex-1 overflow-y-auto p-4 space-y-2">
      <div v-if="parts.length === 0" class="text-center opacity-40 py-20">
        <p class="text-lg">該当する部品がありません</p>
      </div>
      <div v-for="(part, i) in parts" :key="part.id"
        class="card flex items-center gap-4 px-4 py-3"
        :class="i % 2 === 0 ? 'card-even' : 'card-odd'">
        <!-- サムネイル -->
        <img v-if="part.image_url" :src="part.image_url" class="thumbnail flex-shrink-0" />
        <div v-else class="w-16 h-16 flex-shrink-0 rounded border border-[var(--color-border)] flex items-center justify-center opacity-30 text-xs">無</div>
        <!-- 部品情報 -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="list-title truncate">@{{ part.common_name || part.part_number }}</span>
            <span :class="'tag ' + procurementClass[part.procurement_status]" class="text-xs">
              @{{ procurementLabel[part.procurement_status] }}
            </span>
            <span v-for="cat in part.categories" :key="cat.id" class="tag text-xs">@{{ cat.name }}</span>
          </div>
          <p class="text-xs opacity-60 mt-0.5 font-mono">@{{ part.part_number }} @{{ part.manufacturer ? '/ ' + part.manufacturer : '' }}</p>
          <div class="flex gap-4 mt-1 text-xs opacity-70">
            <span>新品: @{{ part.quantity_new }}個</span>
            <span>中古: @{{ part.quantity_used }}個</span>
          </div>
        </div>
        <!-- アクション -->
        <div class="flex gap-2 flex-shrink-0">
          <button @click="toggleCompare(part)"
            class="px-3 py-1 rounded text-xs border transition-colors"
            :class="inCompare(part) ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
            @{{ inCompare(part) ? '比較中' : '比較' }}
          </button>
          <a :href="'/components/' + part.id"
            class="btn btn-primary px-3 py-1 rounded text-xs">詳細</a>
        </div>
      </div>
    </div>

    <!-- ページネーション -->
    <div v-if="lastPage > 1" class="px-4 py-3 border-t border-[var(--color-border)] flex items-center justify-center gap-2 flex-shrink-0">
      <button @click="page = 1"      :disabled="page === 1"      class="btn px-2 py-1 rounded text-xs disabled:opacity-30">«</button>
      <button @click="page--"        :disabled="page === 1"      class="btn px-2 py-1 rounded text-xs disabled:opacity-30">‹</button>
      <span class="text-xs">@{{ page }} / @{{ lastPage }}</span>
      <button @click="page++"        :disabled="page === lastPage" class="btn px-2 py-1 rounded text-xs disabled:opacity-30">›</button>
      <button @click="page = lastPage" :disabled="page === lastPage" class="btn px-2 py-1 rounded text-xs disabled:opacity-30">»</button>
    </div>
  </main>

  <!-- トースト -->
  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
    <div v-for="t in toasts" :key="t.id"
      class="px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
      :class="t.type === 'error' ? 'bg-[var(--color-tag-eol)]' : 'bg-[var(--color-accent)]'">
      @{{ t.msg }}
    </div>
  </div>
</div>

</body>
</html>
