<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ネットワーク探索 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => 'ネットワーク探索'])
<div id="app" data-page="resistance-calc" class="px-4 py-4 sm:px-6 sm:py-6 max-w-7xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'ネットワーク探索', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">🔌 抵抗/容量ネットワーク探索</h1>
  </header>

  <div class="mb-5 flex flex-wrap gap-2">
    <button @click="activeMode = 'network'"
      class="px-4 py-2 rounded-xl border text-sm"
      :class="activeMode === 'network' ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'border-[var(--color-border)]'">
      ネットワーク探索
    </button>
    <button @click="activeMode = 'variable'"
      class="px-4 py-2 rounded-xl border text-sm"
      :class="activeMode === 'variable' ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'border-[var(--color-border)]'">
      可変抵抗 + 固定抵抗
    </button>
  </div>

  <div v-if="activeMode === 'network'" class="flex gap-6" style="min-height: 70vh">

    <!-- 左: 条件入力 -->
    <div class="w-80 flex-shrink-0 space-y-5">

      <!-- 部品種別 -->
      <div>
        <label class="block text-xs font-semibold opacity-60 uppercase tracking-wide mb-2">初期入力例</label>
        <div class="grid gap-2">
          <button v-for="preset in presets" :key="preset.label" @click="applyPreset(preset)"
            class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] px-3 py-2 text-left text-xs hover:border-[var(--color-primary)]">
            @{{ preset.label }}
          </button>
        </div>
      </div>

      <!-- 部品種別 -->
      <div>
        <label class="block text-xs font-semibold opacity-60 uppercase tracking-wide mb-2">部品種別</label>
        <div class="flex rounded-lg overflow-hidden border border-[var(--color-border)]">
          <button v-for="t in [{v:'R',l:'抵抗'},{v:'C',l:'コンデンサ'},{v:'divider',l:'分圧比'}]" :key="t.v"
            @click="form.part_type = t.v"
            :class="form.part_type === t.v ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-card-odd)] hover:opacity-80'"
            class="flex-1 py-2 text-sm font-medium transition-colors">
            @{{ t.l }}
          </button>
        </div>
      </div>

      <!-- 目標値 -->
      <div>
        <label class="block text-xs font-semibold opacity-60 uppercase tracking-wide mb-1">目標値</label>
        <input v-model="form.target_raw" type="text" :placeholder="targetHint"
          @keyup.enter="search"
          :class="form.target_raw && !targetValid ? 'border-red-400' : 'border-[var(--color-border)]'"
          class="w-full border rounded-lg px-3 py-2 text-sm bg-[var(--color-card-odd)] focus:border-[var(--color-primary)] outline-none" />
        <p class="text-xs opacity-50 mt-1">@{{ targetHint }}</p>
        <p v-if="form.part_type === 'divider'" class="text-xs opacity-50">分圧比: 0〜1 または 0%〜100% で入力</p>
      </div>

      <!-- 許容誤差 -->
      <div>
        <label class="block text-xs font-semibold opacity-60 uppercase tracking-wide mb-1">
          許容誤差: @{{ form.tolerance_pct }}%
        </label>
        <input v-model.number="form.tolerance_pct" type="range" min="0.1" max="20" step="0.1"
          class="w-full accent-[var(--color-primary)]" />
        <div class="flex justify-between text-xs opacity-40 mt-0.5">
          <span>0.1%</span><span>20%</span>
        </div>
      </div>

      <!-- E系列 -->
      <div>
        <label class="block text-xs font-semibold opacity-60 uppercase tracking-wide mb-2">E系列</label>
        <div class="flex flex-wrap gap-1">
          <button v-for="s in ['E6','E12','E24','E48','E96','custom']" :key="s"
            @click="form.series = s"
            :class="form.series === s ? 'bg-[var(--color-primary)] text-white' : 'border border-[var(--color-border)] hover:opacity-80'"
            class="px-2.5 py-1 text-xs rounded-md font-medium transition-colors">
            @{{ s }}
          </button>
        </div>
        <!-- カスタム値入力 -->
        <textarea v-if="form.series === 'custom'" v-model="form.custom_values"
          placeholder="値をカンマ区切りで入力（例: 100, 220, 470, 1k, 2.2k）"
          rows="3" class="w-full mt-2 border border-[var(--color-border)] rounded-lg px-3 py-2 text-xs bg-[var(--color-card-odd)] focus:border-[var(--color-primary)] outline-none resize-none" />
      </div>

      <!-- 回路種別 -->
      <div v-if="form.part_type !== 'divider'">
        <label class="block text-xs font-semibold opacity-60 uppercase tracking-wide mb-2">探索回路種別</label>
        <div class="space-y-1">
          <label v-for="t in [{v:'series',l:'直列'},{v:'parallel',l:'並列'},{v:'mixed',l:'直並列混在'}]" :key="t.v"
            class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" :value="t.v" :checked="form.circuit_types.includes(t.v)"
              @change="toggleCircuitType(t.v)" class="accent-[var(--color-primary)]" />
            <span class="text-sm">@{{ t.l }}</span>
          </label>
        </div>
      </div>

      <!-- 素子数 -->
      <div>
        <label class="block text-xs font-semibold opacity-60 uppercase tracking-wide mb-2">素子数</label>
        <div class="flex items-center gap-3">
          <div>
            <span class="text-xs opacity-60">最小</span>
            <select v-model.number="form.min_elements"
              class="ml-1 border border-[var(--color-border)] rounded px-2 py-1 text-sm bg-[var(--color-card-odd)]">
              <option v-for="n in 4" :key="n" :value="n">@{{ n }}</option>
            </select>
          </div>
          <span class="opacity-40">〜</span>
          <div>
            <span class="text-xs opacity-60">最大</span>
            <select v-model.number="form.max_elements"
              class="ml-1 border border-[var(--color-border)] rounded px-2 py-1 text-sm bg-[var(--color-card-odd)]">
              <option v-for="n in 4" :key="n" :value="n">@{{ n }}</option>
            </select>
          </div>
        </div>
        <p class="text-xs opacity-40 mt-1">※ 3素子以上は計算時間が増加します</p>
      </div>

      <div v-if="form.part_type === 'divider'" class="space-y-2">
        <label class="block text-xs font-semibold opacity-60 uppercase tracking-wide mb-1">分圧総抵抗範囲</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <input v-model="form.total_res_min_raw" type="text" placeholder="最小 例: 1k"
            class="w-full border rounded-lg px-3 py-2 text-sm bg-[var(--color-card-odd)] border-[var(--color-border)] outline-none" />
          <input v-model="form.total_res_max_raw" type="text" placeholder="最大 例: 100k"
            class="w-full border rounded-lg px-3 py-2 text-sm bg-[var(--color-card-odd)] border-[var(--color-border)] outline-none" />
        </div>
      </div>

      <!-- 在庫限定 -->
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" v-model="form.inventory_only" class="accent-[var(--color-primary)]" />
        <span class="text-sm">在庫がある部品のみ対象</span>
      </label>

      <!-- 探索ボタン -->
      <button @click="search" :disabled="!targetValid || searching"
        class="w-full btn-primary py-3 rounded-xl font-bold text-base disabled:opacity-40 transition-opacity">
        @{{ searching ? '探索中...' : '🔍 探索開始' }}
      </button>

      <p v-if="error" class="text-sm text-red-500">@{{ error }}</p>
    </div>

    <!-- 右: 結果 -->
    <div class="flex-1 min-w-0">

      <!-- 結果ヘッダ -->
      <div v-if="results.length > 0 || elapsedMs !== null" class="mb-4 flex items-center justify-between">
        <div>
          <span class="font-bold text-lg">@{{ results.length }} 件</span>
          <span class="text-sm opacity-60 ml-2">候補が見つかりました</span>
          <span v-if="truncated" class="text-xs text-amber-600 ml-2">（上位50件を表示）</span>
        </div>
        <span v-if="elapsedMs !== null" class="text-xs opacity-40">@{{ elapsedMs }}ms</span>
      </div>

      <!-- 候補カード一覧 -->
      <div class="space-y-2">
        <div v-for="(c, idx) in results" :key="idx"
          class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-xl p-4">
          <div class="flex items-start justify-between gap-4">
            <!-- 回路表現 -->
            <div class="flex-1 min-w-0">
              <div class="font-mono text-sm font-medium break-all">@{{ c.expression }}</div>
              <div class="text-sm opacity-70 mt-1">合成値: <span class="font-mono">@{{ c.actual_display }}</span></div>
              <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                <!-- 素子数バッジ -->
                <span class="text-xs bg-[var(--color-card-even)] px-2 py-0.5 rounded">
                  @{{ c.elements_count }}素子
                </span>
                <!-- 回路種別バッジ -->
                <span class="text-xs bg-[var(--color-card-even)] px-2 py-0.5 rounded">
                  @{{ c.topology_label || circuitTypeLabel(c.circuit_type) }}
                </span>
                <!-- 在庫バッジ -->
                <span v-if="c.from_inventory" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">
                  在庫あり
                </span>
                <span v-if="c.total_display" class="text-xs bg-[var(--color-card-even)] px-2 py-0.5 rounded">
                  総抵抗 @{{ c.total_display }}
                </span>
              </div>
              <div v-if="c.parts?.length" class="mt-2 flex flex-wrap gap-2">
                <template v-for="(part, pidx) in c.parts" :key="`${idx}-${pidx}`">
                  <a v-if="part.url" :href="part.url" class="text-xs px-2 py-1 rounded border border-[var(--color-border)] hover:border-[var(--color-primary)] no-underline">
                    @{{ part.label }}
                  </a>
                  <span v-else class="text-xs px-2 py-1 rounded border border-[var(--color-border)]">
                    @{{ part.label }}
                  </span>
                </template>
              </div>
            </div>
            <!-- 誤差 -->
            <div class="text-right flex-shrink-0">
              <div :class="errorClass(c.error_pct)" class="font-bold text-lg">
                @{{ c.error_pct.toFixed(2) }}%
              </div>
              <div class="text-xs opacity-50">誤差</div>
            </div>
          </div>
        </div>

        <!-- 結果なし -->
        <div v-if="!searching && results.length === 0 && elapsedMs !== null"
          class="text-center py-16 opacity-40">
          <div class="text-4xl mb-3">🔍</div>
          <p class="text-sm">条件に合う組み合わせが見つかりませんでした。</p>
          <p class="text-xs mt-1">許容誤差を広げるか素子数を増やしてみてください。</p>
        </div>

        <!-- 初期状態 -->
        <div v-if="elapsedMs === null && !searching"
          class="rounded-3xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-8">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl bg-[var(--color-bg)] p-4">
              <div class="text-xs uppercase tracking-[0.18em] opacity-50">入力</div>
              <div class="mt-2 text-sm font-semibold">目標値とE系列を選ぶ</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-bg)] p-4">
              <div class="text-xs uppercase tracking-[0.18em] opacity-50">探索</div>
              <div class="mt-2 text-sm font-semibold">回路方式と素子数を絞る</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-bg)] p-4">
              <div class="text-xs uppercase tracking-[0.18em] opacity-50">比較</div>
              <div class="mt-2 text-sm font-semibold">誤差・回路図・在庫リンクを見る</div>
            </div>
          </div>
        </div>

        <!-- ローディング -->
        <div v-if="searching" class="text-center py-16 opacity-50">
          <p class="text-sm">探索中...</p>
        </div>
      </div>
    </div>

  </div>

  <section v-if="activeMode === 'variable'" class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
    <div class="rounded-3xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-5 space-y-4">
      <label class="block">
        <span class="block text-xs font-semibold opacity-60 mb-1">総抵抗値</span>
        <input v-model="variable.total_raw" class="input-text w-full font-mono" placeholder="例: 10k" />
      </label>
      <label class="block">
        <span class="block text-xs font-semibold opacity-60 mb-1">可変幅</span>
        <input v-model="variable.span_raw" class="input-text w-full font-mono" placeholder="例: 20 または 2k" />
      </label>
      <div>
        <span class="block text-xs font-semibold opacity-60 mb-1">可変幅指定</span>
        <div class="flex rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-1">
          <button @click="variable.span_mode = 'percent'" class="flex-1 rounded-lg px-3 py-2 text-sm"
            :class="variable.span_mode === 'percent' ? 'bg-[var(--color-primary)] text-white' : ''">%</button>
          <button @click="variable.span_mode = 'ohm'" class="flex-1 rounded-lg px-3 py-2 text-sm"
            :class="variable.span_mode === 'ohm' ? 'bg-[var(--color-primary)] text-white' : ''">Ω</button>
        </div>
      </div>
      <div>
        <span class="block text-xs font-semibold opacity-60 mb-1">方式</span>
        <select v-model="variable.circuit" class="input-text w-full">
          <option value="series">固定抵抗 + 可変抵抗（直列）</option>
          <option value="parallel">固定抵抗 ∥ 可変抵抗（並列目安）</option>
        </select>
      </div>
    </div>

    <div class="rounded-3xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-5">
      <div class="grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl bg-[var(--color-bg)] p-4">
          <div class="text-xs uppercase tracking-[0.18em] opacity-50">可変抵抗</div>
          <div class="mt-2 font-mono text-2xl font-bold">@{{ variableResult.potDisplay }}</div>
        </div>
        <div class="rounded-2xl bg-[var(--color-bg)] p-4">
          <div class="text-xs uppercase tracking-[0.18em] opacity-50">固定抵抗</div>
          <div class="mt-2 font-mono text-2xl font-bold">@{{ variableResult.fixedDisplay }}</div>
        </div>
        <div class="rounded-2xl bg-[var(--color-bg)] p-4 md:col-span-2">
          <div class="text-xs uppercase tracking-[0.18em] opacity-50">可変範囲</div>
          <div class="mt-2 font-mono text-xl font-bold">@{{ variableResult.rangeDisplay }}</div>
        </div>
      </div>
      <div class="mt-5 rounded-2xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4 font-mono text-sm">
        <div v-if="variable.circuit === 'series'">[固定抵抗] -- [可変抵抗] で総抵抗と可変幅を作る</div>
        <div v-else>[固定抵抗] ∥ [可変抵抗] の簡易目安。実機では端点抵抗と負荷を確認</div>
      </div>
    </div>
  </section>
  @include('partials.app-breadcrumbs', ['items' => [['label' => 'ネットワーク探索', 'current' => true]], 'class' => 'mt-6'])
</div>
</body>
</html>
