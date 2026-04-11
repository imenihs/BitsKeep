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
@include('partials.app-header', ['current' => 'エンジニア電卓'])
<div id="app" data-page="engineering-calc" class="px-4 py-4 sm:px-6 sm:py-6 max-w-7xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'エンジニア電卓', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">エンジニア電卓</h1>
    <p class="text-sm opacity-60 mt-1">進数混在・SI接頭辞・複数行変数・E系列丸め・数値解法に対応</p>
  </header>

  <div class="grid grid-cols-1 xl:grid-cols-[280px_1fr_320px] gap-6">
    <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-bold">履歴</h2>
        <div class="flex items-center gap-3">
          <span class="text-[11px] opacity-50">固定式は下段へ保存</span>
          <button @click="clearHistory" class="text-xs link-text">クリア</button>
        </div>
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
          <h2 class="text-sm font-bold">固定式</h2>
          <button @click="saveFavorite" class="text-xs link-text">現在式を固定</button>
        </div>
        <div class="space-y-2 max-h-60 overflow-y-auto">
          <button v-for="item in favorites" :key="item.id" @click="useFavorite(item)"
            class="w-full text-left rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3 hover:border-[var(--color-primary)]">
            <div class="font-mono text-xs whitespace-pre-line">@{{ item.expr }}</div>
          </button>
          <div v-if="favorites.length === 0" class="text-center py-4 opacity-30 text-xs">固定式なし</div>
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

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-4">
        <div>
          <textarea v-model="expr" rows="14"
            class="input-text min-h-[320px] w-full px-4 py-3 font-mono text-sm leading-6 resize-none"
            :class="error ? 'border-red-400 text-red-700' : ''"
            placeholder="例:
vin = 5
r1 = 10k
r2 = 3.3k
vin * r2 / (r1 + r2)"></textarea>

          <div v-if="error" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3">
            <div class="text-xs font-semibold text-red-700">エラー箇所</div>
            <div class="mt-2 space-y-1 font-mono text-xs">
              <div v-for="(line, index) in editorLines" :key="`${index}-${line}`"
                class="flex gap-3 rounded px-2 py-1"
                :class="errorLine === index + 1 ? 'bg-red-100 text-red-700' : 'opacity-45'">
                <span class="w-6 text-right">@{{ index + 1 }}</span>
                <span class="flex-1 whitespace-pre-wrap break-all">@{{ line || ' ' }}</span>
              </div>
            </div>
            <div class="mt-2 text-[11px] text-red-700">
              Line @{{ errorLine }}<span v-if="errorColumn"> / Col @{{ errorColumn }}</span>
            </div>
          </div>

          <div class="mt-3">
            <div class="text-xs font-semibold opacity-60 mb-2">式サンプル</div>
            <div class="flex flex-wrap gap-2">
            <button v-for="snippet in snippets" :key="snippet.label" @click="expr = snippet.value"
              class="px-3 py-1.5 rounded border border-[var(--color-border)] text-xs hover:bg-[var(--color-primary)] hover:text-white transition-colors">
              @{{ snippet.label }}
            </button>
            </div>
          </div>
        </div>

        <div class="relative">
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between gap-2 mb-2">
              <div class="text-xs font-semibold opacity-60">よく使う式</div>
              <button @click="functionCatalogOpen = !functionCatalogOpen"
                class="px-2.5 py-1 rounded border border-[var(--color-border)] text-[11px] hover:border-[var(--color-primary)]">
                @{{ functionCatalogOpen ? '式一覧を閉じる' : '式一覧' }}
              </button>
            </div>
            <div class="space-y-2 text-xs">
              <div v-for="item in presetItems" :key="item.name" class="rounded border border-[var(--color-border)] p-2">
                <div class="font-mono font-semibold">@{{ item.name }}</div>
                <div class="opacity-60 mt-1">@{{ item.desc }}</div>
              </div>
            </div>
          </div>

          <div v-if="functionCatalogOpen"
            class="absolute inset-0 z-10 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3 shadow-xl">
            <div class="flex items-center justify-between gap-2 mb-3">
              <div class="text-xs font-semibold opacity-60">関数一覧</div>
              <button @click="functionCatalogOpen = false"
                class="px-2.5 py-1 rounded border border-[var(--color-border)] text-[11px] hover:border-[var(--color-primary)]">
                閉じる
              </button>
            </div>
            <div class="flex flex-wrap gap-2 mb-3">
              <button v-for="group in functionGroups" :key="group.key" @click="activeFunctionGroup = group.key"
                class="px-2.5 py-1 rounded border text-[11px]"
                :class="activeFunctionGroup === group.key ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)] opacity-70'">
                @{{ group.label }}
              </button>
            </div>
            <div class="space-y-2 text-xs max-h-[280px] overflow-y-auto">
              <div v-for="item in activeFunctionItems" :key="item.name" class="rounded border border-[var(--color-border)] p-2">
                <div class="font-mono font-semibold break-all">@{{ item.name }}</div>
                <div class="opacity-60 mt-1">@{{ item.desc }}</div>
                <div class="mt-1 font-mono opacity-50 break-all">@{{ item.example }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap gap-3">
        <button @click="saveCurrentToHistory" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">履歴へ保存</button>
        <button @click="copyResult" class="px-4 py-2 rounded border border-[var(--color-border)] text-sm">
          @{{ copied ? 'コピー済み' : '結果をコピー' }}
        </button>
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
          <div v-if="!isComplexResult" class="text-xs opacity-60 mt-1">工学表記: @{{ engResult }}</div>
        </div>

        <div v-if="isComplexResult" class="mt-4 space-y-2">
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="text-[11px] opacity-60">Cartesian</div>
            <div class="font-mono text-sm mt-1 break-all">@{{ complexCartesian }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="flex items-center justify-between gap-2">
              <div class="text-[11px] opacity-60">Polar</div>
              <div class="flex gap-1">
                <button @click="angleUnit = 'deg'" class="px-2 py-0.5 rounded text-[11px] border"
                  :class="angleUnit === 'deg' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)] opacity-60'">deg</button>
                <button @click="angleUnit = 'rad'" class="px-2 py-0.5 rounded text-[11px] border"
                  :class="angleUnit === 'rad' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)] opacity-60'">rad</button>
              </div>
            </div>
            <div class="font-mono text-sm mt-1 break-all">@{{ complexPolarValue }}</div>
          </div>
        </div>

        <div v-else class="mt-4 space-y-2">
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="flex items-center justify-between gap-3">
              <div class="text-[11px] opacity-60">進数表示設定</div>
              <div class="flex items-center gap-2">
                <select v-model.number="bitWidth" class="input-text px-2 py-1 text-xs min-w-20">
                  <option v-for="width in bitWidthOptions" :key="width" :value="width">@{{ width }}bit</option>
                </select>
                <select v-model="signedMode" class="input-text px-2 py-1 text-xs min-w-24">
                  <option value="unsigned">unsigned</option>
                  <option value="signed">signed</option>
                </select>
              </div>
            </div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="text-[11px] opacity-60">ENG</div>
            <div class="font-mono text-sm mt-1 break-all">@{{ engResult }}</div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
              <div class="text-[11px] opacity-60">HEX</div>
              <div class="font-mono text-sm mt-1 break-all">@{{ hexResult }}</div>
            </div>
            <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
              <div class="text-[11px] opacity-60">DEC</div>
              <div class="font-mono text-sm mt-1 break-all">@{{ decDisplayResult }}</div>
            </div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="text-[11px] opacity-60">OCT</div>
            <div class="font-mono text-sm mt-1 break-all">@{{ octResult }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
            <div class="text-[11px] opacity-60">BIN</div>
            <div class="font-mono text-sm mt-1 break-all">@{{ binResult }}</div>
          </div>
        </div>

        <div class="mt-4 rounded border border-[var(--color-border)] p-3 bg-[var(--color-bg)]">
          <div class="text-xs font-semibold opacity-60 mb-2">評価スコープ</div>
          <div v-if="scopeEntries.length" class="space-y-1 text-xs font-mono">
            <div v-for="[key, value] in scopeEntries" :key="key" class="flex justify-between gap-3">
              <span>@{{ key }}</span>
              <span class="break-all text-right">@{{ formatValue(value) }}</span>
            </div>
          </div>
          <div v-else class="text-xs opacity-40">変数定義なし</div>
        </div>

      </template>
    </section>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => 'エンジニア電卓', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
