<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>入庫 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '入庫'])
<div id="app" data-page="stock-in" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => '入庫', 'current' => true],
  ]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">入庫</h1>
    <p class="text-sm opacity-60 mt-1">品名・型番・商社部品IDで検索し、入庫対象へ追加して一括入庫します</p>
  </header>

  <section v-if="alertParts.length" class="card p-5 bg-[var(--color-card-odd)] mb-4 block">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h2 class="text-lg font-bold">要入庫候補</h2>
        <div class="text-sm opacity-60 mt-1">在庫警告中の部品を、そのまま入庫対象へ追加できます</div>
      </div>
      <a href="{{ route('stock.alert') }}" class="text-sm no-underline hover:text-[var(--color-primary)] transition-colors">在庫警告を開く</a>
    </div>
    <div class="space-y-2">
      <div
        v-for="part in alertParts"
        :key="part.id"
        class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-3 overflow-x-auto">
        <div class="grid min-w-[760px] grid-cols-[minmax(260px,1.4fr)_120px_120px_150px] gap-3 items-center">
          <div class="min-w-0">
            <div class="font-semibold truncate">@{{ part.common_name || part.part_number }}</div>
            <div class="text-xs opacity-60 font-mono mt-1 truncate">@{{ part.part_number }}</div>
          </div>
          <div class="text-sm font-mono whitespace-nowrap">新品 @{{ part.quantity_new }}個</div>
          <div class="text-sm font-mono whitespace-nowrap">発注点 @{{ part.threshold_new }}個</div>
          <button
            type="button"
            @click="queuePart(part)"
            class="px-3 py-2 rounded border border-[var(--color-border)] text-sm whitespace-nowrap hover:border-[var(--color-primary)]">
            入庫対象へ追加
          </button>
        </div>
      </div>
    </div>
  </section>

  <section class="card p-5 bg-[var(--color-card-even)] mb-4 block">
    <div class="flex items-center justify-between gap-4 mb-3">
      <div>
        <h2 class="text-lg font-bold">部品検索</h2>
        <div class="text-sm opacity-60 mt-1">入力すると自動検索します</div>
      </div>
      <button
        @click="addSelectedResults"
        :disabled="searchSelectionCount === 0"
        class="btn btn-primary px-4 py-2 rounded text-sm disabled:opacity-50">
        選択を追加 <span v-if="searchSelectionCount > 0">(@{{ searchSelectionCount }})</span>
      </button>
    </div>
    <input v-model="query" type="text" class="input-text w-full" placeholder="部品名 / 型番 / 商社部品IDで検索" />

    <div v-if="loading" class="mt-4 text-sm opacity-60">検索中...</div>
    <div v-else class="mt-4 space-y-2 max-h-80 overflow-y-auto">
      <label
        v-for="part in parts"
        :key="part.id"
        class="flex items-center gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-3 cursor-pointer hover:border-[var(--color-primary)]">
        <input
          :checked="selectedSearchIds.includes(part.id)"
          @change="toggleSearchSelection(part.id)"
          type="checkbox"
          class="h-4 w-4" />
        <div class="min-w-0 flex-1">
          <div class="font-semibold truncate">@{{ part.common_name || part.part_number }}</div>
          <div class="text-xs opacity-60 mt-1 truncate">@{{ part.part_number }} / @{{ part.manufacturer || 'メーカー未設定' }}</div>
        </div>
      </label>
      <div v-if="!loading && query && parts.length === 0" class="text-sm opacity-50">一致する部品がありません</div>
    </div>
  </section>

  <section v-if="queueCount > 0" class="card p-5 bg-[var(--color-card-even)] block">
    <div class="flex items-start justify-between gap-4 mb-4">
      <div>
        <h2 class="text-lg font-bold">入庫対象一覧</h2>
        <div class="text-sm opacity-60 mt-1">@{{ queueCount }}件をまとめて入庫します</div>
      </div>
      <button @click="submitAll" :disabled="submitting" class="btn btn-primary px-5 py-3 rounded text-sm disabled:opacity-50">
        @{{ submitting ? '入庫中...' : '一括入庫する' }}
      </button>
    </div>

    <div class="space-y-3">
      <div v-for="entry in selectedEntries" :key="entry.key" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-4">
        <div class="flex items-start justify-between gap-4 mb-3">
          <div>
            <div class="font-semibold">@{{ entry.part.common_name || entry.part.part_number }}</div>
            <div class="text-xs opacity-60 font-mono mt-1">@{{ entry.part.part_number }}</div>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-xs opacity-60" :class="matchingBlocks(entry).length > 0 ? 'text-[var(--color-tag-ok)]' : ''">
              @{{ matchingBlocks(entry).length > 0 ? '既存在庫へ加算' : '新規ブロック' }}
            </div>
            <a :href="'/components/' + entry.part.id" target="_blank" rel="noreferrer" class="text-xs no-underline hover:text-[var(--color-primary)]">詳細</a>
            <button @click="removeEntry(entry.key)" class="px-3 py-2 rounded border border-[var(--color-border)] text-xs">除外</button>
          </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-[120px_120px_100px_minmax(180px,1fr)_minmax(140px,1fr)_minmax(140px,1fr)_minmax(180px,1fr)]">
          <select v-model="entry.form.stock_type" class="input-text">
            <option v-for="(label, value) in stockTypeLabel" :key="value" :value="value">@{{ label }}</option>
          </select>
          <select v-model="entry.form.condition" class="input-text">
            <option value="new">新品</option>
            <option value="used">中古</option>
          </select>
          <input v-model.number="entry.form.quantity" type="number" min="1" class="input-text text-right" />
          <select v-model="entry.form.location_id" class="input-text">
            <option value="">未設定</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">@{{ location.code }} / @{{ location.name }}</option>
          </select>
          <input v-model="entry.form.lot_number" type="text" class="input-text" placeholder="ロット番号" />
          <input v-model="entry.form.reel_code" type="text" class="input-text" :disabled="entry.form.stock_type !== 'reel'" placeholder="リール番号" />
          <input v-model="entry.form.note" type="text" class="input-text" placeholder="備考" />
        </div>

        <div v-if="matchingBlocks(entry).length" class="mt-3 text-xs opacity-60">
          一致する在庫:
          <span v-for="block in matchingBlocks(entry)" :key="block.id" class="mr-3">
            @{{ block.location?.code || '未設定' }} / @{{ stockTypeLabel[block.stock_type] }} / @{{ block.condition === 'new' ? '新品' : '中古' }} / @{{ block.quantity }}個
          </span>
        </div>
      </div>
    </div>
  </section>

  <section v-if="processedLog.length" class="mt-4 card p-5 bg-[var(--color-card-odd)] block">
    <h2 class="text-sm font-semibold mb-3 opacity-80">本セッションの入庫履歴</h2>
    <div class="space-y-2">
      <div v-for="(log, i) in processedLog" :key="i"
        class="flex items-center justify-between gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-2 text-sm">
        <div class="flex items-center gap-3">
          <span class="opacity-50 text-xs tabular-nums">@{{ log.at }}</span>
          <span class="font-semibold">@{{ log.commonName || log.partNumber }}</span>
          <span class="text-xs opacity-60 font-mono">@{{ log.partNumber }}</span>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <span class="font-mono font-bold">@{{ log.quantity }}個</span>
          <span class="tag text-xs" :class="log.merged ? 'tag-ok' : ''">@{{ log.merged ? '加算' : '新規ブロック' }}</span>
          <a :href="'/components/' + log.partId" class="link-text text-xs">詳細</a>
        </div>
      </div>
    </div>
  </section>

  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2">
    <div v-for="t in toasts" :key="t.id"
      class="px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
      :class="t.type === 'error' ? 'bg-[var(--color-tag-eol)]' : 'bg-[var(--color-accent)]'">
      @{{ t.msg }}
    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [
    ['label' => '入庫', 'current' => true],
  ], 'class' => 'mt-6'])
</div>
</body>
</html>
