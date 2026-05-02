<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>抵抗/容量ネットワーク探索 - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '抵抗/容量ネットワーク探索'])
<div id="app" data-page="resistance-calc" class="px-4 py-4 sm:px-6 sm:py-6 max-w-7xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '抵抗/容量ネットワーク探索', 'current' => true]]])

  <header class="mb-5 flex flex-wrap items-start justify-between gap-4 border-b border-[var(--color-border)] pb-4">
    <div>
      <h1 class="text-2xl font-bold">抵抗/容量ネットワーク探索</h1>
      <div class="mt-2 flex flex-wrap gap-2 text-xs">
        <span class="rounded border border-[var(--color-border)] px-2 py-1">直列</span>
        <span class="rounded border border-[var(--color-border)] px-2 py-1">並列</span>
        <span class="rounded border border-[var(--color-border)] px-2 py-1">直並列混在</span>
        <span class="rounded border border-[var(--color-border)] px-2 py-1">分圧</span>
        <span class="rounded border border-[var(--color-border)] px-2 py-1">VR調整</span>
        <span class="rounded border border-[var(--color-border)] px-2 py-1">在庫値</span>
      </div>
    </div>
    <div class="flex rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-1">
      <button v-for="mode in modeOptions" :key="mode.value" @click="setActiveMode(mode.value)"
        class="rounded-md px-3 py-2 text-sm font-semibold"
        :class="activeMode === mode.value ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'">
        @{{ mode.label }}
      </button>
    </div>
  </header>

  <section v-if="activeMode === 'network' || (activeMode === 'divider' && form.divider_mode === 'fixed')" class="grid gap-5 lg:grid-cols-[360px_minmax(0,1fr)]">
    <aside class="space-y-4">
      <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
          <h2 class="text-sm font-bold">探索条件</h2>
          <span class="text-xs opacity-60">@{{ partTypeLabel }}</span>
        </div>

        <div v-if="activeMode === 'network'" class="mb-4 grid grid-cols-2 gap-2">
          <button v-for="type in partTypeOptions" :key="type.value" @click="setPartType(type.value)"
            class="rounded border px-3 py-2 text-sm font-semibold"
            :class="form.part_type === type.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)] bg-[var(--color-bg)]'">
            @{{ type.label }}
          </button>
        </div>

        <div class="grid gap-3">
          <div v-if="activeMode === 'divider'" class="grid grid-cols-2 gap-2">
            <button v-for="mode in dividerModeOptions" :key="mode.value" @click="setDividerMode(mode.value)"
              class="rounded border px-3 py-2 text-sm font-semibold"
              :class="form.divider_mode === mode.value ? 'border-[var(--color-primary)] bg-[var(--color-card-even)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
              @{{ mode.label }}
            </button>
          </div>

          <div v-if="activeMode === 'divider'" class="grid grid-cols-2 gap-2">
            <button v-for="mode in dividerTargetModeOptions" :key="mode.value" @click="form.divider_target_mode = mode.value"
              class="rounded border px-3 py-2 text-sm"
              :class="form.divider_target_mode === mode.value ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
              @{{ mode.label }}
            </button>
          </div>

          <label v-if="activeMode !== 'divider' || form.divider_target_mode === 'ratio'" class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">@{{ activeMode === 'divider' ? '目標比率' : '目標値' }}</span>
            <input v-model="form.target_raw" type="text" :placeholder="targetHint" @keyup.enter="search"
              class="input-text w-full font-mono"
              :class="form.target_raw && !targetValid ? 'border-red-400' : ''" />
          </label>

          <label v-if="activeMode === 'divider' && form.divider_target_mode === 'ratio'" class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">入力電圧</span>
            <input v-model="form.input_voltage_raw" class="input-text w-full font-mono" placeholder="3.3" @keyup.enter="search" />
          </label>

          <div v-if="activeMode === 'divider' && form.divider_target_mode === 'voltage'" class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">入力電圧</span>
              <input v-model="form.input_voltage_raw" class="input-text w-full font-mono" placeholder="3.3" @keyup.enter="search" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">出力電圧</span>
              <input v-model="form.output_voltage_raw" class="input-text w-full font-mono" placeholder="2.5" @keyup.enter="search" />
            </label>
            <div class="col-span-2 rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2 text-xs">
              <span class="opacity-55">換算比率</span>
              <span class="ml-2 font-mono font-semibold">@{{ dividerVoltageTarget.valid ? `${(dividerVoltageTarget.ratio * 100).toPrecision(5)}%` : '-' }}</span>
            </div>
          </div>

          <div v-if="activeMode === 'divider'" class="grid gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="grid grid-cols-2 gap-2">
              <button v-for="loadType in loadTypeOptions" :key="`divider-load-${loadType.value}`" @click="form.load_type = loadType.value"
                class="rounded border px-3 py-2 text-sm"
                :class="form.load_type === loadType.value ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
                @{{ loadType.label }}
              </button>
            </div>
            <label v-if="form.load_type === 'resistance'" class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">負荷抵抗</span>
              <div class="flex gap-2">
                <input v-model="form.load_resistance_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="∞ / 10k" @keyup.enter="search" />
                <button @click="setLoadResistanceInfinite(form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm hover:border-[var(--color-primary)]">∞</button>
              </div>
            </label>
            <div v-if="form.load_type === 'current'" class="grid gap-3">
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">負荷電流</span>
                <div class="flex gap-2">
                  <input v-model="form.load_current_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="0 / 1mA" @keyup.enter="search" />
                  <button @click="setLoadCurrentZero(form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm hover:border-[var(--color-primary)]">0A</button>
                </div>
              </label>
            </div>
            <div class="text-xs opacity-60">負荷: @{{ dividerLoadConfig.display }}</div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">@{{ activeMode === 'network' ? '探索許容誤差' : '許容誤差' }}</span>
              <input v-model.number="form.tolerance_pct" type="number" min="0.001" max="50" step="0.1"
                class="input-text w-full font-mono" />
            </label>
            <label v-if="activeMode === 'network'" class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">採用素子許容差</span>
              <input v-model.number="form.element_tolerance_pct" name="element_tolerance_pct" type="number" min="0" max="100" step="0.1"
                class="input-text w-full font-mono" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">E系列</span>
              <select v-model="form.series" class="input-text w-full">
                <option v-for="series in seriesOptions" :key="series" :value="series">@{{ series }}</option>
              </select>
            </label>
          </div>
          <div v-if="activeMode === 'divider'" class="grid grid-cols-2 gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">R1許容差</span>
              <input v-model.number="form.divider_upper_tolerance_pct" type="number" min="0" max="100" step="0.1"
                class="input-text w-full font-mono" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">R2許容差</span>
              <input v-model.number="form.divider_lower_tolerance_pct" type="number" min="0" max="100" step="0.1"
                class="input-text w-full font-mono" />
            </label>
          </div>

          <textarea v-if="form.series === 'custom'" v-model="form.custom_values"
            rows="4"
            class="input-text w-full resize-none font-mono text-sm"
            placeholder="100, 220, 470, 1k, 2.2k"></textarea>

          <div v-if="activeMode === 'network'" class="grid grid-cols-3 gap-2">
            <button v-for="type in circuitOptions" :key="type.value" @click="toggleCircuitType(type.value)"
              class="rounded border px-2 py-2 text-sm"
              :class="form.circuit_types.includes(type.value) ? 'border-[var(--color-primary)] text-[var(--color-primary)] bg-[var(--color-card-even)]' : 'border-[var(--color-border)] bg-[var(--color-bg)]'">
              @{{ type.label }}
            </button>
          </div>

          <div v-if="activeMode === 'network'" class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">最小素子数</span>
              <select v-model.number="form.min_elements" class="input-text w-full">
                <option v-for="n in 4" :key="`min-${n}`" :value="n">@{{ n }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">最大素子数</span>
              <select v-model.number="form.max_elements" class="input-text w-full">
                <option v-for="n in 4" :key="`max-${n}`" :value="n">@{{ n }}</option>
              </select>
            </label>
          </div>

          <div v-if="activeMode === 'divider'" class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">総抵抗 min</span>
              <input v-model="form.total_res_min_raw" class="input-text w-full font-mono" placeholder="1k" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">総抵抗 max</span>
              <input v-model="form.total_res_max_raw" class="input-text w-full font-mono" placeholder="100k" />
            </label>
          </div>

          <label v-if="activeMode === 'network'" class="flex items-center justify-between gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
            <span class="text-sm font-semibold">在庫値のみ</span>
            <input type="checkbox" v-model="form.inventory_only" class="h-5 w-5 accent-[var(--color-primary)]" />
          </label>

          <button @click="search" :disabled="!formValid || searching"
            class="btn-primary rounded-lg px-4 py-3 text-sm font-bold disabled:opacity-40">
            @{{ searching ? '探索中' : '探索' }}
          </button>

          <div v-if="error || !formValid" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
            @{{ error || validationMessage }}
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <div class="mb-3 text-sm font-bold">プリセット</div>
        <div class="grid gap-2">
          <button v-for="preset in presets" :key="preset.label" @click="applyPreset(preset)"
            class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2 text-left text-sm hover:border-[var(--color-primary)]">
            <span class="font-semibold">@{{ preset.label }}</span>
            <span class="ml-2 text-xs opacity-60">@{{ preset.meta }}</span>
          </button>
        </div>
      </div>
    </aside>

    <main class="min-w-0 space-y-4">
      <div class="grid gap-3 md:grid-cols-4">
        <div v-for="metric in statusMetrics" :key="metric.label" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
          <div class="text-xs opacity-55">@{{ metric.label }}</div>
          <div class="mt-1 truncate font-mono text-lg font-bold">@{{ metric.value }}</div>
        </div>
      </div>

      <div v-if="warnings.length || nextActions.length" class="grid gap-2">
        <div v-for="message in warnings" :key="`warn-${message}`" class="rounded border border-amber-400 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          @{{ message }}
        </div>
        <div v-for="message in nextActions" :key="`next-${message}`" class="rounded border border-[var(--color-border)] bg-[var(--color-card-even)] px-3 py-2 text-sm">
          @{{ message }}
        </div>
      </div>

      <section v-if="comparedCandidates.length" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
          <h2 class="text-sm font-bold">比較トレイ</h2>
          <button @click="clearCompare" class="text-xs link-text">クリア</button>
        </div>
        <div class="grid gap-3 md:grid-cols-3">
          <div v-for="candidate in comparedCandidates" :key="candidate.id" class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between gap-3">
              <span class="text-xs opacity-60">@{{ candidate.topology_label || circuitTypeLabel(candidate.circuit_type) }}</span>
              <span class="font-mono text-sm font-bold" :class="errorClass(candidate.error_pct)">@{{ candidate.error_display }}</span>
            </div>
            <div class="mt-2 font-mono text-sm">@{{ candidate.actual_display }}</div>
            <div class="mt-1 truncate font-mono text-xs opacity-70">@{{ candidate.expression }}</div>
          </div>
        </div>
      </section>

      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-border)] px-4 py-3">
          <div>
            <h2 class="text-sm font-bold">候補</h2>
            <div class="text-xs opacity-60">@{{ summaryText }}</div>
          </div>
          <div class="flex items-center gap-2 text-xs">
            <span v-if="truncated" class="rounded border border-amber-400 px-2 py-1 text-amber-700">上限到達</span>
            <span v-if="elapsedMs !== null" class="rounded border border-[var(--color-border)] px-2 py-1">@{{ elapsedMs }}ms</span>
          </div>
        </div>

        <div v-if="searching" class="grid min-h-80 place-items-center text-sm opacity-60">探索中</div>
        <div v-else-if="elapsedMs === null" class="grid min-h-80 place-items-center text-sm opacity-45">候補待ち</div>
        <div v-else-if="results.length === 0" class="grid min-h-80 place-items-center text-sm opacity-60">該当候補なし</div>

        <div v-else class="divide-y divide-[var(--color-border)]">
          <article v-for="candidate in rankedResults" :key="candidate.id" class="grid gap-4 p-4 xl:grid-cols-[minmax(0,1fr)_260px]">
            <div class="min-w-0">
              <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="rounded bg-[var(--color-card-even)] px-2 py-1 text-xs font-semibold">#@{{ candidate.rank }}</span>
                <span class="rounded bg-[var(--color-card-even)] px-2 py-1 text-xs">@{{ candidate.topology_label || circuitTypeLabel(candidate.circuit_type) }}</span>
                <span class="rounded bg-[var(--color-card-even)] px-2 py-1 text-xs">@{{ candidate.elements_count }}素子</span>
                <span v-if="candidate.from_inventory" class="rounded border border-[var(--color-tag-ok)] px-2 py-1 text-xs text-[var(--color-tag-ok)]">在庫</span>
                <button @click="toggleCompare(candidate)"
                  class="ml-auto rounded border px-2 py-1 text-xs"
                  :class="isCompared(candidate) ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
                  @{{ isCompared(candidate) ? '比較中' : '比較' }}
                </button>
              </div>

              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                <div v-if="candidate.circuit_type === 'divider'" class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-sm">
                  <div class="rounded border border-[var(--color-border)] px-3 py-2 text-center">
                    <div class="text-xs opacity-50">R1</div>
                    <div class="font-mono">@{{ candidate.parts?.[0]?.label }}</div>
                  </div>
                  <div class="font-mono text-xs opacity-60">Vout</div>
                  <div class="rounded border border-[var(--color-border)] px-3 py-2 text-center">
                    <div class="text-xs opacity-50">R2</div>
                    <div class="font-mono">@{{ candidate.parts?.[1]?.label }}</div>
                  </div>
                </div>
                <div v-else-if="candidate.circuit_type === 'series'" class="flex flex-wrap items-center gap-2">
                  <template v-for="(part, partIndex) in candidate.parts" :key="`${candidate.id}-s-${partIndex}`">
                    <span v-if="partIndex > 0" class="h-px w-5 bg-[var(--color-border)]"></span>
                    <span class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm">@{{ part.label }}</span>
                  </template>
                </div>
                <div v-else-if="candidate.circuit_type === 'parallel'" class="grid gap-2">
                  <div v-for="(part, partIndex) in candidate.parts" :key="`${candidate.id}-p-${partIndex}`" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm">
                    @{{ part.label }}
                  </div>
                </div>
                <div v-else class="grid gap-2">
                  <div class="text-xs font-semibold opacity-60">混在トポロジ</div>
                  <div class="flex flex-wrap items-center gap-2">
                    <span v-for="(token, tokenIndex) in topologyTokens(candidate.expression)" :key="`${candidate.id}-token-${tokenIndex}`"
                      class="rounded px-2 py-1 font-mono text-sm"
                      :class="token.type === 'part' ? 'border border-[var(--color-border)] bg-[var(--color-card-odd)]' : 'bg-[var(--color-card-even)] opacity-75'">
                      @{{ token.text }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <template v-for="(part, partIndex) in candidate.parts" :key="`${candidate.id}-part-${partIndex}`">
                  <a v-if="part.url" :href="part.url" class="rounded border border-[var(--color-border)] px-2 py-1 text-xs no-underline hover:border-[var(--color-primary)]">
                    <span class="opacity-50">@{{ part.role }}</span>
                    <span class="ml-1">@{{ part.label }}</span>
                    <span v-if="part.stock_quantity !== null" class="ml-1 opacity-50">在庫@{{ part.stock_quantity }}</span>
                  </a>
                  <span v-else class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">
                    <span class="opacity-50">@{{ part.role }}</span>
                    <span class="ml-1">@{{ part.label }}</span>
                  </span>
                </template>
              </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-4 xl:grid-cols-1">
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                <div class="text-xs opacity-50">合成値</div>
                <div class="mt-1 font-mono text-lg font-bold">@{{ candidate.actual_display }}</div>
                <div v-if="candidate.actual_output_display" class="mt-1 font-mono text-xs opacity-65">
                  @{{ candidate.actual_output_display }} / @{{ candidate.input_voltage_display }}
                </div>
              </div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                <div class="text-xs opacity-50">誤差</div>
                <div class="mt-1 font-mono text-lg font-bold" :class="errorClass(candidate.error_pct)">@{{ candidate.error_display }}</div>
                <div v-if="candidate.output_error_display" class="mt-1 font-mono text-xs opacity-65">
                  @{{ candidate.output_error_display }}
                </div>
              </div>
              <div v-if="candidate.divider_rss_range_display" class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                <div class="text-xs opacity-50">素子誤差範囲</div>
                <div class="mt-1 font-mono text-lg font-bold">@{{ candidate.divider_rss_range_display }}</div>
                <div class="mt-1 grid gap-1 font-mono text-xs opacity-70">
                  <span>@{{ candidate.divider_tolerance_display }}</span>
                  <span>RSS最大 @{{ candidate.divider_rss_max_error_display }} / @{{ candidate.divider_rss_max_error_pct_display }}</span>
                  <span>コーナー @{{ candidate.divider_corner_range_display }} / 最大 @{{ candidate.divider_corner_max_error_pct_display }}</span>
                </div>
              </div>
              <div v-if="candidate.rss_range_display || candidate.low_equivalent_display" class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                <div class="text-xs opacity-50">独立RSS目安</div>
                <div class="mt-1 font-mono text-lg font-bold">@{{ candidate.rss_range_display || candidate.tolerance_range_display || `${candidate.low_equivalent_display} 〜 ${candidate.high_equivalent_display}` }}</div>
                <div class="mt-1 grid gap-1 font-mono text-xs opacity-70">
                  <span>採用素子 ±@{{ candidate.element_tolerance_display }}</span>
                  <span v-if="candidate.rss_equivalent_spread_display">RSS幅 ±@{{ candidate.rss_equivalent_spread_display }} / @{{ candidate.rss_equivalent_spread_pct_display }}</span>
                  <span v-if="candidate.rss_max_target_deviation_display">最大偏差 @{{ candidate.rss_max_target_deviation_display_value }} / @{{ candidate.rss_max_target_deviation_display }}</span>
                  <span v-if="candidate.corner_range_display">コーナー @{{ candidate.corner_range_display }} / 最大 @{{ candidate.max_target_deviation_display }}</span>
                </div>
              </div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                <div class="text-xs opacity-50">@{{ candidate.circuit_type === 'divider' ? '総抵抗' : '差分' }}</div>
                <div class="mt-1 font-mono text-lg font-bold">@{{ candidate.total_display || candidate.error_abs_display }}</div>
                <div v-if="candidate.load_display" class="mt-1 font-mono text-xs opacity-65">
                  負荷 @{{ candidate.load_display }}
                </div>
              </div>
              <div v-if="candidate.circuit_type === 'divider' && candidate.source_current_display" class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                <div class="text-xs opacity-50">電流/電力</div>
                <div class="mt-1 font-mono text-lg font-bold">@{{ candidate.source_current_display }}</div>
                <div class="mt-1 grid gap-1 font-mono text-xs opacity-70">
                  <span>R1 @{{ candidate.upper_power_display }}</span>
                  <span>R2 @{{ candidate.lower_power_display }}</span>
                  <span>合計 @{{ candidate.resistor_power_display }}</span>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>
    </main>
  </section>

  <section v-if="activeMode === 'variable'" class="grid gap-5 lg:grid-cols-[360px_minmax(0,1fr)]">
    <aside class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
      <h2 class="mb-4 text-sm font-bold">可変抵抗 + 固定抵抗</h2>
      <div class="grid gap-3">
        <label class="block">
          <span class="mb-1 block text-xs font-semibold opacity-60">基準抵抗値</span>
          <input v-model="variable.reference_raw" class="input-text w-full font-mono" placeholder="10k" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs font-semibold opacity-60">可変幅</span>
          <input v-model="variable.span_raw" class="input-text w-full font-mono" placeholder="20 または 2k" />
        </label>
        <div class="grid grid-cols-2 gap-2">
          <button @click="variable.span_mode = 'percent'" class="rounded border px-3 py-2 text-sm"
            :class="variable.span_mode === 'percent' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">%</button>
          <button @click="variable.span_mode = 'ohm'" class="rounded border px-3 py-2 text-sm"
            :class="variable.span_mode === 'ohm' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">Ω</button>
        </div>
        <label class="block">
          <span class="mb-1 block text-xs font-semibold opacity-60">基準位置</span>
          <select v-model="variable.reference_position" class="input-text w-full">
            <option v-for="position in variableReferencePositionOptions" :key="position.value" :value="position.value">
              @{{ position.label }}
            </option>
          </select>
        </label>
        <select v-model="variable.circuit" class="input-text w-full">
          <option value="series">直列トリム</option>
          <option value="parallel">並列トリム</option>
        </select>
        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">固定抵抗ソース</span>
            <select v-model="variable.fixed_source" class="input-text w-full">
              <option v-for="source in variableFixedSourceOptions" :key="`fixed-${source}`" :value="source">@{{ source }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">VRソース</span>
            <select v-model="variable.pot_source" class="input-text w-full">
              <option v-for="source in variablePotSourceOptions" :key="`pot-${source}`" :value="source">
                @{{ source === 'vr-common' ? '標準VR値' : source }}
              </option>
            </select>
          </label>
        </div>
        <div class="grid grid-cols-2 gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">固定抵抗許容差</span>
            <input v-model.number="variable.fixed_tolerance_pct" type="number" min="0" max="100" step="0.1"
              class="input-text w-full font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">VR許容差</span>
            <input v-model.number="variable.pot_tolerance_pct" type="number" min="0" max="100" step="0.1"
              class="input-text w-full font-mono" />
          </label>
        </div>
        <textarea v-if="variable.fixed_source === 'custom'" v-model="variable.fixed_custom_values"
          rows="3"
          class="input-text w-full resize-none font-mono text-sm"
          placeholder="7.5k, 8.2k, 9.1k"></textarea>
        <textarea v-if="variable.pot_source === 'custom'" v-model="variable.pot_custom_values"
          rows="3"
          class="input-text w-full resize-none font-mono text-sm"
          placeholder="1k, 2k, 5k, 10k"></textarea>
      </div>
    </aside>

    <main class="space-y-4">
      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <h2 class="mb-3 text-sm font-bold">要求範囲</h2>
        <div class="grid gap-3 md:grid-cols-4">
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">基準抵抗値</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ variableResult.referenceDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">可変幅</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ variableResult.spanDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">要求下限</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ variableResult.requirementLowDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">要求上限</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ variableResult.requirementHighDisplay }}</div>
          </div>
        </div>
      </section>

      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
          <h2 class="text-sm font-bold">採用候補</h2>
          <span v-if="variableResult.bestCandidate"
            class="rounded border px-2 py-1 text-xs font-semibold"
            :class="variableStatusClass(variableResult.bestCandidate.status)">
            @{{ variableResult.bestCandidate.verdict }}
          </span>
        </div>
      <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-xs opacity-50">候補固定抵抗</div>
          <div class="mt-1 font-mono text-xl font-bold">@{{ variableResult.selectedFixedDisplay }}</div>
        </div>
        <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-xs opacity-50">候補VR</div>
          <div class="mt-1 font-mono text-xl font-bold">@{{ variableResult.selectedPotDisplay }}</div>
        </div>
        <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-xs opacity-50">候補下限</div>
          <div class="mt-1 font-mono text-xl font-bold">@{{ variableResult.selectedLowDisplay }}</div>
        </div>
        <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-xs opacity-50">候補上限</div>
          <div class="mt-1 font-mono text-xl font-bold">@{{ variableResult.selectedHighDisplay }}</div>
        </div>
      </div>
      <div v-if="variableResult.bestCandidate" class="mt-4 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
        <div class="font-mono text-sm">@{{ variableResult.bestCandidate.expression }}</div>
        <div class="mt-3 flex flex-wrap gap-2">
          <span v-for="tag in variableResult.bestCandidate.tags" :key="tag"
            class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">
            @{{ tag }}
          </span>
        </div>
        <div class="mt-3 grid gap-2 text-xs sm:grid-cols-3">
          <div class="rounded border border-[var(--color-border)] px-3 py-2">
            <span class="opacity-50">下限余裕</span>
            <span class="ml-2 font-mono">@{{ variableResult.bestCandidate.lowMarginDisplay }}</span>
          </div>
          <div class="rounded border border-[var(--color-border)] px-3 py-2">
            <span class="opacity-50">上限余裕</span>
            <span class="ml-2 font-mono">@{{ variableResult.bestCandidate.highMarginDisplay }}</span>
          </div>
          <div class="rounded border border-[var(--color-border)] px-3 py-2">
            <span class="opacity-50">範囲判定</span>
            <span class="ml-2 font-mono">@{{ variableResult.bestCandidate.rangeMarginDisplay }}</span>
          </div>
        </div>
        <div v-if="variableResult.bestCandidate.rssAdjustableRangeDisplay" class="mt-3 rounded border border-[var(--color-border)] px-3 py-2 text-xs">
          <div class="font-semibold opacity-60">素子誤差範囲</div>
          <div class="mt-1 font-mono text-sm font-bold">@{{ variableResult.bestCandidate.rssAdjustableRangeDisplay }}</div>
          <div class="mt-1 grid gap-1 font-mono opacity-70">
            <span>@{{ variableResult.bestCandidate.toleranceDisplay }}</span>
            <span>RSS下限 @{{ variableResult.bestCandidate.rssLowEndpointRangeDisplay }}</span>
            <span>RSS上限 @{{ variableResult.bestCandidate.rssHighEndpointRangeDisplay }}</span>
            <span>コーナー @{{ variableResult.bestCandidate.cornerAdjustableRangeDisplay }}</span>
          </div>
        </div>
      </div>
      <div v-if="!variableResult.valid" class="mt-3 rounded border border-amber-400 bg-amber-50 px-3 py-2 text-sm text-amber-800">
        入力値の組み合わせを確認してください
      </div>
      <div v-else-if="!variableResult.candidates.length" class="mt-3 rounded border border-amber-400 bg-amber-50 px-3 py-2 text-sm text-amber-800">
        候補値ソースを確認してください
      </div>
      </section>

      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <h2 class="mb-3 text-sm font-bold">候補一覧</h2>
        <div v-if="variableResult.candidates.length" class="grid gap-3">
          <article v-for="candidate in variableResult.candidates" :key="`${candidate.fixed}-${candidate.pot}-${candidate.low}`"
            class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded border px-2 py-1 text-xs font-semibold" :class="variableStatusClass(candidate.status)">
                @{{ candidate.verdict }}
              </span>
              <span class="font-mono text-sm font-bold">@{{ candidate.fixedDisplay }} @{{ candidate.operatorDisplay }} VR @{{ candidate.potDisplay }}</span>
              <span class="ml-auto font-mono text-xs">@{{ candidate.lowDisplay }} 〜 @{{ candidate.highDisplay }}</span>
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
              <span v-for="tag in candidate.tags" :key="`${candidate.fixed}-${candidate.pot}-${tag}`"
                class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">
                @{{ tag }}
              </span>
            </div>
          </article>
        </div>
        <div v-else class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-4 text-sm opacity-60">
          候補なし
        </div>
      </section>

      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <h2 class="mb-3 text-sm font-bold">理想値</h2>
        <div class="grid gap-3 md:grid-cols-4">
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">理想固定抵抗</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ variableResult.idealFixedDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">理想VR</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ variableResult.idealPotDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">理想下限</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ variableResult.idealLowDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">理想上限</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ variableResult.idealHighDisplay }}</div>
          </div>
        </div>
        <div class="mt-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3 font-mono text-sm">
          @{{ variableResult.expression }}
        </div>
      </section>
    </main>
  </section>

  <section v-if="activeMode === 'divider' && form.divider_mode === 'variable'" class="grid gap-5 lg:grid-cols-[380px_minmax(0,1fr)]">
    <aside class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-sm font-bold">分圧条件</h2>
        <span class="text-xs opacity-60">VR調整あり</span>
      </div>
      <div class="grid gap-3">
        <div class="grid grid-cols-2 gap-2">
          <button v-for="mode in dividerModeOptions" :key="`vr-mode-${mode.value}`" @click="setDividerMode(mode.value)"
            class="rounded border px-3 py-2 text-sm font-semibold"
            :class="form.divider_mode === mode.value ? 'border-[var(--color-primary)] bg-[var(--color-card-even)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
            @{{ mode.label }}
          </button>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <button v-for="mode in dividerTargetModeOptions" :key="`vr-target-${mode.value}`" @click="form.divider_target_mode = mode.value"
            class="rounded border px-3 py-2 text-sm"
            :class="form.divider_target_mode === mode.value ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
            @{{ mode.label }}
          </button>
        </div>

        <label class="block">
          <span class="mb-1 block text-xs font-semibold opacity-60">入力電圧</span>
          <input v-model="form.input_voltage_raw" class="input-text w-full font-mono" placeholder="5" />
        </label>
        <div v-if="form.divider_target_mode === 'voltage'" class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">出力下限</span>
            <input v-model="dividerVariable.output_low_raw" class="input-text w-full font-mono" placeholder="1" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">出力上限</span>
            <input v-model="dividerVariable.output_high_raw" class="input-text w-full font-mono" placeholder="3" />
          </label>
        </div>
        <div v-if="form.divider_target_mode === 'ratio'" class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">出力下限比率</span>
            <input v-model="dividerVariable.output_low_ratio_raw" class="input-text w-full font-mono" placeholder="20%" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">出力上限比率</span>
            <input v-model="dividerVariable.output_high_ratio_raw" class="input-text w-full font-mono" placeholder="60%" />
          </label>
        </div>
        <label class="block">
          <span class="mb-1 block text-xs font-semibold opacity-60">基準VR値</span>
          <input v-model="dividerVariable.nominal_pot_raw" class="input-text w-full font-mono" placeholder="10k" />
        </label>

        <div class="grid gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="grid grid-cols-2 gap-2">
            <button v-for="loadType in loadTypeOptions" :key="`vr-load-${loadType.value}`" @click="form.load_type = loadType.value"
              class="rounded border px-3 py-2 text-sm"
              :class="form.load_type === loadType.value ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
              @{{ loadType.label }}
            </button>
          </div>
          <label v-if="form.load_type === 'resistance'" class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">負荷抵抗</span>
            <div class="flex gap-2">
              <input v-model="form.load_resistance_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="∞ / 10k" />
              <button @click="setLoadResistanceInfinite(form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm hover:border-[var(--color-primary)]">∞</button>
            </div>
          </label>
          <label v-if="form.load_type === 'current'" class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">負荷電流</span>
            <div class="flex gap-2">
              <input v-model="form.load_current_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="0 / 1mA" />
              <button @click="setLoadCurrentZero(form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm hover:border-[var(--color-primary)]">0A</button>
            </div>
          </label>
          <div class="text-xs opacity-60">負荷: @{{ dividerVariableResult.loadDisplay }}</div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">許容誤差</span>
            <input v-model.number="form.tolerance_pct" type="number" min="0" max="50" step="0.1"
              class="input-text w-full font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">E系列</span>
            <select v-model="form.series" class="input-text w-full">
              <option v-for="series in seriesOptions" :key="`vr-series-${series}`" :value="series">@{{ series }}</option>
            </select>
          </label>
        </div>
        <div class="grid grid-cols-3 gap-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">R上許容差</span>
            <input v-model.number="dividerVariable.top_tolerance_pct" type="number" min="0" max="100" step="0.1"
              class="input-text w-full font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">VR許容差</span>
            <input v-model.number="dividerVariable.pot_tolerance_pct" type="number" min="0" max="100" step="0.1"
              class="input-text w-full font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">R下許容差</span>
            <input v-model.number="dividerVariable.bottom_tolerance_pct" type="number" min="0" max="100" step="0.1"
              class="input-text w-full font-mono" />
          </label>
        </div>
        <textarea v-if="form.series === 'custom'" v-model="form.custom_values"
          rows="3"
          class="input-text w-full resize-none font-mono text-sm"
          placeholder="5k, 10k, 15k"></textarea>
        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">総抵抗 min</span>
            <input v-model="form.total_res_min_raw" class="input-text w-full font-mono" placeholder="1k" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold opacity-60">総抵抗 max</span>
            <input v-model="form.total_res_max_raw" class="input-text w-full font-mono" placeholder="100k" />
          </label>
        </div>
        <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
          <div class="text-xs font-semibold opacity-60">採用VR</div>
          <div class="mt-1 font-mono text-sm font-bold">@{{ dividerVariableResult.nominalPotDisplay }}</div>
        </div>
      </div>
    </aside>

    <main class="space-y-4">
      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <h2 class="mb-3 text-sm font-bold">要求電圧</h2>
        <div class="grid gap-3 md:grid-cols-5">
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">入力</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.inputVoltageDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">出力下限</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.outputLowDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">出力上限</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.outputHighDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">比率範囲</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.ratioLowDisplay }}〜@{{ dividerVariableResult.ratioHighDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">負荷</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.loadDisplay }}</div>
          </div>
        </div>
      </section>

      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
          <h2 class="text-sm font-bold">採用候補</h2>
          <span v-if="dividerVariableResult.bestCandidate"
            class="rounded border px-2 py-1 text-xs font-semibold"
            :class="variableStatusClass(dividerVariableResult.bestCandidate.status)">
            @{{ dividerVariableResult.bestCandidate.verdict }}
          </span>
        </div>
        <div class="grid gap-3 md:grid-cols-5">
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">R上</div>
            <div class="mt-1 font-mono text-xl font-bold">@{{ dividerVariableResult.selectedTopDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">VR</div>
            <div class="mt-1 font-mono text-xl font-bold">@{{ dividerVariableResult.selectedPotDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">R下</div>
            <div class="mt-1 font-mono text-xl font-bold">@{{ dividerVariableResult.selectedBottomDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">候補下限</div>
            <div class="mt-1 font-mono text-xl font-bold">@{{ dividerVariableResult.selectedLowDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">候補上限</div>
            <div class="mt-1 font-mono text-xl font-bold">@{{ dividerVariableResult.selectedHighDisplay }}</div>
          </div>
        </div>
        <div v-if="dividerVariableResult.bestCandidate" class="mt-3 grid gap-3 md:grid-cols-5">
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">最大回路電流</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.selectedSourceCurrentDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">R上最大電力</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.selectedTopPowerDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">VR最大電力</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.selectedPotPowerDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">R下最大電力</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.selectedBottomPowerDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">抵抗合計最大</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.selectedResistorPowerDisplay }}</div>
          </div>
        </div>
        <div v-if="dividerVariableResult.bestCandidate" class="mt-4 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
          <div class="font-mono text-sm">@{{ dividerVariableResult.bestCandidate.expression }}</div>
          <div class="mt-3 grid gap-2 text-xs sm:grid-cols-4">
            <div class="rounded border border-[var(--color-border)] px-3 py-2">
              <span class="opacity-50">下限余裕</span>
              <span class="ml-2 font-mono">@{{ dividerVariableResult.bestCandidate.lowMarginDisplay }}</span>
            </div>
            <div class="rounded border border-[var(--color-border)] px-3 py-2">
              <span class="opacity-50">上限余裕</span>
              <span class="ml-2 font-mono">@{{ dividerVariableResult.bestCandidate.highMarginDisplay }}</span>
            </div>
            <div class="rounded border border-[var(--color-border)] px-3 py-2">
              <span class="opacity-50">負荷電流</span>
              <span class="ml-2 font-mono">@{{ dividerVariableResult.bestCandidate.outputCurrentDisplay }}</span>
            </div>
          <div class="rounded border border-[var(--color-border)] px-3 py-2">
            <span class="opacity-50">範囲判定</span>
            <span class="ml-2 font-mono">@{{ dividerVariableResult.bestCandidate.rangeMarginDisplay }}</span>
          </div>
        </div>
          <div v-if="dividerVariableResult.bestCandidate.rssOutputRangeDisplay" class="mt-3 rounded border border-[var(--color-border)] px-3 py-2 text-xs">
            <div class="font-semibold opacity-60">素子誤差範囲</div>
            <div class="mt-1 font-mono text-sm font-bold">@{{ dividerVariableResult.bestCandidate.rssOutputRangeDisplay }}</div>
            <div class="mt-1 grid gap-1 font-mono opacity-70">
              <span>@{{ dividerVariableResult.bestCandidate.toleranceDisplay }}</span>
              <span>RSS下限端 @{{ dividerVariableResult.bestCandidate.rssLowEndpointRangeDisplay }}</span>
              <span>RSS上限端 @{{ dividerVariableResult.bestCandidate.rssHighEndpointRangeDisplay }}</span>
              <span>コーナー @{{ dividerVariableResult.bestCandidate.cornerOutputRangeDisplay }}</span>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <span v-for="tag in dividerVariableResult.bestCandidate.tags" :key="`best-divider-${tag}`"
              class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">
              @{{ tag }}
            </span>
          </div>
        </div>
        <div v-if="!dividerVariableResult.valid" class="mt-3 rounded border border-amber-400 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          入力電圧、出力電圧範囲、VR値、負荷条件を確認してください
        </div>
      </section>

      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
        <h2 class="mb-3 text-sm font-bold">候補一覧</h2>
        <div v-if="dividerVariableResult.candidates.length" class="grid gap-3">
          <article v-for="candidate in dividerVariableResult.candidates" :key="`${candidate.top}-${candidate.pot}-${candidate.bottom}-${candidate.low}`"
            class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded border px-2 py-1 text-xs font-semibold" :class="variableStatusClass(candidate.status)">
                @{{ candidate.verdict }}
              </span>
              <span class="font-mono text-sm font-bold">@{{ candidate.topDisplay }} + VR @{{ candidate.potDisplay }} + @{{ candidate.bottomDisplay }}</span>
              <span class="ml-auto font-mono text-xs">@{{ candidate.lowDisplay }} 〜 @{{ candidate.highDisplay }}</span>
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
              <span v-for="tag in candidate.tags" :key="`${candidate.top}-${candidate.pot}-${candidate.bottom}-${tag}`"
                class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">
                @{{ tag }}
              </span>
            </div>
          </article>
        </div>
        <div v-else class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-4 text-sm opacity-60">
          候補なし
        </div>
      </section>

      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <h2 class="mb-3 text-sm font-bold">理想値</h2>
        <div class="grid gap-3 md:grid-cols-4">
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">理想R上</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.idealTopDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">理想VR</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.idealPotDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">理想R下</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.idealBottomDisplay }}</div>
          </div>
          <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="text-xs opacity-50">理想総抵抗</div>
            <div class="mt-1 font-mono text-lg font-bold">@{{ dividerVariableResult.idealTotalDisplay }}</div>
          </div>
        </div>
        <div class="mt-3 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3 font-mono text-sm">
          @{{ dividerVariableResult.expression }}
        </div>
      </section>
    </main>
  </section>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '抵抗/容量ネットワーク探索', 'current' => true]], 'class' => 'mt-6'])
</div>
</body>
</html>
