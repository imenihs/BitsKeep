<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>部品一覧 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@php($canEdit = auth()->user()->isEditor())
@include('partials.app-header', ['current' => '部品一覧'])

<div id="app" data-page="components-list" class="min-h-screen">
  <main class="max-w-7xl mx-auto px-4 py-4">
    @include('partials.app-breadcrumbs', ['items' => [['label' => '部品一覧', 'current' => true]]])

    <section class="rounded-3xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-5 mb-4">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
          <p class="text-xs uppercase tracking-[0.2em] opacity-50">Component Index</p>
          <div class="flex flex-wrap items-center gap-3 mt-1">
            <h1 class="text-2xl font-bold">部品一覧</h1>
            <span class="text-sm opacity-60">表示 @{{ parts.length }}件 / 全@{{ total }}件</span>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button @click="favoriteOnly = !favoriteOnly"
            class="px-3 py-2 rounded-2xl border text-sm transition-colors"
            :class="favoriteOnly ? 'border-[var(--color-primary)] text-[var(--color-primary)] bg-[var(--color-bg)]' : 'border-[var(--color-border)]'">
            ★ お気に入りのみ
          </button>
          <a v-if="alertCount > 0" href="/stock-alert"
            class="flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-bold text-white no-underline"
            style="background-color: var(--color-tag-warning);">
            ⚠ 在庫警告 @{{ alertCount }}件
          </a>
          <div v-if="compareList.length" class="flex items-center gap-2 px-3 py-2 rounded-2xl border border-[var(--color-primary)] text-xs">
            <span class="font-semibold text-[var(--color-primary)]">比較: @{{ compareList.length }}件</span>
            <a :href="compareUrl" class="btn btn-primary text-xs px-2 py-1 rounded no-underline">比較する</a>
          </div>
          @if ($canEdit)
          <a href="{{ route('components.create') }}" class="btn btn-primary text-sm px-4 py-2 rounded no-underline">
            <span class="feature-lock">編</span>
            + 新規登録
          </a>
          @else
          <div class="feature-disabled rounded-xl border border-[var(--color-border)] px-4 py-2 bg-[var(--color-card-odd)]">
            <div class="flex items-center gap-2 text-sm font-semibold">
              <span class="feature-lock">編</span>
              <span>+ 新規登録</span>
            </div>
            <div class="mt-1 text-xs opacity-70">閲覧者のため登録できません</div>
          </div>
          @endif
        </div>
      </div>

      <div class="mt-5 grid gap-3 xl:grid-cols-[minmax(0,1.9fr)_repeat(4,minmax(0,0.8fr))]">
        <div>
          <label class="block text-[11px] font-semibold opacity-60 mb-1">検索</label>
          <input v-model="searchQuery" type="text" placeholder="型番・通称・部品名・メーカーで検索..."
            class="input-text w-full" />
        </div>
        <div>
          <label class="block text-[11px] font-semibold opacity-60 mb-1">分類</label>
          <select v-model="filterCategories" multiple size="1" class="input-text h-11 w-full">
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">@{{ cat.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-semibold opacity-60 mb-1">入手可否</label>
          <select v-model="filterStatus" class="input-text w-full">
            <option value="">すべて</option>
            <option value="active">量産中</option>
            <option value="eol">EOL</option>
            <option value="last_time">在庫限り</option>
            <option value="nrnd">新規非推奨</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-semibold opacity-60 mb-1">並び順</label>
          <select v-model="sortOrder" class="input-text w-full">
            <option value="updated_at">更新順</option>
            <option value="name">名前順</option>
            <option value="part_number">型番順</option>
          </select>
        </div>
        <div class="flex flex-col gap-2 justify-end">
          <button @click="clearFilters" :disabled="!hasFilter"
            class="h-11 px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm disabled:opacity-40">
            条件をクリア
          </button>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <button @click="advancedOpen = !advancedOpen"
          class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm hover:border-[var(--color-primary)] transition-colors">
          @{{ advancedOpen ? '詳細条件を閉じる' : '詳細条件を開く' }}
        </button>
        <div class="flex items-center gap-2 ml-auto">
          <span class="text-xs opacity-60">表示件数</span>
          <select v-model.number="perPage" class="input-text text-sm py-1 w-24">
            <option :value="20">20件</option>
            <option :value="50">50件</option>
            <option :value="100">100件</option>
          </select>
        </div>
      </div>

      <div v-if="advancedOpen" class="mt-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">メーカー</label>
            <input v-model="advManufacturer" type="text" class="input-text w-full" placeholder="例: Murata" />
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">パッケージ</label>
            <div class="space-y-2">
              <select v-model="advPackageGroupId" class="input-text w-full">
                <option value="">パッケージ分類を選択</option>
                <option v-for="group in packageGroups" :key="group.id" :value="group.id">@{{ group.name }}</option>
              </select>
              <input v-model="advPackageQuery" type="text" class="input-text w-full" :disabled="!advPackageGroupId" placeholder="パッケージ名で絞り込み" />
              <div class="max-h-32 overflow-y-auto rounded border border-[var(--color-border)] bg-[var(--color-card-even)] p-2 space-y-1">
                <button v-for="pkg in filteredAdvancedPackages" :key="pkg.id" type="button" @click="advPackageId = pkg.id"
                  class="w-full flex items-center justify-between rounded px-2 py-1 text-sm hover:bg-[var(--color-card-odd)]"
                  :class="advPackageId == pkg.id ? 'bg-[var(--color-card-odd)] border border-[var(--color-primary)]' : ''">
                  <span>@{{ pkg.name }}</span>
                  <span class="text-xs opacity-60">@{{ advPackageId == pkg.id ? '選択中' : '使う' }}</span>
                </button>
                <p v-if="!advPackageGroupId" class="text-xs opacity-40 p-1">先にパッケージ分類を選択してください</p>
                <p v-else-if="!filteredAdvancedPackages.length" class="text-xs opacity-40 p-1">パッケージがありません</p>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">スペック項目</label>
            <select v-model="advSpecTypeId" class="input-text w-full">
              <option value="">-- 選択 --</option>
              <option v-for="st in specTypes" :key="st.id" :value="st.id">@{{ st.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">照合基準</label>
            <div class="flex flex-wrap gap-1 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-1"
              :class="!advSpecTypeId ? 'opacity-45 pointer-events-none' : ''">
              <button v-for="option in specProfileOptions" :key="option.value" type="button"
                @click="advSpecProfile = option.value"
                class="min-h-9 flex-1 rounded-xl px-2 text-xs font-semibold transition-colors"
                :class="advSpecProfile === option.value ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-odd)] opacity-75'">
                @{{ option.label }}
              </button>
            </div>
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">単位</label>
            <input v-model="advUnit" type="text" class="input-text w-full" placeholder="例: ohm, V" />
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">最小値</label>
            <input v-model="advMin" type="text" class="input-text w-full" placeholder="例: 1kΩ" />
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">最大値</label>
            <input v-model="advMax" type="text" class="input-text w-full" placeholder="例: 10kΩ" />
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">在庫下限</label>
            <input v-model="advMinStock" type="number" class="input-text w-full" placeholder="0" />
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">在庫状態</label>
            <select v-model="advInventoryState" class="input-text w-full">
              <option value="">すべて</option>
              <option value="new">新品あり</option>
              <option value="used">中古あり</option>
              <option value="empty">在庫なし</option>
              <option value="warning">警告あり</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">購入日From</label>
            <input v-model="advPurchasedFrom" type="date" class="input-text w-full" />
          </div>
          <div>
            <label class="block text-[11px] font-semibold opacity-60 mb-1">購入日To</label>
            <input v-model="advPurchasedTo" type="date" class="input-text w-full" />
          </div>
        </div>
      </div>

      <div v-if="activeFilterChips.length" class="mt-4 flex flex-wrap gap-2">
        <button v-for="chip in activeFilterChips" :key="chip.key" @click="removeFilterChip(chip.key)"
          class="inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-1.5 text-xs hover:border-[var(--color-primary)]">
          <span>@{{ chip.label }}</span>
          <span class="opacity-50">✕</span>
        </button>
      </div>
    </section>

    <div v-if="masterError || listError" class="mb-4 space-y-3">
      <div v-if="masterError" class="rounded-2xl border border-[var(--color-tag-warning)] px-4 py-3 text-sm bg-[color-mix(in_srgb,var(--color-tag-warning)_10%,var(--color-bg))]">
        <div class="font-semibold text-[var(--color-tag-warning)]">補助データの取得に失敗しました</div>
        <div class="mt-1 opacity-80">@{{ masterError }}</div>
        <div class="mt-2 flex flex-wrap gap-2">
          <button @click="fetchMasters" class="px-3 py-2 rounded-xl border border-[var(--color-tag-warning)] text-sm">再読込</button>
          <a href="{{ route('master.index') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">マスタ管理へ</a>
          <a href="{{ route('stock.alert') }}" class="px-3 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">在庫警告へ</a>
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

    <div class="rounded-3xl border border-[var(--color-border)] bg-[var(--color-card-even)] overflow-hidden">
      <div v-if="loading" class="flex items-center justify-center py-24 opacity-50">
        <span class="text-sm">読み込み中...</span>
      </div>

      <div v-else class="p-4 space-y-2">
        <div v-if="emptyState" class="rounded-3xl border border-[var(--color-border)] bg-[var(--color-bg)] px-6 py-10 text-center">
          <p class="text-lg font-semibold">@{{ emptyState.title }}</p>
          <p class="mt-2 text-sm opacity-60">@{{ emptyState.desc }}</p>
          <div class="mt-4 flex flex-wrap justify-center gap-2">
            <button v-if="emptyState.actions.includes('clear')" @click="clearFilters" class="px-4 py-2 rounded-xl border border-[var(--color-border)] text-sm">条件をクリア</button>
            <button v-if="emptyState.actions.includes('retry')" @click="fetchParts" class="px-4 py-2 rounded-xl border border-[var(--color-border)] text-sm">再検索</button>
            <a v-if="emptyState.actions.includes('create')" href="{{ route('components.create') }}" class="px-4 py-2 rounded-xl border border-[var(--color-primary)] text-sm no-underline text-inherit">新規登録</a>
            <a v-if="emptyState.actions.includes('csv')" href="{{ route('csv.import') }}" class="px-4 py-2 rounded-xl border border-[var(--color-border)] text-sm no-underline text-inherit">CSVインポート</a>
          </div>
        </div>
        <div v-for="(part, i) in parts" v-else :key="part.id"
          class="flex items-stretch gap-3">
          <a :href="'/components/' + part.id"
            class="card flex flex-1 items-center gap-4 px-4 py-3 no-underline text-inherit cursor-pointer hover:border-[var(--color-primary)] transition-colors min-w-0"
            :class="i % 2 === 0 ? 'card-even' : 'card-odd'">
            <img v-if="part.image_url" :src="part.image_url" class="thumbnail flex-shrink-0" />
            <div v-else class="w-16 h-16 flex-shrink-0 rounded border border-[var(--color-border)] flex items-center justify-center opacity-30 text-xs">無</div>

            <div class="flex-1 min-w-0">
              <div class="flex items-start gap-2 flex-wrap">
                <span class="list-title font-mono tracking-normal truncate">@{{ part.part_number || part.name }}</span>
                <span :class="'tag ' + procurementClass[part.procurement_status]" class="text-xs">
                  @{{ procurementLabel[part.procurement_status] }}
                </span>
                <span v-if="part.needs_reorder" class="tag text-xs bg-amber-100 text-amber-700">警告</span>
                <span v-for="cat in part.categories" :key="cat.id" class="tag text-xs">@{{ cat.name }}</span>
              </div>
              <p class="text-xs opacity-70 mt-0.5 truncate">
                <span v-if="part.manufacturer" class="font-semibold">@{{ part.manufacturer }}</span>
                <span v-if="part.common_name">@{{ part.manufacturer ? ' / ' : '' }}通称: @{{ part.common_name }}</span>
                <span v-if="part.name && part.name !== part.part_number && part.name !== part.common_name">
                  @{{ (part.manufacturer || part.common_name) ? ' / ' : '' }}名称: @{{ part.name }}
                </span>
              </p>
              <div class="flex flex-wrap gap-3 mt-1 text-xs opacity-70">
                <span>新品: @{{ part.quantity_new }}個</span>
                <span>中古: @{{ part.quantity_used }}個</span>
                <span v-if="part.packages?.length">パッケージ: @{{ part.packages.map((pkg) => pkg.name).join(' / ') }}</span>
                <span v-if="part.cheapest_supplier_name">最安: @{{ formatCurrency(part.cheapest_unit_price, {decimals:2}) }} / @{{ part.cheapest_supplier_name }}</span>
                <span>更新: @{{ new Date(part.updated_at).toLocaleDateString('ja-JP') }}</span>
              </div>
              <div class="mt-1 text-xs opacity-60">
                カテゴリ@{{ part.categories?.length ?? 0 }}件 / 仕入先@{{ part.component_suppliers?.length ?? 0 }}件 / 在庫ブロック@{{ part.inventory_blocks_count ?? 0 }}件
              </div>
            </div>
          </a>

          <div class="flex flex-shrink-0 items-center" @click.stop>
            <div class="flex flex-col gap-2 justify-center">
              <button @click="handleToggleFavorite(part.id)"
                class="px-3 py-2 rounded text-xs border transition-colors"
                :class="isFavorite(part.id) ? 'border-orange-400 text-orange-400 bg-orange-400/10' : 'border-[var(--color-border)] hover:border-orange-400 hover:text-orange-400'">
                @{{ isFavorite(part.id) ? '★ お気に入り' : '☆ お気に入り' }}
              </button>
              <button @click="toggleCompare(part)"
                class="px-3 py-2 rounded text-xs border transition-colors"
                :class="inCompare(part) ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
                @{{ inCompare(part) ? '比較中' : '比較に追加' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="lastPage > 1" class="px-4 py-3 border-t border-[var(--color-border)] flex items-center justify-center gap-2">
        <button @click="page = 1" :disabled="page === 1" class="btn px-2 py-1 rounded text-xs disabled:opacity-30">«</button>
        <button @click="page--" :disabled="page === 1" class="btn px-2 py-1 rounded text-xs disabled:opacity-30">‹</button>
        <span class="text-xs">@{{ page }} / @{{ lastPage }}</span>
        <button @click="page++" :disabled="page === lastPage" class="btn px-2 py-1 rounded text-xs disabled:opacity-30">›</button>
        <button @click="page = lastPage" :disabled="page === lastPage" class="btn px-2 py-1 rounded text-xs disabled:opacity-30">»</button>
      </div>
    </div>
  </main>

  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
    <div v-for="t in toasts" :key="t.id"
      class="px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
      :class="t.type === 'error' ? 'bg-[var(--color-tag-eol)]' : 'bg-[var(--color-accent)]'">
      @{{ t.msg }}
    </div>
  </div>
  <div class="max-w-7xl mx-auto px-4 pb-6">
    @include('partials.app-breadcrumbs', ['items' => [['label' => '部品一覧', 'current' => true]], 'class' => 'mt-6'])
  </div>
</div>

</body>
</html>
