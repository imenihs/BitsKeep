{{-- パッシブネットワーク系の仕様図、入力、候補一覧をまとめるpartial。 --}}
  <section v-if="['network-search', 'divider-design', 'variable-resistor'].includes(activeToolId) && activeDiagram"
    data-review-stage="spec"
    class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div class="min-w-0">
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">仕様・前提確認</div>
        <h2 class="mt-1 text-sm font-bold">@{{ activeDiagram.title }}</h2>
        <p class="mt-1 text-xs leading-5 opacity-60">@{{ activeDiagram.subtitle }}</p>
      </div>
      <span class="tag text-[10px]">@{{ activeTool?.label }}</span>
    </div>
    <div class="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.6fr)]">
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="text-[11px] font-semibold opacity-50">式</div>
        <div class="mt-1 break-words font-mono text-xs font-semibold">@{{ activeDiagram.formula }}</div>
      </div>
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="text-[11px] font-semibold opacity-50">確認記号</div>
        <div class="mt-2 flex flex-wrap gap-2">
          <button v-for="part in activeDiagram.parts" :key="part.key" type="button"
            @mouseenter="focusDiagram(part.key)" @mouseleave="clearDiagramFocus" @click="focusDiagram(part.key)"
            class="rounded-lg border border-[var(--color-border)] px-2 py-1 text-left text-xs transition"
            :class="isDiagramFocused(part.key) ? 'border-[var(--color-primary)] bg-[color-mix(in_srgb,var(--color-primary)_10%,var(--color-card-odd))]' : 'bg-[var(--color-card-even)]'">
            <span class="block font-semibold">@{{ part.label }}</span>
            <span class="block max-w-[15rem] truncate opacity-60">@{{ part.desc }}</span>
          </button>
        </div>
      </div>
    </div>
  </section>

  <section v-if="['network-search', 'divider-design', 'variable-resistor'].includes(activeToolId)" class="mb-6 space-y-4">
    <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">設計解析</div>
          <h2 class="mt-1 text-lg font-bold">@{{ ({
            'network-search': 'ネットワーク探索',
            'divider-design': '分圧',
            'variable-resistor': '可変抵抗'
          })[activeToolId] }}</h2>
          <p class="mt-1 text-xs leading-5 opacity-60">@{{ ({
            'network-search': '目標値に近い抵抗/容量ネットワーク候補を探索します。',
            'divider-design': '通常分圧とVR分圧の候補、負荷、電流、電力を確認します。',
            'variable-resistor': '固定抵抗と可変抵抗の組み合わせで調整範囲を確認します。'
          })[activeToolId] }}</p>
        </div>
        <span class="tag text-[10px]">@{{ ({
          'network-search': 'R/C候補',
          'divider-design': passiveNetwork.form.divider_mode === 'variable' ? 'VR分圧' : '通常分圧',
          'variable-resistor': 'VR + 固定抵抗'
        })[activeToolId] }}</span>
      </div>

      <div v-if="activeToolId === 'network-search' || (activeToolId === 'divider-design' && passiveNetwork.form.divider_mode === 'fixed')" class="grid gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
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
                <span class="mb-1 block text-xs font-semibold opacity-60">負荷電流 (A)</span>
                <div class="flex gap-2">
                  <input v-model="passiveNetwork.form.load_current_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="0 / 1m" @keyup.enter="passiveNetwork.search" />
                  <button type="button" @click="passiveNetwork.setLoadCurrentZero(passiveNetwork.form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm hover:border-[var(--color-primary)]">0</button>
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

      <div v-if="activeToolId === 'variable-resistor'" class="grid gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
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

      <div v-if="activeToolId === 'divider-design' && passiveNetwork.form.divider_mode === 'variable'" class="grid gap-4 lg:grid-cols-[380px_minmax(0,1fr)]">
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
              <label v-if="passiveNetwork.form.load_type === 'current'" class="block"><span class="mb-1 block text-xs font-semibold opacity-60">負荷電流 (A)</span><div class="flex gap-2"><input v-model="passiveNetwork.form.load_current_raw" class="input-text min-w-0 flex-1 font-mono" placeholder="0 / 1m" /><button type="button" @click="passiveNetwork.setLoadCurrentZero(passiveNetwork.form)" class="rounded border border-[var(--color-border)] px-3 py-2 font-mono text-sm">0</button></div></label>
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
