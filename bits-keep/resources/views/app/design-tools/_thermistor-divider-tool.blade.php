{{-- NTC/PTC温度変換と感度グラフを表示するpartial。 --}}
  <!-- ══════ NTC/PTC温度変換 ══════ -->
  <div v-if="activeToolId === 'divider'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">NTC/PTC温度変換</h2>
    <div class="mb-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3 text-sm leading-6 opacity-80">
      Rthと固定抵抗で作る温度センサ測定回路を確認します。温度-電圧カーブ、測定対象範囲の感度、自己発熱、ADCコードを同時に見ます。
    </div>
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
      <div class="space-y-3">
        <div class="grid grid-cols-2 gap-2">
          <button v-for="type in [['ntc','NTC'],['ptc','PTC']]" :key="type[0]" type="button"
            @click="divider.sensorType = type[0]"
            class="rounded border px-3 py-2 text-sm font-semibold"
            :class="divider.sensorType === type[0] ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)]'">
            @{{ type[1] }}
          </button>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button v-for="position in [['low','下側Rth'],['high','上側Rth']]" :key="position[0]" type="button"
            @click="divider.position = position[0]"
            class="rounded border px-3 py-2 text-sm"
            :class="divider.position === position[0] ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-[var(--color-border)]'">
            @{{ position[1] }}
          </button>
        </div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">R₀ @ T₀ (Ω)</label>
          <input :value="numericInputValue(divider, 'R0')" @input="setNumericInput(divider, 'R0', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('R0')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">T₀ (°C)</label>
          <input :value="numericInputValue(divider, 'T0')" @input="setNumericInput(divider, 'T0', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('T0')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">B定数</label>
          <input :value="numericInputValue(divider, 'B')" @input="setNumericInput(divider, 'B', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('B')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-32 text-sm">Rth 測定抵抗 (Ω)</label>
          <input :value="numericInputValue(divider, 'Rmeas')" @input="setNumericInput(divider, 'Rmeas', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Rmeas')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-32 text-sm">固定抵抗 (Ω)</label>
          <input :value="numericInputValue(divider, 'fixedResistor')" @input="setNumericInput(divider, 'fixedResistor', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('fixedResistor')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="grid grid-cols-2 gap-3">
          <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">測定対象 min</span>
            <input :value="numericInputValue(divider, 'targetTempMin')" @input="setNumericInput(divider, 'targetTempMin', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('temp')" @blur="clearDiagramFocus"
              class="input-text w-full font-mono" /></label>
          <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">測定対象 max</span>
            <input :value="numericInputValue(divider, 'targetTempMax')" @input="setNumericInput(divider, 'targetTempMax', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('temp')" @blur="clearDiagramFocus"
              class="input-text w-full font-mono" /></label>
        </div>
        <div class="grid grid-cols-3 gap-3 rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
          <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">固定公差</span>
            <input :value="numericInputValue(divider, 'fixedResistorTolerancePct')" @input="setNumericInput(divider, 'fixedResistorTolerancePct', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('fixedResistor')" @blur="clearDiagramFocus"
              class="input-text w-full font-mono" /></label>
          <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">Rth公差</span>
            <input :value="numericInputValue(divider, 'thermistorTolerancePct')" @input="setNumericInput(divider, 'thermistorTolerancePct', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('R0')" @blur="clearDiagramFocus"
              class="input-text w-full font-mono" /></label>
          <label class="block"><span class="mb-1 block text-xs font-semibold opacity-60">放熱定数 (mW/℃)</span>
            <input :value="numericInputValue(divider, 'dissipationMwPerC')" @input="setNumericInput(divider, 'dissipationMwPerC', $event, 1e-3, false)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('temp')" @blur="clearDiagramFocus"
              class="input-text w-full font-mono" /></label>
        </div>
      </div>
      <div class="space-y-4">
        <div class="grid gap-3 md:grid-cols-4">
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
            <div class="text-xs opacity-60">換算温度</div>
            <div class="mt-1 font-mono text-xl font-bold">@{{ dividerResult.temp_c }} °C</div>
          </div>
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
            <div class="text-xs opacity-60">測定電圧</div>
            <div class="mt-1 font-mono text-xl font-bold">@{{ dividerResult.voltage_v }}</div>
          </div>
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
            <div class="text-xs opacity-60">ADCコード</div>
            <div class="mt-1 font-mono text-xl font-bold">@{{ dividerResult.adc_code }}</div>
          </div>
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
            <div class="text-xs opacity-60">対象範囲感度</div>
            <div class="mt-1 font-mono text-sm font-bold">@{{ dividerGraph.targetSensitivity }}</div>
          </div>
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
            <div class="text-xs opacity-60">対象範囲損失</div>
            <div class="mt-1 font-mono text-sm font-bold">@{{ dividerGraph.targetPower }}</div>
          </div>
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
            <div class="text-xs opacity-60">自己発熱</div>
            <div class="mt-1 font-mono text-sm font-bold">@{{ dividerGraph.targetSelfHeat }}</div>
          </div>
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
            <div class="text-xs opacity-60">公差起因温度振れ</div>
            <div class="mt-1 font-mono text-sm font-bold">@{{ dividerGraph.targetToleranceTempError }}</div>
          </div>
          <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
            <div class="text-xs opacity-60">公差電圧幅</div>
            <div class="mt-1 font-mono text-sm font-bold">@{{ dividerGraph.targetVoltageToleranceBand }}</div>
          </div>
        </div>

        <section class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">温度-電圧 / 感度</div>
              <h3 class="mt-1 text-sm font-bold">測定対象範囲と高感度域</h3>
            </div>
            <span class="tag text-[10px]"
              :class="{
                'tag-ok': dividerGraph.sensitivityTone === 'ok',
                'tag-warning': dividerGraph.sensitivityTone === 'warn',
                'tag-eol': dividerGraph.sensitivityTone === 'bad'
              }">
              @{{ dividerGraph.sensitivityTone === 'ok' ? '感度良好' : (dividerGraph.sensitivityTone === 'warn' ? '感度要確認' : '感度不足') }}
            </span>
          </div>
          <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] opacity-80">
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[var(--color-tag-ok)]"></span>緑点: 入力中のRth測定点</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[var(--color-tag-warning)]"></span>黄点: 感度最大点</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-0.5 w-5 bg-[var(--color-primary)]"></span>温度-電圧</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-0.5 w-5 bg-[var(--color-tag-warning)]"></span>感度</span>
          </div>
          <svg class="w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)]" viewBox="0 0 640 260" role="img" aria-label="NTC/PTC温度-電圧グラフ"
            @mousemove="updateDividerGraphCursor" @mouseleave="clearDividerGraphCursor">
            <rect :x="dividerGraph.targetBand.x" y="22" :width="dividerGraph.targetBand.width" height="218" fill="var(--color-primary)" opacity="0.10"></rect>
            <line x1="52" y1="22" x2="52" y2="174" class="circuit-wire"></line>
            <line x1="52" y1="174" x2="592" y2="174" class="circuit-wire"></line>
            <line x1="52" y1="240" x2="592" y2="240" class="circuit-wire"></line>
            <text x="18" y="30" class="circuit-note">@{{ dividerGraph.axis.vref }}</text>
            <text x="22" y="178" class="circuit-note">@{{ dividerGraph.axis.zero }}</text>
            <text x="52" y="192" text-anchor="middle" class="circuit-note">@{{ dividerGraph.axis.minTemp }}</text>
            <text x="592" y="192" text-anchor="middle" class="circuit-note">@{{ dividerGraph.axis.maxTemp }}</text>
            <text x="52" y="255" text-anchor="middle" class="circuit-note">感度</text>
            <polyline :points="dividerGraph.toleranceMinPoints" fill="none" stroke="var(--color-tag-warning)" stroke-width="1.6" stroke-dasharray="6 5" opacity="0.85"></polyline>
            <polyline :points="dividerGraph.toleranceMaxPoints" fill="none" stroke="var(--color-tag-warning)" stroke-width="1.6" stroke-dasharray="6 5" opacity="0.85"></polyline>
            <polyline :points="dividerGraph.points" fill="none" stroke="var(--color-primary)" stroke-width="3"></polyline>
            <polyline :points="dividerGraph.sensitivityPoints" fill="none" stroke="var(--color-tag-warning)" stroke-width="2"></polyline>
            <circle v-if="dividerGraph.measuredPoint" :cx="dividerGraph.measuredPoint.x" :cy="dividerGraph.measuredPoint.y" r="5" fill="var(--color-tag-ok)"></circle>
            <circle :cx="dividerGraph.bestPoint.x" :cy="dividerGraph.bestPoint.y" r="4" fill="var(--color-tag-warning)"></circle>
            <g v-if="dividerGraphCursor.active && dividerGraphTooltip" pointer-events="none">
              <line :x1="dividerGraphCursor.x" y1="22" :x2="dividerGraphCursor.x" y2="240" stroke="var(--color-border)" stroke-width="1" stroke-dasharray="4 4"></line>
              <circle :cx="dividerGraphCursor.x" :cy="dividerGraphCursor.y" r="4" fill="var(--color-primary)"></circle>
              <rect :x="dividerGraphTooltip.x" :y="dividerGraphTooltip.y" width="126" height="56" rx="6" class="circuit-box"></rect>
              <text :x="dividerGraphTooltip.x + 8" :y="dividerGraphTooltip.y + 16" class="circuit-note">T @{{ dividerGraphTooltip.temp }}</text>
              <text :x="dividerGraphTooltip.x + 8" :y="dividerGraphTooltip.y + 32" class="circuit-note">V @{{ dividerGraphTooltip.voltage }}</text>
              <text :x="dividerGraphTooltip.x + 8" :y="dividerGraphTooltip.y + 48" class="circuit-note">S @{{ dividerGraphTooltip.sensitivity }}</text>
            </g>
          </svg>
          <div class="mt-3 grid gap-2 md:grid-cols-2">
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
              <div class="text-xs opacity-60">最大感度</div>
              <div class="mt-1 font-mono text-sm font-bold">@{{ dividerGraph.bestSensitivity }}</div>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
              <div class="text-xs opacity-60">低感度域</div>
              <div class="mt-1 font-mono text-sm font-bold">@{{ dividerGraph.lowSensitivityRange }}</div>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
              <div class="text-xs opacity-60">高感度域</div>
              <div class="mt-1 font-mono text-sm font-bold">@{{ dividerGraph.highSensitivityRange }}</div>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
              <div class="text-xs opacity-60">固定抵抗候補</div>
              <div class="mt-1 font-mono text-xs font-bold">@{{ dividerGraph.candidateSensitivity.map((item) => item.label).join(' / ') }}</div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
