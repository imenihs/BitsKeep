<!DOCTYPE html>
<html lang="ja" data-theme="light">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>エンジニア電卓 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
<div id="app" data-page="engineering-calc" class="p-6 max-w-7xl mx-auto">

  <nav class="breadcrumb mb-4">
    @include('partials.brand-home-link')
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span>ツール</span>
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="current">エンジニア電卓</span>
  </nav>

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">エンジニア電卓</h1>
    <p class="text-sm opacity-60 mt-1">進数混在・SI接頭辞・複数行変数・E系列丸め・数値解法に対応</p>
  </header>

  <div class="grid grid-cols-1 xl:grid-cols-[280px_1fr_320px] gap-6">
    <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-bold">履歴</h2>
        <button @click="clearHistory" class="text-xs link-text">クリア</button>
      </div>
      <div class="space-y-2 max-h-[70vh] overflow-y-auto">
        <button v-for="item in history" :key="item.id" @click="useHistory(item)"
          class="w-full text-left rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3 hover:border-[var(--color-primary)]">
          <div class="font-mono text-xs opacity-70 whitespace-pre-line">@{{ item.expr }}</div>
          <div class="mt-1 text-sm font-semibold">@{{ item.result }}</div>
          <div class="mt-1 text-[11px] opacity-50">@{{ item.meta }}</div>
        </button>
        <div v-if="history.length === 0" class="text-center py-6 opacity-30 text-xs">履歴なし</div>
      </div>

      <div class="mt-5 pt-4 border-t border-[var(--color-border)]">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-sm font-bold">お気に入り</h2>
          <button @click="saveFavorite" class="text-xs link-text">現在式を保存</button>
        </div>
        <div class="space-y-2 max-h-60 overflow-y-auto">
          <button v-for="item in favorites" :key="item.id" @click="useFavorite(item)"
            class="w-full text-left rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3 hover:border-[var(--color-primary)]">
            <div class="font-mono text-xs whitespace-pre-line">@{{ item.expr }}</div>
          </button>
          <div v-if="favorites.length === 0" class="text-center py-4 opacity-30 text-xs">お気に入りなし</div>
        </div>
      </div>
    </section>

    <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <span class="text-sm font-bold">式エディタ</span>
        <span class="tag tag-ok text-xs">SI接頭辞</span>
        <span class="tag tag-warning text-xs">複数行変数</span>
        <span class="tag text-xs">即時計算</span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_240px] gap-4">
        <div>
          <textarea v-model="expr" rows="14"
            class="input-text min-h-[320px] w-full px-4 py-3 font-mono text-sm leading-6 resize-none"
            placeholder="例:
vin = 5
r1 = 10k
r2 = 3.3k
vin * r2 / (r1 + r2)"></textarea>

          <div class="mt-3 flex flex-wrap gap-2">
            <button v-for="snippet in snippets" :key="snippet.label" @click="expr = snippet.value"
              class="px-3 py-1.5 rounded border border-[var(--color-border)] text-xs hover:bg-[var(--color-primary)] hover:text-white transition-colors">
              @{{ snippet.label }}
            </button>
          </div>
        </div>

        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-xs font-semibold opacity-60 mb-2">定数 / 関数</div>
          <div class="space-y-2 text-xs">
            <div v-for="item in presetItems" :key="item.name" class="rounded border border-[var(--color-border)] p-2">
              <div class="font-mono font-semibold">@{{ item.name }}</div>
              <div class="opacity-60 mt-1">@{{ item.desc }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 flex gap-3">
        <button @click="run(true)" class="btn btn-primary px-4 py-2 rounded text-sm">評価して履歴へ保存</button>
        <button @click="copyResult" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">結果をコピー</button>
        <button @click="clearCalc" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">クリア</button>
      </div>
    </section>

    <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
      <h2 class="text-sm font-bold mb-3">結果</h2>

      <div v-if="error" class="rounded-xl border border-red-400 bg-red-50 p-4 text-sm text-red-600">@{{ error }}</div>
      <template v-else>
        <div class="rounded-xl border-2 border-[var(--color-primary)] p-4 bg-[var(--color-bg)]">
          <div class="text-xs opacity-60 mb-1">評価結果</div>
          <div class="font-mono text-3xl font-bold text-[var(--color-primary)]">@{{ decResult }}</div>
          <div class="text-xs opacity-60 mt-2">型: @{{ resultType }}</div>
          <div class="text-xs opacity-60 mt-1">工学表記: @{{ engResult }}</div>
        </div>

        <div class="grid grid-cols-2 gap-2 mt-4">
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="text-[11px] opacity-60">DEC</div>
            <div class="font-mono text-sm mt-1 break-all">@{{ decResult }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="text-[11px] opacity-60">ENG</div>
            <div class="font-mono text-sm mt-1 break-all">@{{ engResult }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="text-[11px] opacity-60">HEX</div>
            <div class="font-mono text-sm mt-1 break-all">@{{ hexResult }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="text-[11px] opacity-60">BIN</div>
            <div class="font-mono text-sm mt-1 break-all">@{{ binResult }}</div>
          </div>
        </div>

        <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)] mt-4">
          <div class="text-[11px] opacity-60">OCT</div>
          <div class="font-mono text-sm mt-1 break-all">@{{ octResult }}</div>
        </div>

        <div class="mt-4 rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
          <div class="text-xs font-semibold opacity-60 mb-2">評価スコープ</div>
          <div v-if="scopeEntries.length" class="space-y-1 text-xs font-mono">
            <div v-for="[key, value] in scopeEntries" :key="key" class="flex justify-between gap-3">
              <span>@{{ key }}</span>
              <span class="break-all text-right">@{{ formatNum(value) }}</span>
            </div>
          </div>
          <div v-else class="text-xs opacity-40">変数定義なし</div>
        </div>
      </template>
    </section>
  </div>

</div>
</body>
</html>
