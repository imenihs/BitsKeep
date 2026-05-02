<!DOCTYPE html>
<html lang="ja">
<head>
  @include('partials.theme-init')
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>設計解析ツール - BitsKeep</title>
  @include('partials.favicon')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--color-bg)] text-[var(--color-text)]">
@include('partials.app-header', ['current' => '設計解析ツール'])
<div id="app" data-page="design-tools" data-tool="adc" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '設計解析ツール', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">設計解析ツール</h1>
  </header>

  <section class="grid gap-3 mb-5 md:grid-cols-3">
    <div v-for="band in hubBands" :key="band.label"
      class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
      <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">@{{ band.label }}</div>
      <div class="mt-1 text-sm font-semibold">@{{ band.value }}</div>
    </div>
  </section>

  <!-- ツールタブ -->
  <div class="flex flex-wrap gap-1 mb-4 pb-2 border-b border-[var(--color-border)]">
    <button v-for="t in tools" :key="t.id" @click="activeToolId = t.id"
      :class="activeToolId === t.id ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-card-odd)] hover:opacity-90'"
      class="px-3 py-1.5 rounded text-sm transition-colors border border-[var(--color-border)]">
      @{{ t.label }}
    </button>
  </div>
  <!-- アクティブツールの説明 -->
  <p v-if="activeTool?.desc" class="text-xs opacity-60 mb-5">@{{ activeTool.desc }}</p>

  <section v-if="activeToolId === 'passive-network'" class="mb-6 space-y-4">
    <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">受動部品ネットワーク/分圧</div>
          <h2 class="mt-1 text-lg font-bold">サブモードを選んで同じ画面で計算</h2>
        </div>
        <div class="flex flex-wrap gap-1 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-1">
          <button v-for="mode in passiveNetwork.modeOptions" :key="mode.value" type="button"
            @click="passiveNetwork.setActiveMode(mode.value)"
            class="rounded-md px-3 py-2 text-sm font-semibold"
            :class="passiveNetwork.activeMode === mode.value ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-card-even)]'">
            @{{ mode.label }}
          </button>
        </div>
      </div>

      <div v-if="passiveNetwork.activeMode === 'network' || (passiveNetwork.activeMode === 'divider' && passiveNetwork.form.divider_mode === 'fixed')" class="grid gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-bold">@{{ passiveNetwork.activeMode === 'divider' ? '分圧条件' : 'R/C探索条件' }}</h3>
            <span class="text-xs opacity-60">@{{ passiveNetwork.partTypeLabel }}</span>
          </div>
          <div class="grid gap-3">
            <div v-if="passiveNetwork.activeMode === 'network'" class="grid grid-cols-2 gap-2">
              <button v-for="type in passiveNetwork.partTypeOptions" :key="type.value" type="button"
                @click="passiveNetwork.setPartType(type.value)"
                class="rounded border px-3 py-2 text-sm font-semibold"
                :class="passiveNetwork.form.part_type === type.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)]'">
                @{{ type.label }}
              </button>
            </div>

            <div v-if="passiveNetwork.activeMode === 'divider'" class="grid grid-cols-2 gap-2">
              <button v-for="mode in passiveNetwork.dividerModeOptions" :key="mode.value" type="button"
                @click="passiveNetwork.setDividerMode(mode.value)"
                class="rounded border px-3 py-2 text-sm font-semibold"
                :class="passiveNetwork.form.divider_mode === mode.value ? 'border-[var(--color-primary)] bg-[var(--color-card-even)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
                @{{ mode.label }}
              </button>
            </div>

            <div v-if="passiveNetwork.activeMode === 'divider'" class="grid grid-cols-2 gap-2">
              <button v-for="mode in passiveNetwork.dividerTargetModeOptions" :key="mode.value" type="button"
                @click="passiveNetwork.form.divider_target_mode = mode.value"
                class="rounded border px-3 py-2 text-sm"
                :class="passiveNetwork.form.divider_target_mode === mode.value ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
                @{{ mode.label }}
              </button>
            </div>

            <label v-if="passiveNetwork.activeMode !== 'divider' || passiveNetwork.form.divider_target_mode === 'ratio'" class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">@{{ passiveNetwork.activeMode === 'divider' ? '目標比率' : '目標値' }}</span>
              <input v-model="passiveNetwork.form.target_raw" class="input-text w-full font-mono" :placeholder="passiveNetwork.targetHint" @keyup.enter="passiveNetwork.search" />
            </label>

            <div v-if="passiveNetwork.activeMode === 'divider' && passiveNetwork.form.divider_target_mode === 'voltage'" class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">入力電圧</span>
                <input v-model="passiveNetwork.form.input_voltage_raw" class="input-text w-full font-mono" placeholder="3.3" @keyup.enter="passiveNetwork.search" />
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">出力電圧</span>
                <input v-model="passiveNetwork.form.output_voltage_raw" class="input-text w-full font-mono" placeholder="2.5" @keyup.enter="passiveNetwork.search" />
              </label>
              <div class="col-span-2 rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] px-3 py-2 text-xs">
                <span class="opacity-55">換算比率</span>
                <span class="ml-2 font-mono font-semibold">@{{ passiveNetwork.dividerVoltageTarget.valid ? `${(passiveNetwork.dividerVoltageTarget.ratio * 100).toPrecision(5)}%` : '-' }}</span>
              </div>
            </div>

            <label v-if="passiveNetwork.activeMode === 'divider' && passiveNetwork.form.divider_target_mode === 'ratio'" class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">入力電圧</span>
              <input v-model="passiveNetwork.form.input_voltage_raw" class="input-text w-full font-mono" placeholder="3.3" @keyup.enter="passiveNetwork.search" />
            </label>

            <div v-if="passiveNetwork.activeMode === 'divider'" class="grid gap-3 rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
              <div class="grid grid-cols-2 gap-2">
                <button v-for="loadType in passiveNetwork.loadTypeOptions" :key="loadType.value" type="button"
                  @click="passiveNetwork.form.load_type = loadType.value"
                  class="rounded border px-3 py-2 text-sm"
                  :class="passiveNetwork.form.load_type === loadType.value ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
                  @{{ loadType.label }}
                </button>
              </div>
              <label v-if="passiveNetwork.form.load_type === 'resistance'" class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">負荷抵抗</span>
                <div class="flex gap-2">
                  <input v-model="passiveNetwork.form.load_resistance_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="∞ / 10k" @keyup.enter="passiveNetwork.search" />
                  <button type="button" @click="passiveNetwork.setLoadResistanceInfinite(passiveNetwork.form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm hover:border-[var(--color-primary)]">∞</button>
                </div>
              </label>
              <label v-if="passiveNetwork.form.load_type === 'current'" class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">負荷電流</span>
                <div class="flex gap-2">
                  <input v-model="passiveNetwork.form.load_current_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="0 / 1mA" @keyup.enter="passiveNetwork.search" />
                  <button type="button" @click="passiveNetwork.setLoadCurrentZero(passiveNetwork.form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm hover:border-[var(--color-primary)]">0A</button>
                </div>
              </label>
              <div class="text-xs opacity-60">負荷: @{{ passiveNetwork.dividerLoadConfig.display }}</div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">許容誤差</span>
                <input v-model.number="passiveNetwork.form.tolerance_pct" type="number" min="0.001" max="50" step="0.1" class="input-text w-full font-mono" />
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">E系列</span>
                <select v-model="passiveNetwork.form.series" class="input-text w-full">
                  <option v-for="series in passiveNetwork.seriesOptions" :key="series" :value="series">@{{ series }}</option>
                </select>
              </label>
            </div>

            <label v-if="passiveNetwork.activeMode === 'network'" class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">採用素子許容差</span>
              <input v-model.number="passiveNetwork.form.element_tolerance_pct" type="number" min="0" max="100" step="0.1" class="input-text w-full font-mono" />
            </label>

            <div v-if="passiveNetwork.activeMode === 'divider'" class="grid grid-cols-2 gap-3 rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">R1許容差</span>
                <input v-model.number="passiveNetwork.form.divider_upper_tolerance_pct" type="number" min="0" max="100" step="0.1" class="input-text w-full font-mono" />
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">R2許容差</span>
                <input v-model.number="passiveNetwork.form.divider_lower_tolerance_pct" type="number" min="0" max="100" step="0.1" class="input-text w-full font-mono" />
              </label>
            </div>

            <textarea v-if="passiveNetwork.form.series === 'custom'" v-model="passiveNetwork.form.custom_values" rows="4"
              class="input-text w-full resize-none font-mono text-sm" placeholder="100, 220, 470, 1k, 2.2k"></textarea>

            <div v-if="passiveNetwork.activeMode === 'network'" class="grid grid-cols-3 gap-2">
              <button v-for="type in passiveNetwork.circuitOptions" :key="type.value" type="button"
                @click="passiveNetwork.toggleCircuitType(type.value)"
                class="rounded border px-2 py-2 text-sm"
                :class="passiveNetwork.form.circuit_types.includes(type.value) ? 'border-[var(--color-primary)] text-[var(--color-primary)] bg-[var(--color-card-even)]' : 'border-[var(--color-border)]'">
                @{{ type.label }}
              </button>
            </div>

            <div v-if="passiveNetwork.activeMode === 'network'" class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">最小素子数</span>
                <select v-model.number="passiveNetwork.form.min_elements" class="input-text w-full">
                  <option v-for="n in 4" :key="`pn-min-${n}`" :value="n">@{{ n }}</option>
                </select>
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">最大素子数</span>
                <select v-model.number="passiveNetwork.form.max_elements" class="input-text w-full">
                  <option v-for="n in 4" :key="`pn-max-${n}`" :value="n">@{{ n }}</option>
                </select>
              </label>
            </div>

            <div v-if="passiveNetwork.activeMode === 'divider'" class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">総抵抗 min</span>
                <input v-model="passiveNetwork.form.total_res_min_raw" class="input-text w-full font-mono" placeholder="1k" />
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-semibold opacity-60">総抵抗 max</span>
                <input v-model="passiveNetwork.form.total_res_max_raw" class="input-text w-full font-mono" placeholder="100k" />
              </label>
            </div>

            <button type="button" @click="passiveNetwork.search" :disabled="!passiveNetwork.formValid || passiveNetwork.searching"
              class="btn-primary rounded-lg px-4 py-3 text-sm font-bold disabled:opacity-40">
              @{{ passiveNetwork.searching ? '探索中' : '探索' }}
            </button>
            <div v-if="passiveNetwork.error || !passiveNetwork.formValid" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
              @{{ passiveNetwork.error || passiveNetwork.validationMessage }}
            </div>
          </div>
        </aside>

        <main class="min-w-0 space-y-4">
          <div class="grid gap-3 md:grid-cols-4">
            <div v-for="metric in passiveNetwork.statusMetrics" :key="metric.label" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
              <div class="text-xs opacity-55">@{{ metric.label }}</div>
              <div class="mt-1 truncate font-mono text-lg font-bold">@{{ metric.value }}</div>
            </div>
          </div>
          <div v-if="passiveNetwork.warnings.length || passiveNetwork.nextActions.length" class="grid gap-2">
            <div v-for="message in passiveNetwork.warnings" :key="`pn-warn-${message}`" class="rounded border border-amber-400 bg-amber-50 px-3 py-2 text-sm text-amber-800">@{{ message }}</div>
            <div v-for="message in passiveNetwork.nextActions" :key="`pn-next-${message}`" class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2 text-sm">@{{ message }}</div>
          </div>
          <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-border)] px-4 py-3">
              <div>
                <h3 class="text-sm font-bold">候補</h3>
                <div class="text-xs opacity-60">@{{ passiveNetwork.summaryText }}</div>
              </div>
              <span v-if="passiveNetwork.elapsedMs !== null" class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">@{{ passiveNetwork.elapsedMs }}ms</span>
            </div>
            <div v-if="passiveNetwork.searching" class="grid min-h-56 place-items-center text-sm opacity-60">探索中</div>
            <div v-else-if="passiveNetwork.elapsedMs === null" class="grid min-h-56 place-items-center text-sm opacity-45">候補待ち</div>
            <div v-else-if="passiveNetwork.results.length === 0" class="grid min-h-56 place-items-center text-sm opacity-60">該当候補なし</div>
            <div v-else class="divide-y divide-[var(--color-border)]">
              <article v-for="candidate in passiveNetwork.rankedResults" :key="candidate.id" class="grid gap-3 p-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                <div class="min-w-0">
                  <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded bg-[var(--color-card-even)] px-2 py-1 text-xs font-semibold">#@{{ candidate.rank }}</span>
                    <span class="rounded bg-[var(--color-card-even)] px-2 py-1 text-xs">@{{ candidate.topology_label || passiveNetwork.circuitTypeLabel(candidate.circuit_type) }}</span>
                    <span class="rounded bg-[var(--color-card-even)] px-2 py-1 text-xs">@{{ candidate.elements_count }}素子</span>
                  </div>
                  <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3 font-mono text-sm">@{{ candidate.expression }}</div>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <span v-for="part in candidate.parts" :key="`${candidate.id}-${part.role}-${part.label}`" class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">
                      <span class="opacity-50">@{{ part.role }}</span>
                      <span class="ml-1">@{{ part.label }}</span>
                    </span>
                  </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                  <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                    <div class="text-xs opacity-50">合成値</div>
                    <div class="mt-1 font-mono text-lg font-bold">@{{ candidate.actual_display }}</div>
                    <div v-if="candidate.actual_output_display" class="mt-1 font-mono text-xs opacity-65">@{{ candidate.actual_output_display }}</div>
                  </div>
                  <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                    <div class="text-xs opacity-50">誤差</div>
                    <div class="mt-1 font-mono text-lg font-bold" :class="passiveNetwork.errorClass(candidate.error_pct)">@{{ candidate.error_display }}</div>
                    <div v-if="candidate.output_error_display" class="mt-1 font-mono text-xs opacity-65">@{{ candidate.output_error_display }}</div>
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
                  <div v-if="candidate.divider_rss_range_display || candidate.rss_range_display" class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
                    <div class="text-xs opacity-50">素子誤差範囲</div>
                    <div class="mt-1 font-mono text-sm font-bold">@{{ candidate.divider_rss_range_display || candidate.rss_range_display }}</div>
                  </div>
                </div>
              </article>
            </div>
          </section>
        </main>
      </div>

      <div v-if="passiveNetwork.activeMode === 'variable'" class="grid gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
          <h3 class="mb-3 text-sm font-bold">可変抵抗 + 固定抵抗</h3>
          <div class="grid gap-3">
            <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">基準抵抗値</span><input v-model="passiveNetwork.variable.reference_raw" class="input-text w-full font-mono" placeholder="10k" /></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">可変幅</span><input v-model="passiveNetwork.variable.span_raw" class="input-text w-full font-mono" placeholder="20 または 2k" /></label>
            <div class="grid grid-cols-2 gap-2">
              <button type="button" @click="passiveNetwork.variable.span_mode = 'percent'" class="rounded border px-3 py-2 text-sm" :class="passiveNetwork.variable.span_mode === 'percent' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">%</button>
              <button type="button" @click="passiveNetwork.variable.span_mode = 'ohm'" class="rounded border px-3 py-2 text-sm" :class="passiveNetwork.variable.span_mode === 'ohm' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">Ω</button>
            </div>
            <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">基準位置</span><select v-model="passiveNetwork.variable.reference_position" class="input-text w-full"><option v-for="position in passiveNetwork.variableReferencePositionOptions" :key="position.value" :value="position.value">@{{ position.label }}</option></select></label>
            <select v-model="passiveNetwork.variable.circuit" class="input-text w-full"><option value="series">直列トリム</option><option value="parallel">並列トリム</option></select>
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">固定抵抗ソース</span><select v-model="passiveNetwork.variable.fixed_source" class="input-text w-full"><option v-for="source in passiveNetwork.variableFixedSourceOptions" :key="source" :value="source">@{{ source }}</option></select></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">VRソース</span><select v-model="passiveNetwork.variable.pot_source" class="input-text w-full"><option v-for="source in passiveNetwork.variablePotSourceOptions" :key="source" :value="source">@{{ source === 'vr-common' ? '標準VR値' : source }}</option></select></label>
            </div>
            <div class="grid grid-cols-2 gap-3 rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">固定許容差</span><input v-model.number="passiveNetwork.variable.fixed_tolerance_pct" type="number" min="0" max="100" step="0.1" class="input-text w-full font-mono" /></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">VR許容差</span><input v-model.number="passiveNetwork.variable.pot_tolerance_pct" type="number" min="0" max="100" step="0.1" class="input-text w-full font-mono" /></label>
            </div>
          </div>
        </aside>
        <main class="space-y-4">
          <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <h3 class="text-sm font-bold">採用候補</h3>
              <span v-if="passiveNetwork.variableResult.bestCandidate" class="rounded border px-2 py-1 text-xs font-semibold" :class="passiveNetwork.variableStatusClass(passiveNetwork.variableResult.bestCandidate.status)">@{{ passiveNetwork.variableResult.bestCandidate.verdict }}</span>
            </div>
            <div class="grid gap-3 md:grid-cols-4">
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">候補固定抵抗</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.variableResult.selectedFixedDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">候補VR</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.variableResult.selectedPotDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">候補下限</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.variableResult.selectedLowDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">候補上限</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.variableResult.selectedHighDisplay }}</div></div>
            </div>
            <div v-if="passiveNetwork.variableResult.bestCandidate" class="mt-4 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
              <div class="font-mono text-sm">@{{ passiveNetwork.variableResult.bestCandidate.expression }}</div>
              <div class="mt-3 flex flex-wrap gap-2"><span v-for="tag in passiveNetwork.variableResult.bestCandidate.tags" :key="tag" class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">@{{ tag }}</span></div>
            </div>
          </section>
        </main>
      </div>

      <div v-if="passiveNetwork.activeMode === 'divider' && passiveNetwork.form.divider_mode === 'variable'" class="grid gap-4 lg:grid-cols-[380px_minmax(0,1fr)]">
        <aside class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
          <h3 class="mb-3 text-sm font-bold">VR分圧条件</h3>
          <div class="grid gap-3">
            <div class="grid grid-cols-2 gap-2">
              <button v-for="mode in passiveNetwork.dividerModeOptions" :key="mode.value" type="button" @click="passiveNetwork.setDividerMode(mode.value)"
                class="rounded border px-3 py-2 text-sm font-semibold"
                :class="passiveNetwork.form.divider_mode === mode.value ? 'border-[var(--color-primary)] bg-[var(--color-card-even)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">@{{ mode.label }}</button>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <button v-for="mode in passiveNetwork.dividerTargetModeOptions" :key="mode.value" type="button" @click="passiveNetwork.form.divider_target_mode = mode.value"
                class="rounded border px-3 py-2 text-sm"
                :class="passiveNetwork.form.divider_target_mode === mode.value ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">@{{ mode.label }}</button>
            </div>
            <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">入力電圧</span><input v-model="passiveNetwork.form.input_voltage_raw" class="input-text w-full font-mono" placeholder="5" /></label>
            <div v-if="passiveNetwork.form.divider_target_mode === 'voltage'" class="grid grid-cols-2 gap-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">出力下限</span><input v-model="passiveNetwork.dividerVariable.output_low_raw" class="input-text w-full font-mono" placeholder="1" /></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">出力上限</span><input v-model="passiveNetwork.dividerVariable.output_high_raw" class="input-text w-full font-mono" placeholder="3" /></label>
            </div>
            <div v-if="passiveNetwork.form.divider_target_mode === 'ratio'" class="grid grid-cols-2 gap-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">出力下限比率</span><input v-model="passiveNetwork.dividerVariable.output_low_ratio_raw" class="input-text w-full font-mono" placeholder="20%" /></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">出力上限比率</span><input v-model="passiveNetwork.dividerVariable.output_high_ratio_raw" class="input-text w-full font-mono" placeholder="60%" /></label>
            </div>
            <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">基準VR値</span><input v-model="passiveNetwork.dividerVariable.nominal_pot_raw" class="input-text w-full font-mono" placeholder="10k" /></label>
            <div class="grid gap-3 rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
              <div class="grid grid-cols-2 gap-2">
                <button v-for="loadType in passiveNetwork.loadTypeOptions" :key="loadType.value" type="button" @click="passiveNetwork.form.load_type = loadType.value"
                  class="rounded border px-3 py-2 text-sm"
                  :class="passiveNetwork.form.load_type === loadType.value ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">@{{ loadType.label }}</button>
              </div>
              <label v-if="passiveNetwork.form.load_type === 'resistance'" class="block"><span class="mb-1 block text-xs font-semibold opacity-60">負荷抵抗</span><div class="flex gap-2"><input v-model="passiveNetwork.form.load_resistance_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="∞ / 10k" /><button type="button" @click="passiveNetwork.setLoadResistanceInfinite(passiveNetwork.form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm">∞</button></div></label>
              <label v-if="passiveNetwork.form.load_type === 'current'" class="block"><span class="mb-1 block text-xs font-semibold opacity-60">負荷電流</span><div class="flex gap-2"><input v-model="passiveNetwork.form.load_current_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="0 / 1mA" /><button type="button" @click="passiveNetwork.setLoadCurrentZero(passiveNetwork.form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm">0A</button></div></label>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">許容誤差</span><input v-model.number="passiveNetwork.form.tolerance_pct" type="number" min="0" max="50" step="0.1" class="input-text w-full font-mono" /></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">E系列</span><select v-model="passiveNetwork.form.series" class="input-text w-full"><option v-for="series in passiveNetwork.seriesOptions" :key="series" :value="series">@{{ series }}</option></select></label>
            </div>
            <div class="grid grid-cols-3 gap-3 rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">R上許容差</span><input v-model.number="passiveNetwork.dividerVariable.top_tolerance_pct" type="number" min="0" max="100" step="0.1" class="input-text w-full font-mono" /></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">VR許容差</span><input v-model.number="passiveNetwork.dividerVariable.pot_tolerance_pct" type="number" min="0" max="100" step="0.1" class="input-text w-full font-mono" /></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">R下許容差</span><input v-model.number="passiveNetwork.dividerVariable.bottom_tolerance_pct" type="number" min="0" max="100" step="0.1" class="input-text w-full font-mono" /></label>
            </div>
          </div>
        </aside>
        <main class="space-y-4">
          <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <h3 class="text-sm font-bold">採用候補</h3>
              <span v-if="passiveNetwork.dividerVariableResult.bestCandidate" class="rounded border px-2 py-1 text-xs font-semibold" :class="passiveNetwork.variableStatusClass(passiveNetwork.dividerVariableResult.bestCandidate.status)">@{{ passiveNetwork.dividerVariableResult.bestCandidate.verdict }}</span>
            </div>
            <div class="grid gap-3 md:grid-cols-5">
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">R上</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.dividerVariableResult.selectedTopDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">VR</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.dividerVariableResult.selectedPotDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">R下</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.dividerVariableResult.selectedBottomDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">候補下限</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.dividerVariableResult.selectedLowDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">候補上限</div><div class="mt-1 font-mono text-xl font-bold">@{{ passiveNetwork.dividerVariableResult.selectedHighDisplay }}</div></div>
            </div>
            <div v-if="passiveNetwork.dividerVariableResult.bestCandidate" class="mt-3 grid gap-3 md:grid-cols-5">
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">最大回路電流</div><div class="mt-1 font-mono text-lg font-bold">@{{ passiveNetwork.dividerVariableResult.selectedSourceCurrentDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">R上最大電力</div><div class="mt-1 font-mono text-lg font-bold">@{{ passiveNetwork.dividerVariableResult.selectedTopPowerDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">VR最大電力</div><div class="mt-1 font-mono text-lg font-bold">@{{ passiveNetwork.dividerVariableResult.selectedPotPowerDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">R下最大電力</div><div class="mt-1 font-mono text-lg font-bold">@{{ passiveNetwork.dividerVariableResult.selectedBottomPowerDisplay }}</div></div>
              <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-3"><div class="text-xs opacity-50">抵抗合計最大</div><div class="mt-1 font-mono text-lg font-bold">@{{ passiveNetwork.dividerVariableResult.selectedResistorPowerDisplay }}</div></div>
            </div>
            <div v-if="passiveNetwork.dividerVariableResult.bestCandidate" class="mt-4 rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
              <div class="font-mono text-sm">@{{ passiveNetwork.dividerVariableResult.bestCandidate.expression }}</div>
              <div class="mt-3 flex flex-wrap gap-2"><span v-for="tag in passiveNetwork.dividerVariableResult.bestCandidate.tags" :key="tag" class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">@{{ tag }}</span></div>
            </div>
          </section>
        </main>
      </div>
    </div>
  </section>

  <section v-if="advancedInputGroups.length" class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <div>
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">入力条件</div>
        <h2 class="mt-1 text-sm font-bold">基本条件 / 最悪条件 / 部品定格 / 出力・保存</h2>
      </div>
      <span class="tag text-[10px]">@{{ activeTool?.label }}</span>
    </div>
    <div class="grid gap-3 lg:grid-cols-4">
      <div v-for="group in advancedInputGroups" :key="group.label" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="mb-2 text-xs font-semibold opacity-60">@{{ group.label }}</div>
        <div class="grid gap-2">
          <label v-for="item in group.fields" :key="`${group.label}-${item.key}`" class="block">
            <span class="block text-[11px] opacity-60 mb-1">@{{ item.label }}</span>
            <textarea v-if="item.type === 'textarea'" v-model="item.target[item.key]"
              @focus="focusDiagram(item.diagramKey || item.key)" @blur="clearDiagramFocus"
              rows="4" class="input-text w-full font-mono text-xs"></textarea>
            <input v-else-if="item.type === 'text'" v-model="item.target[item.key]"
              @focus="focusDiagram(item.diagramKey || item.key)" @blur="clearDiagramFocus"
              type="text" class="input-text w-full font-mono text-xs" />
            <input v-else :value="item.target[item.key]" @change="setNumericInput(item.target, item.key, $event, item.storedUnitFactor)"
              @focus="focusDiagram(item.diagramKey || item.key)" @blur="setNumericInput(item.target, item.key, $event, item.storedUnitFactor); clearDiagramFocus()"
              type="text" inputmode="decimal" autocomplete="off" class="input-text w-full font-mono text-xs" />
          </label>
        </div>
      </div>
    </div>
  </section>

  <section v-if="activeDiagram" class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(280px,0.75fr)] lg:items-stretch">
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">回路の前提</div>
            <h2 class="mt-1 text-sm font-bold">@{{ activeDiagram.title }}</h2>
            <p class="mt-1 text-xs leading-5 opacity-60">@{{ activeDiagram.subtitle }}</p>
          </div>
          <span v-if="diagramFocus" class="tag tag-ok">@{{ diagramFocus }}</span>
        </div>

        <svg class="circuit-svg mt-3" viewBox="0 0 640 260" role="img" :aria-label="activeDiagram.title">
          <defs>
            <marker id="circuit-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
              <path d="M 0 0 L 10 5 L 0 10 z" class="circuit-arrow-fill"></path>
            </marker>
          </defs>

          <template v-if="activeDiagram.type === 'divider' || activeDiagram.type === 'divider-ntc'">
            <line x1="180" y1="40" x2="180" y2="68" class="circuit-wire"></line>
            <line x1="180" y1="116" x2="180" y2="146" class="circuit-wire"></line>
            <line x1="180" y1="194" x2="180" y2="216" class="circuit-wire"></line>
            <line x1="180" y1="130" x2="430" y2="130" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass(activeDiagram.keys.input)" @mouseenter="focusDiagram(activeDiagram.keys.input)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.input)">
              <circle cx="180" cy="40" r="18" class="circuit-node"></circle>
              <text x="180" y="44" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'Vin' : 'Rntc' }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.upper)" @mouseenter="focusDiagram(activeDiagram.keys.upper)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.upper)">
              <rect x="155" y="68" width="50" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="97" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'R1' : 'R0' }}</text>
              <text x="222" y="95" class="circuit-note">@{{ activeDiagram.type === 'divider' ? '上側' : '基準' }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.output)" @mouseenter="focusDiagram(activeDiagram.keys.output)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.output)">
              <circle cx="180" cy="130" r="6" class="circuit-junction"></circle>
              <rect x="430" y="105" width="120" height="50" rx="8" class="circuit-box"></rect>
              <text x="490" y="126" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'Vout' : '温度' }}</text>
              <text x="490" y="143" text-anchor="middle" class="circuit-note">@{{ activeDiagram.type === 'divider' ? 'ADC/後段へ' : '換算結果' }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.lower)" @mouseenter="focusDiagram(activeDiagram.keys.lower)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.lower)">
              <rect x="155" y="146" width="50" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="175" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'R2' : 'Rntc' }}</text>
              <text x="222" y="173" class="circuit-note">@{{ activeDiagram.type === 'divider' ? '下側' : '測定値' }}</text>
            </g>
            <g>
              <line x1="160" y1="216" x2="200" y2="216" class="circuit-wire"></line>
              <line x1="166" y1="224" x2="194" y2="224" class="circuit-wire"></line>
              <line x1="173" y1="232" x2="187" y2="232" class="circuit-wire"></line>
              <text x="214" y="226" class="circuit-note">GND</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'shunt'">
            <line x1="70" y1="120" x2="170" y2="120" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <line x1="270" y1="120" x2="380" y2="120" class="circuit-wire"></line>
            <g :class="diagramItemClass('I')" @mouseenter="focusDiagram('I')" @mouseleave="clearDiagramFocus" @click="focusDiagram('I')">
              <text x="82" y="100" class="circuit-label">I</text>
              <text x="76" y="143" class="circuit-note">負荷電流</text>
            </g>
            <g :class="diagramItemClass('Rs')" @mouseenter="focusDiagram('Rs')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Rs')">
              <rect x="170" y="96" width="100" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="220" y="124" text-anchor="middle" class="circuit-label">Rs</text>
              <text x="220" y="142" text-anchor="middle" class="circuit-note">シャント</text>
            </g>
            <g :class="diagramItemClass('gain')" @mouseenter="focusDiagram('gain')" @mouseleave="clearDiagramFocus" @click="focusDiagram('gain')">
              <path d="M 355 70 L 455 120 L 355 170 Z" class="circuit-box"></path>
              <text x="385" y="116" text-anchor="middle" class="circuit-label">AMP</text>
              <text x="386" y="134" text-anchor="middle" class="circuit-note">gain</text>
              <line x1="270" y1="102" x2="355" y2="100" class="circuit-wire"></line>
              <line x1="270" y1="138" x2="355" y2="140" class="circuit-wire"></line>
            </g>
            <g :class="diagramItemClass('Vout')" @mouseenter="focusDiagram('Vout')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vout')">
              <line x1="455" y1="120" x2="560" y2="120" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <rect x="560" y="96" width="55" height="48" rx="6" class="circuit-box"></rect>
              <text x="588" y="124" text-anchor="middle" class="circuit-label">Vout</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'comparator'">
            <g :class="diagramItemClass('R1')" @mouseenter="focusDiagram('R1')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R1')">
              <line x1="70" y1="100" x2="145" y2="100" class="circuit-wire"></line>
              <rect x="145" y="78" width="70" height="44" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="105" text-anchor="middle" class="circuit-label">R1</text>
              <text x="94" y="91" class="circuit-note">Vin</text>
            </g>
            <line x1="215" y1="100" x2="290" y2="100" class="circuit-wire"></line>
            <g>
              <path d="M 290 62 L 290 178 L 410 120 Z" class="circuit-box"></path>
              <text x="310" y="104" class="circuit-label">+</text>
              <text x="310" y="151" class="circuit-label">-</text>
              <text x="346" y="124" text-anchor="middle" class="circuit-label">CMP</text>
            </g>
            <g :class="diagramItemClass('Vref')" @mouseenter="focusDiagram('Vref')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vref')">
              <line x1="140" y1="160" x2="290" y2="145" class="circuit-wire"></line>
              <circle cx="140" cy="160" r="18" class="circuit-node"></circle>
              <text x="140" y="164" text-anchor="middle" class="circuit-label">Vref</text>
            </g>
            <g :class="diagramItemClass('R2')" @mouseenter="focusDiagram('R2')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R2')">
              <rect x="176" y="146" width="58" height="34" rx="6" class="circuit-symbol-fill"></rect>
              <text x="205" y="168" text-anchor="middle" class="circuit-label">R2</text>
            </g>
            <g :class="diagramItemClass('out')" @mouseenter="focusDiagram('out')" @mouseleave="clearDiagramFocus" @click="focusDiagram('out')">
              <line x1="410" y1="120" x2="565" y2="120" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <text x="520" y="108" class="circuit-label">OUT</text>
            </g>
            <g :class="diagramItemClass('R3')" @mouseenter="focusDiagram('R3')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R3')">
              <path d="M 510 120 C 510 42 260 42 290 145" class="circuit-wire"></path>
              <rect x="348" y="26" width="70" height="34" rx="6" class="circuit-symbol-fill"></rect>
              <text x="383" y="48" text-anchor="middle" class="circuit-label">R3</text>
              <text x="430" y="44" class="circuit-note">基準帰還</text>
            </g>
            <g :class="diagramItemClass('Vcc')" @mouseenter="focusDiagram('Vcc')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vcc')">
              <text x="440" y="70" class="circuit-note">Vcc = @{{ comp.Vcc }} V</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'bode'">
            <line x1="70" y1="125" x2="150" y2="125" class="circuit-wire"></line>
            <text x="75" y="108" class="circuit-label">Vin</text>
            <g :class="diagramItemClass(activeDiagram.variant === 'lowpass' ? 'r' : 'c')" @mouseenter="focusDiagram(activeDiagram.variant === 'lowpass' ? 'r' : 'c')" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.variant === 'lowpass' ? 'r' : 'c')">
              <rect x="150" y="101" width="94" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="197" y="130" text-anchor="middle" class="circuit-label">@{{ activeDiagram.variant === 'lowpass' ? 'R' : 'C' }}</text>
            </g>
            <line x1="244" y1="125" x2="390" y2="125" class="circuit-wire"></line>
            <g :class="diagramItemClass('out')" @mouseenter="focusDiagram('out')" @mouseleave="clearDiagramFocus" @click="focusDiagram('out')">
              <circle cx="390" cy="125" r="6" class="circuit-junction"></circle>
              <line x1="390" y1="125" x2="540" y2="125" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <text x="500" y="108" class="circuit-label">Vout</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.variant === 'lowpass' ? 'c' : 'r')" @mouseenter="focusDiagram(activeDiagram.variant === 'lowpass' ? 'c' : 'r')" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.variant === 'lowpass' ? 'c' : 'r')">
              <line x1="390" y1="125" x2="390" y2="158" class="circuit-wire"></line>
              <rect x="365" y="158" width="50" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="390" y="187" text-anchor="middle" class="circuit-label">@{{ activeDiagram.variant === 'lowpass' ? 'C' : 'R' }}</text>
            </g>
            <line x1="370" y1="220" x2="410" y2="220" class="circuit-wire"></line>
            <line x1="376" y1="228" x2="404" y2="228" class="circuit-wire"></line>
            <line x1="383" y1="236" x2="397" y2="236" class="circuit-wire"></line>
            <g :class="diagramItemClass('freq')" @mouseenter="focusDiagram('freq')" @mouseleave="clearDiagramFocus" @click="focusDiagram('freq')">
              <text x="75" y="165" class="circuit-note">評価周波数</text>
              <text x="75" y="183" class="circuit-label">@{{ quickForms.bode.freq }} Hz</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'protection'">
            <g :class="diagramItemClass(activeDiagram.keys.input)" @mouseenter="focusDiagram(activeDiagram.keys.input)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.input)">
              <circle cx="75" cy="120" r="18" class="circuit-node"></circle>
              <text x="75" y="124" text-anchor="middle" class="circuit-label">@{{ activeDiagram.labels.input }}</text>
            </g>
            <line x1="93" y1="120" x2="150" y2="120" class="circuit-wire"></line>
            <g :class="diagramItemClass(activeDiagram.keys.series)" @mouseenter="focusDiagram(activeDiagram.keys.series)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.series)">
              <rect x="150" y="96" width="100" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="200" y="124" text-anchor="middle" class="circuit-label">@{{ activeDiagram.labels.series }}</text>
            </g>
            <line x1="250" y1="120" x2="395" y2="120" class="circuit-wire"></line>
            <g :class="diagramItemClass(activeDiagram.keys.clamp)" @mouseenter="focusDiagram(activeDiagram.keys.clamp)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.clamp)">
              <line x1="330" y1="120" x2="330" y2="160" class="circuit-wire"></line>
              <rect x="304" y="160" width="52" height="44" rx="6" class="circuit-symbol-fill"></rect>
              <text x="330" y="187" text-anchor="middle" class="circuit-label">@{{ activeDiagram.labels.clamp }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.load)" @mouseenter="focusDiagram(activeDiagram.keys.load)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.load)">
              <rect x="395" y="88" width="120" height="64" rx="8" class="circuit-box"></rect>
              <text x="455" y="116" text-anchor="middle" class="circuit-label">@{{ activeDiagram.labels.load }}</text>
              <text x="455" y="136" text-anchor="middle" class="circuit-note">protected node</text>
            </g>
            <line x1="515" y1="120" x2="585" y2="120" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <line x1="310" y1="218" x2="350" y2="218" class="circuit-wire"></line>
            <line x1="316" y1="226" x2="344" y2="226" class="circuit-wire"></line>
            <line x1="323" y1="234" x2="337" y2="234" class="circuit-wire"></line>
            <text x="362" y="226" class="circuit-note">GND</text>
          </template>

          <template v-else-if="activeDiagram.type === 'power'">
            <g :class="diagramItemClass('supply')" @mouseenter="focusDiagram('supply')" @mouseleave="clearDiagramFocus" @click="focusDiagram('supply')">
              <rect x="55" y="92" width="120" height="72" rx="10" class="circuit-box"></rect>
              <text x="115" y="122" text-anchor="middle" class="circuit-label">Supply</text>
              <text x="115" y="142" text-anchor="middle" class="circuit-note">@{{ power.supply_w }} W</text>
            </g>
            <line x1="175" y1="128" x2="540" y2="128" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('loads')" @mouseenter="focusDiagram('loads')" @mouseleave="clearDiagramFocus" @click="focusDiagram('loads')">
              <g v-for="(load, index) in power.loads.slice(0, 3)" :key="`load-${index}`" :transform="`translate(${245 + index * 110}, 78)`">
                <rect width="86" height="72" rx="8" class="circuit-symbol-fill"></rect>
                <text x="43" y="30" text-anchor="middle" class="circuit-label">@{{ load.label || 'Load' }}</text>
                <text x="43" y="50" text-anchor="middle" class="circuit-note">@{{ load.mA }}mA</text>
              </g>
              <text v-if="power.loads.length > 3" x="570" y="92" class="circuit-note">+@{{ power.loads.length - 3 }}</text>
            </g>
            <g :class="diagramItemClass('margin')" @mouseenter="focusDiagram('margin')" @mouseleave="clearDiagramFocus" @click="focusDiagram('margin')">
              <rect x="260" y="180" width="160" height="42" rx="8" class="circuit-box"></rect>
              <text x="340" y="206" text-anchor="middle" class="circuit-label">余裕 @{{ powerResult.margin }} W</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'thermal'">
            <g :class="diagramItemClass('Tambient')" @mouseenter="focusDiagram('Tambient')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Tambient')">
              <rect x="45" y="96" width="95" height="62" rx="8" class="circuit-box"></rect>
              <text x="92" y="122" text-anchor="middle" class="circuit-label">Ta</text>
              <text x="92" y="142" text-anchor="middle" class="circuit-note">@{{ thermal.Tambient }} degC</text>
            </g>
            <line x1="140" y1="127" x2="520" y2="127" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('nodes')" @mouseenter="focusDiagram('nodes')" @mouseleave="clearDiagramFocus" @click="focusDiagram('nodes')">
              <g v-for="(node, index) in thermal.nodes.slice(0, 3)" :key="`thermal-${index}`" :transform="`translate(${175 + index * 112}, 86)`">
                <rect width="90" height="82" rx="8" class="circuit-symbol-fill"></rect>
                <text x="45" y="30" text-anchor="middle" class="circuit-label">Rth</text>
                <text x="45" y="50" text-anchor="middle" class="circuit-note">@{{ node.Rth }}</text>
                <text x="45" y="68" text-anchor="middle" class="circuit-note">@{{ node.label.slice(0, 8) }}</text>
              </g>
            </g>
            <g :class="diagramItemClass('P')" @mouseenter="focusDiagram('P')" @mouseleave="clearDiagramFocus" @click="focusDiagram('P')">
              <path d="M 510 92 L 590 127 L 510 162 Z" class="circuit-box"></path>
              <text x="535" y="122" text-anchor="middle" class="circuit-label">P</text>
              <text x="536" y="142" text-anchor="middle" class="circuit-note">@{{ thermal.P }} W</text>
            </g>
            <g :class="diagramItemClass('Tj')" @mouseenter="focusDiagram('Tj')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Tj')">
              <text x="510" y="206" class="circuit-label">Tj @{{ thermalResult.Tjunction }} degC</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'interface'">
            <g>
              <rect x="70" y="78" width="150" height="104" rx="10" class="circuit-box"></rect>
              <text x="145" y="108" text-anchor="middle" class="circuit-label">Driver</text>
            </g>
            <g :class="diagramItemClass('VOH')" @mouseenter="focusDiagram('VOH')" @mouseleave="clearDiagramFocus" @click="focusDiagram('VOH')">
              <text x="110" y="138" class="circuit-label">VOH @{{ iface.VOH }}V</text>
            </g>
            <g :class="diagramItemClass('VOL')" @mouseenter="focusDiagram('VOL')" @mouseleave="clearDiagramFocus" @click="focusDiagram('VOL')">
              <text x="110" y="158" class="circuit-label">VOL @{{ iface.VOL }}V</text>
            </g>
            <line x1="220" y1="130" x2="420" y2="130" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g>
              <rect x="420" y="78" width="150" height="104" rx="10" class="circuit-box"></rect>
              <text x="495" y="108" text-anchor="middle" class="circuit-label">Receiver</text>
            </g>
            <g :class="diagramItemClass('VIH')" @mouseenter="focusDiagram('VIH')" @mouseleave="clearDiagramFocus" @click="focusDiagram('VIH')">
              <text x="455" y="138" class="circuit-label">VIH @{{ iface.VIH }}V</text>
            </g>
            <g :class="diagramItemClass('VIL')" @mouseenter="focusDiagram('VIL')" @mouseleave="clearDiagramFocus" @click="focusDiagram('VIL')">
              <text x="455" y="158" class="circuit-label">VIL @{{ iface.VIL }}V</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'connector'">
            <g :class="diagramItemClass('endA')" @mouseenter="focusDiagram('endA')" @mouseleave="clearDiagramFocus" @click="focusDiagram('endA')">
              <rect x="95" y="78" width="150" height="112" rx="10" class="circuit-box"></rect>
              <text x="170" y="104" text-anchor="middle" class="circuit-label">端A / pin1</text>
              <circle v-for="pin in 5" :key="`a-${pin}`" :cx="125 + pin * 16" cy="132" r="5" class="circuit-junction"></circle>
              <text x="170" y="166" text-anchor="middle" class="circuit-note">mating face</text>
            </g>
            <line x1="245" y1="132" x2="395" y2="132" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('endB')" @mouseenter="focusDiagram('endB')" @mouseleave="clearDiagramFocus" @click="focusDiagram('endB')">
              <rect x="395" y="78" width="150" height="112" rx="10" class="circuit-box"></rect>
              <text x="470" y="104" text-anchor="middle" class="circuit-label">端B / pin1</text>
              <circle v-for="pin in 5" :key="`b-${pin}`" :cx="425 + pin * 16" cy="132" r="5" class="circuit-junction"></circle>
              <text x="470" y="166" text-anchor="middle" class="circuit-note">solder side?</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'startup'">
            <g :class="diagramItemClass('rails')" @mouseenter="focusDiagram('rails')" @mouseleave="clearDiagramFocus" @click="focusDiagram('rails')">
              <rect x="72" y="86" width="92" height="54" rx="8" class="circuit-box"></rect>
              <text x="118" y="118" text-anchor="middle" class="circuit-label">VIN</text>
              <line x1="164" y1="113" x2="250" y2="113" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <rect x="250" y="86" width="92" height="54" rx="8" class="circuit-box"></rect>
              <text x="296" y="118" text-anchor="middle" class="circuit-label">3V3</text>
              <line x1="342" y1="113" x2="428" y2="113" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <rect x="428" y="86" width="92" height="54" rx="8" class="circuit-box"></rect>
              <text x="474" y="118" text-anchor="middle" class="circuit-label">1V8</text>
            </g>
            <g :class="diagramItemClass('reset')" @mouseenter="focusDiagram('reset')" @mouseleave="clearDiagramFocus" @click="focusDiagram('reset')">
              <path d="M 296 140 C 296 186 430 186 430 148" class="circuit-wire" marker-end="url(#circuit-arrow)"></path>
              <rect x="250" y="182" width="140" height="40" rx="8" class="circuit-symbol-fill"></rect>
              <text x="320" y="207" text-anchor="middle" class="circuit-label">RESET / PG</text>
            </g>
          </template>

          <template v-else>
            <g v-for="(block, index) in activeDiagram.blocks" :key="block.key"
              :transform="`translate(${40 + index * 145}, 92)`"
              :class="diagramItemClass(block.key)"
              @mouseenter="focusDiagram(block.key)" @mouseleave="clearDiagramFocus" @click="focusDiagram(block.key)">
              <rect width="112" height="76" rx="10" class="circuit-box"></rect>
              <text x="56" y="34" text-anchor="middle" class="circuit-label">@{{ block.label }}</text>
              <text x="56" y="56" text-anchor="middle" class="circuit-note">@{{ block.sub }}</text>
            </g>
            <g v-if="activeDiagram.blocks?.length > 1">
              <line v-for="index in activeDiagram.blocks.length - 1" :key="`flow-${index}`"
                :x1="40 + (index - 1) * 145 + 112" y1="130"
                :x2="40 + index * 145" y2="130"
                class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            </g>
          </template>
        </svg>
      </div>

      <aside class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">Labels / Formula</div>
        <div class="mt-3 flex flex-wrap gap-2">
          <button v-for="part in activeDiagram.parts" :key="part.key" type="button"
            @mouseenter="focusDiagram(part.key)" @mouseleave="clearDiagramFocus" @click="focusDiagram(part.key)"
            class="rounded-lg border border-[var(--color-border)] px-2 py-1 text-left text-xs transition"
            :class="isDiagramFocused(part.key) ? 'border-[var(--color-primary)] bg-[color-mix(in_srgb,var(--color-primary)_10%,var(--color-card-odd))]' : 'bg-[var(--color-bg)]'">
            <span class="block font-semibold">@{{ part.label }}</span>
            <span class="block max-w-[15rem] truncate opacity-60">@{{ part.desc }}</span>
          </button>
        </div>
        <div class="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-[11px] font-semibold opacity-50">式</div>
          <div class="mt-1 break-words font-mono text-xs font-semibold">@{{ activeDiagram.formula }}</div>
        </div>
        <div v-if="activeDiagram.assumptions?.length" class="mt-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-[11px] font-semibold opacity-50">未評価条件</div>
          <ul class="mt-1 list-disc pl-4 text-xs leading-5 opacity-70">
            <li v-for="item in activeDiagram.assumptions" :key="item">@{{ item }}</li>
          </ul>
        </div>
      </aside>
    </div>
  </section>

  <section v-if="analysisReport" class="mb-6 rounded-2xl border bg-[var(--color-card-even)] p-4"
    :class="{
      'border-[var(--color-tag-ok)]': analysisReport.tone === 'ok',
      'border-[var(--color-tag-warning)]': analysisReport.tone === 'warn',
      'border-[var(--color-tag-eol)]': analysisReport.tone === 'bad',
      'border-[var(--color-border)]': analysisReport.tone === 'neutral'
    }">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-[11px] uppercase tracking-[0.18em] opacity-50">判定結果</span>
          <span class="tag"
            :class="{
              'tag-ok': analysisReport.tone === 'ok' && analysisReport.verdict !== 'CHECK',
              'tag-warning': analysisReport.tone === 'warn',
              'tag-eol': analysisReport.tone === 'bad',
              'border border-[var(--color-tag-warning)] text-[var(--color-tag-warning)]': analysisReport.verdict === 'CHECK'
            }">@{{ analysisReport.verdict }}</span>
        </div>
        <p class="mt-2 text-sm font-semibold leading-6">@{{ analysisReport.summary }}</p>
      </div>
      <div v-if="analysisReport.dominantFactors.length" class="shrink-0 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
        <div class="text-[11px] font-semibold opacity-50">支配要因</div>
        <div class="mt-1 flex flex-wrap gap-1">
          <span v-for="factor in analysisReport.dominantFactors" :key="factor" :title="factor" class="tag text-[10px]">@{{ factor.length > 10 ? factor.slice(0, 10) + '...' : factor }}</span>
        </div>
      </div>
    </div>
    <div v-if="analysisReport.metrics.length" class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
      <div v-for="metric in analysisReport.metrics" :key="metric.label"
        class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
        <div class="text-[11px] opacity-50">@{{ metric.label }}</div>
        <div class="mt-1 break-words font-mono text-sm font-semibold">@{{ metric.value }}</div>
      </div>
    </div>
    <div class="mt-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-xs font-semibold opacity-60">コピー用サマリ</div>
        <button type="button" @click="copyAnalysisSummary"
          class="rounded border border-[var(--color-border)] px-2 py-1 text-xs hover:bg-[var(--color-card-odd)]">
          コピー
        </button>
      </div>
      <p class="mt-2 text-xs leading-5 opacity-75">@{{ analysisReport.copySummary }}</p>
    </div>
    <div class="mt-4 grid gap-3 lg:grid-cols-2">
      <div v-if="analysisReport.missingConditions.length" class="rounded-xl border border-[var(--color-tag-warning)] bg-[color-mix(in_srgb,var(--color-tag-warning)_8%,var(--color-bg))] px-3 py-2">
        <div class="text-xs font-semibold text-[var(--color-tag-warning)]">判定に必要な不足条件</div>
        <ul class="mt-1 list-disc pl-5 text-xs leading-5">
          <li v-for="condition in analysisReport.missingConditions" :key="condition">@{{ condition }}</li>
        </ul>
      </div>
      <div v-if="analysisReport.assumptions.length" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
        <div class="text-xs font-semibold opacity-60">前提</div>
        <ul class="mt-1 list-disc pl-5 text-xs leading-5">
          <li v-for="assumption in analysisReport.assumptions" :key="assumption">@{{ assumption }}</li>
        </ul>
      </div>
      <div v-if="analysisReport.warnings.length" class="rounded-xl border border-[var(--color-tag-warning)] bg-[color-mix(in_srgb,var(--color-tag-warning)_8%,var(--color-bg))] px-3 py-2">
        <div class="text-xs font-semibold text-[var(--color-tag-warning)]">不足条件・注意</div>
        <ul class="mt-1 list-disc pl-5 text-xs leading-5">
          <li v-for="warning in analysisReport.warnings" :key="warning">@{{ warning }}</li>
        </ul>
      </div>
      <div v-if="analysisReport.nextActions.length" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
        <div class="text-xs font-semibold opacity-60">次に直す/確認する項目</div>
        <ul class="mt-1 list-disc pl-5 text-xs leading-5">
          <li v-for="action in analysisReport.nextActions" :key="action">@{{ action }}</li>
        </ul>
      </div>
    </div>
    <div v-if="analysisReport.candidateLinks.length" class="mt-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
      <div class="text-xs font-semibold opacity-60">候補リンク</div>
      <div class="mt-2 flex flex-wrap gap-2">
        <a v-for="link in analysisReport.candidateLinks" :key="link.url || link.label"
          :href="link.url" target="_blank" rel="noopener noreferrer"
          class="tag text-[10px] hover:opacity-80">@{{ link.label || link.url }}</a>
      </div>
    </div>
    <div class="mt-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-3">
      <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_repeat(3,minmax(8rem,0.35fr))_auto] lg:items-end">
        <label class="block">
          <span class="block text-[11px] font-semibold opacity-60 mb-1">保存</span>
          <input v-model="outputSave.title" type="text" class="input-text w-full"
            :placeholder="`${activeTool?.label || activeToolId} ${analysisReport.verdict}`" />
        </label>
        <label class="block">
          <span class="block text-[11px] font-semibold opacity-60 mb-1">案件</span>
          <input v-model="outputSave.projectId" type="number" min="1" class="input-text w-full font-mono" />
        </label>
        <label class="block">
          <span class="block text-[11px] font-semibold opacity-60 mb-1">部品</span>
          <input v-model="outputSave.componentId" type="number" min="1" class="input-text w-full font-mono" />
        </label>
        <label class="block">
          <span class="block text-[11px] font-semibold opacity-60 mb-1">BOMの行番号/識別名</span>
          <input v-model="outputSave.bomLineKey" type="text" class="input-text w-full font-mono" />
        </label>
        <button type="button" @click="saveAnalysisReport" :disabled="outputSave.saving"
          class="rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)] disabled:cursor-not-allowed disabled:opacity-50">
          @{{ outputSave.saving ? '保存中...' : '解析セッション保存' }}
        </button>
      </div>
      <div class="mt-4 grid gap-3 lg:grid-cols-3">
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
          <div class="text-xs font-semibold opacity-60">登録部品から取り込み</div>
          <button type="button" @click="loadComponentContext" :disabled="componentImport.loading"
            class="mt-2 rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)] disabled:cursor-not-allowed disabled:opacity-50">
            @{{ componentImport.loading ? '読込中...' : '部品から取り込み' }}
          </button>
          <div v-if="componentImport.component" class="mt-2 text-xs leading-5">
            <div class="font-semibold">@{{ loadedComponentName }}</div>
            <div class="opacity-60">在庫 @{{ loadedComponentStock }} pcs / スペック件数 @{{ componentImport.component.specs?.length || 0 }}</div>
            <ul v-if="componentImport.applied.length" class="mt-1 list-disc pl-4 opacity-70">
              <li v-for="item in componentImport.applied" :key="item">@{{ item }}</li>
            </ul>
          </div>
          <p v-if="componentImport.status === 'success'" class="mt-2 text-xs text-[var(--color-tag-ok)]">@{{ componentImport.message }}</p>
          <div v-if="componentImport.status === 'error'" class="mt-2 rounded-lg border border-[var(--color-tag-eol)] bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))] p-3 text-xs">
            <div class="font-semibold text-[var(--color-tag-eol)]">取り込みに失敗しました</div>
            <p class="mt-1 opacity-80">@{{ componentImport.error }}</p>
            <button type="button" @click="loadComponentContext" class="mt-2 rounded border border-[var(--color-tag-eol)] px-2 py-1">再試行</button>
          </div>
        </div>
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
          <div class="text-xs font-semibold opacity-60">保存済み解析差分</div>
          <button type="button" @click="loadSavedAnalysis" :disabled="savedAnalysis.loading"
            class="mt-2 rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)] disabled:cursor-not-allowed disabled:opacity-50">
            @{{ savedAnalysis.loading ? '取得中...' : '再計算・差分比較' }}
          </button>
          <div v-if="savedAnalysis.diff" class="mt-2 text-xs leading-5">
            <div class="font-semibold">@{{ savedAnalysis.diff.title }}</div>
            <div class="opacity-60">@{{ savedAnalysis.diff.previousVerdict }} -> @{{ savedAnalysis.diff.currentVerdict }}</div>
            <ul v-if="savedAnalysis.diff.changes.length" class="mt-1 list-disc pl-4 opacity-70">
              <li v-for="change in savedAnalysis.diff.changes" :key="change">@{{ change }}</li>
            </ul>
            <p v-else class="mt-1 opacity-60">入力条件に差分はありません。</p>
          </div>
          <p v-if="savedAnalysis.status === 'success'" class="mt-2 text-xs text-[var(--color-tag-ok)]">@{{ savedAnalysis.message }}</p>
          <div v-if="savedAnalysis.status === 'error'" class="mt-2 rounded-lg border border-[var(--color-tag-eol)] bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))] p-3 text-xs">
            <div class="font-semibold text-[var(--color-tag-eol)]">差分取得に失敗しました</div>
            <p class="mt-1 opacity-80">@{{ savedAnalysis.error }}</p>
            <button type="button" @click="loadSavedAnalysis" class="mt-2 rounded border border-[var(--color-tag-eol)] px-2 py-1">再試行</button>
          </div>
        </div>
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
          <div class="text-xs font-semibold opacity-60">解析テンプレート</div>
          <select v-model="templateState.selected" class="input-text mt-2 w-full text-xs">
            <option v-for="template in analysisTemplates" :key="template.id" :value="template.id">@{{ template.label }}</option>
          </select>
          <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" @click="applyAnalysisTemplate"
              class="rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)]">
              入力へ反映
            </button>
            <button type="button" @click="duplicateAnalysisTemplate" :disabled="outputSave.saving"
              class="rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)] disabled:cursor-not-allowed disabled:opacity-50">
              複製保存
            </button>
          </div>
          <p v-if="templateState.status === 'success'" class="mt-2 text-xs text-[var(--color-tag-ok)]">@{{ templateState.message }}</p>
          <div v-if="templateState.status === 'error'" class="mt-2 rounded-lg border border-[var(--color-tag-eol)] bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))] p-3 text-xs">
            <div class="font-semibold text-[var(--color-tag-eol)]">テンプレート操作に失敗しました</div>
            <p class="mt-1 opacity-80">@{{ templateState.error }}</p>
            <button type="button" @click="applyAnalysisTemplate" class="mt-2 rounded border border-[var(--color-tag-eol)] px-2 py-1">再試行</button>
          </div>
        </div>
      </div>
      <p v-if="outputSave.status === 'success'" class="mt-2 text-xs text-[var(--color-tag-ok)]">@{{ outputSave.message }}</p>
      <div v-if="outputSave.status === 'error'" class="mt-2 rounded-lg border border-[var(--color-tag-eol)] bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))] p-3 text-xs">
        <div class="font-semibold text-[var(--color-tag-eol)]">保存に失敗しました</div>
        <p class="mt-1 opacity-80">@{{ outputSave.error }}</p>
        <button type="button" @click="saveAnalysisReport" class="mt-2 rounded border border-[var(--color-tag-eol)] px-2 py-1">再試行</button>
      </div>
    </div>
  </section>

  <!-- ══════ ADCスケーリング ══════ -->
  <div v-if="activeToolId === 'adc'">
    <h2 class="font-bold text-lg mb-4">ADCコード/スケーリング設計</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">分解能 (bits)</label>
          <input :value="adc.bits" @change="setNumericInput(adc, 'bits', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('adc')" @blur="setNumericInput(adc, 'bits', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">Vref (V)</label>
          <input :value="adc.vref" @change="setNumericInput(adc, 'vref', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('vref')" @blur="setNumericInput(adc, 'vref', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">入力電圧 Vin (V)</label>
          <input :value="adc.vin" @change="setNumericInput(adc, 'vin', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('vin')" @blur="setNumericInput(adc, 'vin', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">オフセット (V)</label>
          <input :value="adc.offset" @change="setNumericInput(adc, 'offset', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('offset')" @blur="setNumericInput(adc, 'offset', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">ADCコード</span>
          <span class="font-mono font-bold text-xl" :class="adcResult.clipped ? 'text-red-500' : ''">@{{ adcResult.code }}</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">HEX</span>
          <span class="font-mono">@{{ adcResult.hex }}</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">1LSB</span>
          <span class="font-mono">@{{ adcResult.lsb_mv }} mV</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">フルスケール比</span>
          <span class="font-mono">@{{ adcResult.percent }}%</span></div>
        <div v-if="adcResult.clipped" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-700">
          <div class="font-semibold">入力範囲の警告</div>
          <p class="mt-1">入力がレンジ外です（クリッピング）。Vrefまたは入力電圧を見直してください。</p>
          <button type="button" @click="activeToolId = 'adc'" class="mt-2 rounded border border-red-300 px-2 py-1">再確認</button>
        </div>
      </div>
    </div>
  </div>

  <section v-if="activeToolId === 'connector'" class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
      <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">ピン配置</div>
            <h3 class="mt-1 text-sm font-bold">@{{ connectorActiveTemplate?.label }}</h3>
            <p class="mt-1 text-xs leading-5 opacity-60">@{{ connectorActiveTemplate?.numbering }}</p>
          </div>
          <button type="button" @click="applyConnectorTemplate()"
            class="rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)]">
            テンプレート反映
          </button>
        </div>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="pin in connectorPinMap" :key="pin.pin"
            class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-2 text-xs"
            :class="{
              'border-[var(--color-tag-eol)]': pin.currentMargin < 0 || pin.voltageMargin < 0 || pin.awgMargin < 0,
              'border-[var(--color-tag-ok)]': pin.assigned && pin.currentMargin >= 0 && pin.voltageMargin >= 0
            }">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono font-bold">@{{ pin.pin }}</span>
              <span class="tag text-[10px]">@{{ pin.type }}</span>
            </div>
            <div class="mt-1 truncate font-semibold">@{{ pin.signal || '未割付' }}</div>
            <div class="mt-1 font-mono opacity-70">@{{ pin.voltage }}V / @{{ pin.current }}A</div>
            <div class="mt-1 text-[11px] opacity-60">I余裕 @{{ pin.currentMargin.toFixed(3) }}A</div>
          </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
          <a v-if="quickForms.connector.photoUrl || connectorActiveTemplate?.photoUrl"
            :href="quickForms.connector.photoUrl || connectorActiveTemplate.photoUrl" target="_blank"
            class="rounded border border-[var(--color-border)] px-3 py-2 text-xs no-underline hover:bg-[var(--color-card-odd)]">写真を開く</a>
          <a v-if="quickForms.connector.diagramUrl || connectorActiveTemplate?.diagramUrl"
            :href="quickForms.connector.diagramUrl || connectorActiveTemplate.diagramUrl" target="_blank"
            class="rounded border border-[var(--color-border)] px-3 py-2 text-xs no-underline hover:bg-[var(--color-card-odd)]">ピン配置図を開く</a>
          <a v-if="quickForms.connector.datasheetUrl || connectorActiveTemplate?.datasheetUrl"
            :href="quickForms.connector.datasheetUrl || connectorActiveTemplate.datasheetUrl" target="_blank"
            class="rounded border border-[var(--color-border)] px-3 py-2 text-xs no-underline hover:bg-[var(--color-card-odd)]">データシートを開く</a>
        </div>
      </div>

      <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">ユーザー登録</div>
        <h3 class="mt-1 text-sm font-bold">コネクタテンプレートを追加</h3>
        <div class="mt-3 grid gap-2 md:grid-cols-2">
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">名称</span>
            <input v-model="quickForms.connector.userTemplateName" class="input-text w-full text-xs" placeholder="例: XH 6P custom" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">規格/系列</span>
            <input v-model="quickForms.connector.userTemplateStandard" class="input-text w-full text-xs" placeholder="例: JST XH" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">極数</span>
            <input :value="quickForms.connector.userTemplatePins" @change="setNumericInput(quickForms.connector, 'userTemplatePins', $event)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">列数</span>
            <input :value="quickForms.connector.userTemplateRows" @change="setNumericInput(quickForms.connector, 'userTemplateRows', $event)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">ピッチ(mm)</span>
            <input :value="quickForms.connector.userTemplatePitchMm" @change="setNumericInput(quickForms.connector, 'userTemplatePitchMm', $event, 1e-3)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">1pin定格(A)</span>
            <input :value="quickForms.connector.userTemplateCurrentRatingPerPin" @change="setNumericInput(quickForms.connector, 'userTemplateCurrentRatingPerPin', $event)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">定格電圧(V)</span>
            <input :value="quickForms.connector.userTemplateVoltageRatingV" @change="setNumericInput(quickForms.connector, 'userTemplateVoltageRatingV', $event)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">相手側部品</span>
            <input v-model="quickForms.connector.userTemplateMatingPart" class="input-text w-full text-xs" placeholder="例: XHP-6 housing" />
          </label>
          <label class="block md:col-span-2">
            <span class="mb-1 block text-[11px] opacity-60">ピン番号規則</span>
            <input v-model="quickForms.connector.userTemplateNumbering" class="input-text w-full text-xs" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">登録写真URL</span>
            <input v-model="quickForms.connector.userTemplatePhotoUrl" class="input-text w-full text-xs" placeholder="写真URL" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">登録ピン配置図URL</span>
            <input v-model="quickForms.connector.userTemplateDiagramUrl" class="input-text w-full text-xs" placeholder="ピン配置図URL" />
          </label>
          <label class="block md:col-span-2">
            <span class="mb-1 block text-[11px] opacity-60">登録データシートURL</span>
            <input v-model="quickForms.connector.userTemplateDatasheetUrl" class="input-text w-full text-xs" placeholder="データシートURL" />
          </label>
          <label class="block md:col-span-2">
            <span class="mb-1 block text-[11px] opacity-60">登録メモ</span>
            <input v-model="quickForms.connector.userTemplateNotes" class="input-text w-full text-xs" placeholder="ロック向き、シェル接続、圧着条件など" />
          </label>
        </div>
        <button type="button" @click="saveConnectorTemplate"
          class="mt-3 rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)]">
          ユーザーコネクタを登録
        </button>
        <div class="mt-4 text-xs leading-5 opacity-70">
          <div>登録済みユーザー定義: @{{ connectorUserTemplates.length }}</div>
          <div>相手側: @{{ quickForms.connector.matingPart || connectorActiveTemplate?.matingPart || '未指定' }}</div>
          <div>BOM: @{{ quickForms.connector.bomNote || '未入力' }}</div>
          <div>シルク: @{{ quickForms.connector.silkNote || '未入力' }}</div>
        </div>
      </div>
  </section>

  <!-- ══════ コンデンサ寿命 ══════ -->
  <div v-if="activeToolId === 'cap-life'">
    <h2 class="font-bold text-lg mb-4">電解コンデンサ寿命推定（アレニウス則）</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div v-for="[key, label, step, diagramKey] in [['L0','定格寿命 L₀ (h)',100,'L0'],['T0','定格温度 T₀ (°C)',5,'L0'],['T','動作温度 T (°C)',1,'T'],['Vr','定格電圧 Vr (V)',1,'V'],['V','動作電圧 V (V)',1,'V']]" :key="key"
          class="flex items-center gap-3">
          <label class="w-32 text-sm">@{{ label }}</label>
          <input :value="cap[key]" @change="setNumericInput(cap, key, $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram(diagramKey)" @blur="setNumericInput(cap, key, $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">推定寿命</span>
          <span class="font-mono font-bold text-xl">@{{ capResult.life_h.toLocaleString() }} h</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">年換算</span>
          <span class="font-mono font-bold">@{{ capResult.life_y }} 年</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">最悪温度寿命</span>
          <span class="font-mono">@{{ capResult.worst_life_y }} 年</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">目標寿命余裕</span>
          <span class="font-mono">@{{ capResult.target_margin_y }} 年</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">温度係数</span>
          <span class="font-mono">× @{{ capResult.temp_factor }}</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">実効温度</span>
          <span class="font-mono">@{{ capResult.effective_temp_c }} °C</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">自己発熱</span>
          <span class="font-mono">@{{ capResult.self_heat_c }} °C</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">リプル損失</span>
          <span class="font-mono">@{{ capResult.ripple_loss_w }} W</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">電圧derating</span>
          <span class="font-mono" :class="capResult.derating_ok ? 'text-emerald-600' : 'text-red-500'">@{{ capResult.derating_ok ? 'OK' : 'NG' }}</span></div>
      </div>
    </div>
  </div>

  <!-- ══════ NTC/PTC温度変換 ══════ -->
  <div v-if="activeToolId === 'divider'">
    <h2 class="font-bold text-lg mb-4">NTC/PTC温度変換</h2>
    <div class="mb-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3 text-sm leading-6 opacity-80">
      通常分圧とVR分圧は設計解析ツール内の「受動部品ネットワーク/分圧」タブに統合しています。この画面ではサーミスタの温度変換、温度スイープ、ADCコード表、線形化係数だけを扱います。
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="flex items-center gap-3"><label class="w-28 text-sm">R₀ @ T₀ (Ω)</label>
          <input :value="divider.R0" @change="setNumericInput(divider, 'R0', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('R0')" @blur="setNumericInput(divider, 'R0', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">T₀ (°C)</label>
          <input :value="divider.T0" @change="setNumericInput(divider, 'T0', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('T0')" @blur="setNumericInput(divider, 'T0', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">B定数</label>
          <input :value="divider.B" @change="setNumericInput(divider, 'B', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('B')" @blur="setNumericInput(divider, 'B', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-32 text-sm">Rntc 測定抵抗 (Ω)</label>
          <input :value="divider.Rmeas" @change="setNumericInput(divider, 'Rmeas', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Rmeas')" @blur="setNumericInput(divider, 'Rmeas', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">温度</span>
          <span class="font-mono font-bold text-xl">@{{ dividerResult.temp_c }} °C</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">絶対温度</span>
          <span class="font-mono">@{{ dividerResult.temp_k }} K</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">ADC条件</span>
          <span class="font-mono">@{{ divider.adcBits }}bit / @{{ divider.adcVref }}V</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">温度sweep</span>
          <span class="font-mono">@{{ divider.tempMin }}〜@{{ divider.tempMax }}°C</span></div>
      </div>
    </div>
  </div>

  <!-- ══════ 電流検出 ══════ -->
  <div v-if="activeToolId === 'shunt'">
    <h2 class="font-bold text-lg mb-4">電流検出解析（シャント抵抗）</h2>
    <div class="flex gap-4 mb-4">
      <label class="flex items-center gap-2 cursor-pointer">
        <input v-model="shunt.mode" type="radio" value="from_vout" />
        <span class="text-sm">Vout → 電流</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input v-model="shunt.mode" type="radio" value="from_current" />
        <span class="text-sm">電流 → Vout</span>
      </label>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="flex items-center gap-3"><label class="w-36 text-sm">Rs シャント抵抗 (Ω)</label>
          <input :value="shunt.Rs" @change="setNumericInput(shunt, 'Rs', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Rs')" @blur="setNumericInput(shunt, 'Rs', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">アンプゲイン</label>
          <input :value="shunt.gain" @change="setNumericInput(shunt, 'gain', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('gain')" @blur="setNumericInput(shunt, 'gain', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div v-if="shunt.mode === 'from_vout'" class="flex items-center gap-3"><label class="w-28 text-sm">Vout (V)</label>
          <input :value="shunt.Vout" @change="setNumericInput(shunt, 'Vout', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Vout')" @blur="setNumericInput(shunt, 'Vout', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div v-else class="flex items-center gap-3"><label class="w-28 text-sm">電流 I (A)</label>
          <input :value="shunt.I" @change="setNumericInput(shunt, 'I', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('I')" @blur="setNumericInput(shunt, 'I', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <template v-if="shunt.mode === 'from_vout'">
          <div class="flex justify-between"><span class="opacity-60 text-sm">電流 I</span>
            <span class="font-mono font-bold text-xl">@{{ shuntResult.I }} A</span></div>
          <div class="flex justify-between"><span class="opacity-60 text-sm">シャント電圧</span>
            <span class="font-mono">@{{ shuntResult.Vshunt_mv }} mV</span></div>
        </template>
        <template v-else>
          <div class="flex justify-between"><span class="opacity-60 text-sm">Vout</span>
            <span class="font-mono font-bold text-xl">@{{ shuntResult.Vout }} V</span></div>
          <div class="flex justify-between"><span class="opacity-60 text-sm">シャント電圧</span>
            <span class="font-mono">@{{ shuntResult.Vshunt_mv }} mV</span></div>
        </template>
        <div class="flex justify-between"><span class="opacity-60 text-sm">シャント損失</span>
          <span class="font-mono">@{{ shuntResult.P_mW }} mW</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">入力換算オフセット</span>
          <span class="font-mono">@{{ shuntResult.offset_error_a }} A</span></div>
      </div>
    </div>
  </div>

  <!-- ══════ 電源余裕 ══════ -->
  <div v-if="activeToolId === 'power'">
    <h2 class="font-bold text-lg mb-4">電源余裕解析</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">供給電力 (W)</label>
          <input :value="power.supply_w" @change="setNumericInput(power, 'supply_w', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('supply')" @blur="setNumericInput(power, 'supply_w', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(l, i) in power.loads" :key="i" class="flex items-center gap-2">
            <input v-model="l.label" type="text" placeholder="名称"
              @focus="focusDiagram('loads')" @blur="clearDiagramFocus"
              class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input :value="l.mA" @change="setNumericInput(l, 'mA', $event, 1e-3)" type="text" inputmode="decimal" autocomplete="off" placeholder="mA"
              @focus="focusDiagram('loads')" @blur="setNumericInput(l, 'mA', $event, 1e-3); clearDiagramFocus()"
              class="w-20 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">mA @</span>
            <input :value="l.V" @change="setNumericInput(l, 'V', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('loads')" @blur="setNumericInput(l, 'V', $event); clearDiagramFocus()"
              class="w-16 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">V</span>
            <button @click="removeLoad(i)" class="text-red-400 hover:text-red-600 text-sm">✕</button>
          </div>
        </div>
        <button @click="addLoad" class="text-xs px-3 py-1.5 border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">+ 追加</button>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">消費電力合計</span>
          <span class="font-mono font-bold text-xl">@{{ powerResult.totalW }} W</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">余裕</span>
          <span class="font-mono font-bold" :class="powerResult.ok ? 'text-emerald-600' : 'text-red-500'">
            @{{ powerResult.margin }} W
          </span></div>
        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
          <div class="h-2 rounded-full transition-all"
            :class="powerResult.ok ? 'bg-emerald-500' : 'bg-red-500'"
            :style="{ width: Math.min(100, parseFloat(powerResult.percent)) + '%' }"></div>
        </div>
        <div class="text-xs text-center opacity-60">@{{ powerResult.percent }}% 使用</div>
        <div v-if="powerResult.railMargins.length" class="mt-3 space-y-1 border-t border-[var(--color-border)] pt-3">
          <div v-for="rail in powerResult.railMargins" :key="rail.name" class="flex items-center justify-between gap-3 text-xs">
            <span class="opacity-70">@{{ rail.name }}</span>
            <span class="font-mono" :class="rail.overloaded ? 'text-red-500' : 'text-emerald-600'">
              @{{ rail.rolledLoadW.toFixed(3) }} / @{{ rail.capacityW.toFixed(3) }} W
            </span>
          </div>
        </div>
        <div v-if="!powerResult.ok" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-700">
          <div class="font-semibold">電源余裕の警告</div>
          <p class="mt-1">供給電力を超過しています。負荷または供給電力を見直してください。</p>
          <button type="button" @click="activeToolId = 'power'" class="mt-2 rounded border border-red-300 px-2 py-1">再確認</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ 比較器 ══════ -->
  <div v-if="activeToolId === 'comparator'">
    <h2 class="font-bold text-lg mb-4">比較器しきい値/ヒステリシス</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div v-for="[key, label, step, diagramKey] in [['Vcc','Vcc 電源電圧 (V)',0.1,'Vcc'],['Vref','Vref 基準電圧 (V)',0.01,'Vref'],['VOH','出力High電圧 (V)',0.01,'out'],['VOL','出力Low電圧 (V)',0.01,'out'],['R1','R1 入力直列抵抗 (Ω)',1000,'R1'],['R2','R2 基準側抵抗 (Ω)',1000,'R2'],['R3','R3 基準帰還抵抗 (Ω, 0=なし)',1000,'R3']]" :key="key"
          class="flex items-center gap-3">
          <label class="w-36 text-sm">@{{ label }}</label>
          <input :value="comp[key]" @change="setNumericInput(comp, key, $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram(diagramKey)" @blur="setNumericInput(comp, key, $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="mb-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3 font-mono text-xs">
          <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 text-center">
            <div>Vin</div><div>R1</div><div>比較入力</div>
            <div class="col-span-3 border-t border-[var(--color-border)]"></div>
            <div>Vref</div><div>R2</div><div>基準ノード ← R3 ← OUT</div>
          </div>
        </div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">Low → High しきい値</span>
          <span class="font-mono font-bold text-lg">@{{ compResult.Vth_rising }} V</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">High → Low しきい値</span>
          <span class="font-mono font-bold text-lg">@{{ compResult.Vth_falling }} V</span></div>
        <div class="flex justify-between border-t border-[var(--color-border)] pt-2"><span class="opacity-60 text-sm">ヒステリシス幅</span>
          <span class="font-mono font-bold">@{{ compResult.hysteresis }} V</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">モデル</span>
          <span class="font-mono">@{{ compResult.topology }}</span></div>
      </div>
    </div>
  </div>

  <!-- ══════ 熱設計 ══════ -->
  <div v-if="activeToolId === 'thermal'">
    <h2 class="font-bold text-lg mb-4">熱設計 / 熱抵抗チェーン</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="flex items-center gap-3 mb-3">
          <label class="w-32 text-sm font-medium">消費電力 (W)</label>
          <input :value="thermal.P" @change="setNumericInput(thermal, 'P', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('P')" @blur="setNumericInput(thermal, 'P', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">雰囲気温度 (°C)</label>
          <input :value="thermal.Tambient" @change="setNumericInput(thermal, 'Tambient', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Tambient')" @blur="setNumericInput(thermal, 'Tambient', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">Tj閾値 (°C)</label>
          <input :value="thermal.TjLimit" @change="setNumericInput(thermal, 'TjLimit', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Tj')" @blur="setNumericInput(thermal, 'TjLimit', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(n, i) in thermal.nodes" :key="i" class="flex items-center gap-2">
            <input v-model="n.label" type="text"
              @focus="focusDiagram('nodes')" @blur="clearDiagramFocus"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input :value="n.Rth" @change="setNumericInput(n, 'Rth', $event)" type="text" inputmode="decimal" autocomplete="off" placeholder="θ(°C/W)"
              @focus="focusDiagram('nodes')" @blur="setNumericInput(n, 'Rth', $event); clearDiagramFocus()"
              class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">°C/W</span>
            <button @click="removeNode(i)" class="text-red-400 hover:text-red-600">✕</button>
          </div>
        </div>
        <button @click="addNode" class="text-xs px-3 py-1.5 border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">+ 熱抵抗追加</button>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4">
        <div class="flex justify-between mb-3"><span class="opacity-60 text-sm">接合部温度 Tj</span>
          <span class="font-mono font-bold text-xl" :class="thermalResult.ok ? 'text-emerald-600' : 'text-red-500'">
            @{{ thermalResult.Tjunction }} °C
          </span></div>
        <div class="text-xs opacity-60 mb-2">温度チェーン（入力端→接合部）:</div>
        <div class="space-y-1">
          <div v-for="n in thermalResult.cumulative" :key="n.label" class="flex justify-between text-xs">
            <span class="opacity-70">@{{ n.label }}</span>
            <span class="font-mono">@{{ n.T }} °C</span>
          </div>
        </div>
        <div v-if="!thermalResult.ok" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-700">
          <div class="font-semibold">熱設計の警告</div>
          <p class="mt-1">Tj が閾値を超えています。熱抵抗、消費電力、雰囲気温度を見直してください。</p>
          <button type="button" @click="activeToolId = 'thermal'" class="mt-2 rounded border border-red-300 px-2 py-1">再確認</button>
        </div>
        <div class="mt-4 border-t border-[var(--color-border)] pt-3">
          <div class="text-xs font-semibold opacity-60 mb-2">熱設計: 代表値</div>
          <div class="grid gap-2">
            <div v-for="item in thermalReferences" :key="`${item.group}-${item.label}`"
              class="flex justify-between gap-3 rounded bg-[var(--color-bg)] px-2 py-1 text-xs">
              <span class="opacity-60">@{{ item.group }} / @{{ item.label }}</span>
              <span class="font-mono">@{{ item.value }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ インタフェース余裕 ══════ -->
  <div v-if="activeToolId === 'interface'">
    <h2 class="font-bold text-lg mb-4">インタフェース電圧余裕解析</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <p class="text-xs font-medium opacity-60">出力側（ドライバ）</p>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VOH (V)</label>
          <input :value="iface.VOH" @change="setNumericInput(iface, 'VOH', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('VOH')" @blur="setNumericInput(iface, 'VOH', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VOL (V)</label>
          <input :value="iface.VOL" @change="setNumericInput(iface, 'VOL', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('VOL')" @blur="setNumericInput(iface, 'VOL', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <p class="text-xs font-medium opacity-60 pt-2">入力側（レシーバ）</p>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VIH (V)</label>
          <input :value="iface.VIH" @change="setNumericInput(iface, 'VIH', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('VIH')" @blur="setNumericInput(iface, 'VIH', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VIL (V)</label>
          <input :value="iface.VIL" @change="setNumericInput(iface, 'VIL', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('VIL')" @blur="setNumericInput(iface, 'VIL', $event); clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-3">
        <div class="flex justify-between items-center">
          <span class="opacity-60 text-sm">Hレベル余裕 (VOH - VIH)</span>
          <div class="text-right">
            <span class="font-mono font-bold text-lg" :class="ifaceResult.high_ok ? 'text-emerald-600' : 'text-red-500'">
              @{{ ifaceResult.high_margin }} V
            </span>
            <span v-if="!ifaceResult.high_ok" class="text-red-500 text-xs ml-1">✗ NG</span>
            <span v-else class="text-emerald-600 text-xs ml-1">✓ OK</span>
          </div>
        </div>
        <div class="flex justify-between items-center">
          <span class="opacity-60 text-sm">Lレベル余裕 (VIL - VOL)</span>
          <div class="text-right">
            <span class="font-mono font-bold text-lg" :class="ifaceResult.low_ok ? 'text-emerald-600' : 'text-red-500'">
              @{{ ifaceResult.low_margin }} V
            </span>
            <span v-if="!ifaceResult.low_ok" class="text-red-500 text-xs ml-1">✗ NG</span>
            <span v-else class="text-emerald-600 text-xs ml-1">✓ OK</span>
          </div>
        </div>
        <div class="flex justify-between items-center">
          <span class="opacity-60 text-sm">I2C立上り</span>
          <span class="font-mono" :class="parseFloat(ifaceResult.i2c_rise_ns) <= iface.i2cRiseNsLimit ? 'text-emerald-600' : 'text-red-500'">@{{ ifaceResult.i2c_rise_ns }} ns</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="opacity-60 text-sm">UART誤差</span>
          <span class="font-mono" :class="Math.abs(parseFloat(ifaceResult.uart_error_pct)) <= 2 ? 'text-emerald-600' : 'text-amber-600'">
            @{{ ifaceResult.uart_error_pct }} %
          </span>
        </div>
        <div class="flex justify-between items-center">
          <span class="opacity-60 text-sm">I2C Lowシンク</span>
          <span class="font-mono" :class="ifaceResult.i2c_sink_ok ? 'text-emerald-600' : 'text-red-500'">
            @{{ ifaceResult.i2c_sink_ma }} mA
          </span>
        </div>
        <div v-if="!ifaceResult.high_ok || !ifaceResult.low_ok || !ifaceResult.i2c_sink_ok" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-700">
          <div class="font-semibold">インタフェース余裕の警告</div>
          <p class="mt-1">余裕がありません。電圧レベル変換が必要な可能性があります。</p>
          <button type="button" @click="activeToolId = 'interface'" class="mt-2 rounded border border-red-300 px-2 py-1">再確認</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ 共通クイック解析フォーム ══════ -->
  <div v-if="quickTool">
    <h2 class="font-bold text-lg mb-4">@{{ quickTool.title }}</h2>
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)] gap-6">
      <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <div class="grid gap-3" :class="activeToolId === 'logic-ic' ? 'xl:grid-cols-2' : 'md:grid-cols-2'">
          <label v-for="field in quickTool.fields" :key="field.key" class="block"
            :class="activeToolId === 'logic-ic' ? 'sm:grid sm:grid-cols-[6.5rem_minmax(0,1fr)] sm:items-center sm:gap-3' : ''">
            <span class="block text-[11px] font-semibold opacity-60"
              :class="activeToolId === 'logic-ic' ? 'mb-1 sm:mb-0' : 'mb-1'">
              @{{ activeToolId === 'logic-ic' ? ({
                family: '系列',
                function: '機能',
                inputs: '入力数',
                packagePins: 'ピン数',
                supplyV: 'Vcc',
                outputType: '出力',
                driverFamily: '送信系列',
                driverVcc: '送信Vcc',
                receiverFamily: '受信系列',
                receiverVcc: '受信Vcc'
              }[field.key] || field.label) : field.label }}
            </span>
            <select v-if="field.type === 'select'" v-model="quickForms[quickTool.model][field.key]"
              @change="field.key === 'selectedTemplateId' ? applyConnectorTemplate() : null"
              @focus="focusDiagram(field.diagramKey || field.key)" @blur="clearDiagramFocus"
              class="input-text w-full">
              <option v-for="option in field.options" :key="option[0]" :value="option[0]">@{{ option[1] }}</option>
            </select>
            <textarea v-else-if="field.type === 'textarea'" v-model="quickForms[quickTool.model][field.key]"
              @focus="focusDiagram(field.diagramKey || field.key)" @blur="clearDiagramFocus"
              rows="7" class="input-text w-full font-mono text-xs md:col-span-2"></textarea>
            <input v-else-if="field.type === 'text'" v-model="quickForms[quickTool.model][field.key]"
              @focus="focusDiagram(field.diagramKey || field.key)" @blur="clearDiagramFocus"
              type="text" class="input-text w-full font-mono" />
            <input v-else :value="quickForms[quickTool.model][field.key]" @change="setNumericInput(quickForms[quickTool.model], field.key, $event, field.storedUnitFactor)"
              @focus="focusDiagram(field.diagramKey || field.key)" @blur="setNumericInput(quickForms[quickTool.model], field.key, $event, field.storedUnitFactor); clearDiagramFocus()"
              type="text" inputmode="decimal" autocomplete="off" class="input-text w-full font-mono" />
          </label>
        </div>
      </div>
      <div class="rounded-2xl border bg-[var(--color-card-odd)] p-4"
        :class="{
          'border-[var(--color-tag-ok)]': quickTool.tone === 'ok',
          'border-[var(--color-tag-warning)]': quickTool.tone === 'warn',
          'border-[var(--color-tag-eol)]': quickTool.tone === 'bad',
          'border-[var(--color-border)]': !quickTool.tone || quickTool.tone === 'check'
        }">
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">計算結果</div>
        <div class="mt-3 space-y-2">
          <div v-for="row in quickTool.rows" :key="row[0]" class="flex items-start justify-between gap-4 border-b border-[var(--color-border)] pb-2 last:border-b-0">
            <span class="text-sm opacity-65">@{{ row[0] }}</span>
            <span class="text-right font-mono font-semibold">@{{ row[1] }}</span>
          </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
          <span class="tag">即時</span>
          <span class="tag">初期値</span>
          <span v-if="quickTool.tone === 'bad'" class="tag tag-eol">要見直し</span>
          <span v-else-if="quickTool.tone === 'warn'" class="tag tag-warning">要確認</span>
          <span v-else-if="quickTool.tone === 'check'" class="tag tag-warning">定格待ち</span>
          <span v-else class="tag tag-ok">OK</span>
        </div>
      </div>
    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '設計解析ツール', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
