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
    <p class="text-sm opacity-60 mt-1">品名・型番・商社部品IDで検索し、複数部品を順に受け入れます</p>
  </header>

  <section class="card p-5 bg-[var(--color-card-even)] mb-4 block">
    <div class="flex gap-3">
      <input v-model="query" @keydown.enter.prevent="search" type="text" class="input-text flex-1" placeholder="部品名 / 型番 / 商社部品IDで検索" />
      <button @click="search" class="btn btn-primary px-4 py-2 rounded text-sm">検索</button>
    </div>
    <div v-if="loading" class="mt-4 text-sm opacity-60">検索中...</div>
    <div v-else class="mt-4 grid gap-2">
      <button v-for="part in parts" :key="part.id" @click="choosePart(part)"
        class="text-left rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-3 hover:border-[var(--color-primary)]">
        <div class="font-semibold">@{{ part.common_name || part.part_number }}</div>
        <div class="text-xs opacity-60 mt-1">@{{ part.part_number }} / @{{ part.manufacturer || 'メーカー未設定' }}</div>
      </button>
      <div v-if="!loading && query && parts.length === 0" class="text-sm opacity-50">一致する部品がありません</div>
    </div>
  </section>

  <!-- 入庫完了バナー（次の部品へ促す） -->
  <div v-if="justSubmitted && selectedPart" class="mb-4 flex items-center justify-between gap-4 rounded-xl border border-[var(--color-tag-ok)] bg-[var(--color-tag-ok)]/10 px-5 py-4">
    <div>
      <div class="font-semibold text-[var(--color-tag-ok)]">入庫が完了しました</div>
      <div class="text-sm opacity-70 mt-0.5">@{{ selectedPart.common_name || selectedPart.part_number }} を入庫しました</div>
    </div>
    <div class="flex gap-3 shrink-0">
      <button @click="justSubmitted = false" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">この部品を続けて入庫</button>
      <button @click="nextPart" class="btn btn-primary px-5 py-2 rounded text-sm">次の部品を入庫</button>
    </div>
  </div>

  <section v-if="selectedPart" class="card p-5 bg-[var(--color-card-even)] block">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-lg font-bold">@{{ selectedPart.common_name || selectedPart.part_number }}</h2>
        <div class="text-sm opacity-60 font-mono mt-1">@{{ selectedPart.part_number }}</div>
      </div>
      <a :href="'/components/' + selectedPart.id" class="btn px-4 py-2 rounded border border-[var(--color-border)] text-sm no-underline text-inherit">詳細を見る</a>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-semibold mb-1">在庫区分</label>
          <select v-model="form.stock_type" class="input-text w-full">
            <option v-for="(label, value) in stockTypeLabel" :key="value" :value="value">@{{ label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">新品/中古</label>
          <select v-model="form.condition" class="input-text w-full">
            <option value="new">新品</option>
            <option value="used">中古</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">数量</label>
          <input v-model.number="form.quantity" type="number" min="1" class="input-text w-full" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">入庫先棚</label>
          <select v-model="form.location_id" class="input-text w-full">
            <option value="">未設定</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">@{{ location.code }} / @{{ location.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">ロット番号</label>
          <input v-model="form.lot_number" type="text" class="input-text w-full" />
        </div>
        <div v-if="form.stock_type === 'reel'">
          <label class="block text-xs font-semibold mb-1">リール番号</label>
          <input v-model="form.reel_code" type="text" class="input-text w-full" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">備考</label>
          <input v-model="form.note" type="text" class="input-text w-full" />
        </div>
      </div>

      <div class="space-y-3">
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-3">
          <div class="text-xs opacity-60">入庫結果</div>
          <div class="mt-2 text-sm font-semibold" :class="willMerge ? 'text-[var(--color-tag-ok)]' : 'text-[var(--color-primary)]'">
            @{{ willMerge ? '既存在庫へ加算されます' : '新しい在庫ブロックを作成します' }}
          </div>
        </div>
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-3">
          <div class="text-xs opacity-60 mb-2">一致する既存在庫ブロック</div>
          <div v-if="matchingBlocks.length" class="space-y-2 text-sm">
            <div v-for="block in matchingBlocks" :key="block.id" class="flex items-center justify-between">
              <div>@{{ block.location?.code || '未設定' }} / @{{ stockTypeLabel[block.stock_type] }} / @{{ block.condition === 'new' ? '新品' : '中古' }}</div>
              <div class="font-mono">@{{ block.quantity }}個</div>
            </div>
          </div>
          <div v-else class="text-sm opacity-50">一致する既存在庫ブロックはありません</div>
        </div>
        <div class="flex justify-end">
          <button @click="submit" :disabled="submitting" class="btn btn-primary px-5 py-3 rounded text-sm disabled:opacity-50">
            @{{ submitting ? '入庫中...' : '入庫する' }}
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- セッション処理ログ -->
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
