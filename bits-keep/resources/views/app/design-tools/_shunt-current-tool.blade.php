{{-- シャント抵抗の電流検出入力、ADCコード、余裕結果を表示するpartial。 --}}
  <!-- ══════ 電流検出 ══════ -->
  <div v-if="activeToolId === 'shunt'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">電流検出解析（シャント抵抗）</h2>
    <div class="mb-4 grid gap-3 lg:grid-cols-2">
      <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
        <div class="mb-2 text-xs font-semibold opacity-60">回路方式</div>
        <div class="flex flex-wrap gap-2">
          <label class="flex cursor-pointer items-center gap-2 rounded border border-[var(--color-border)] px-3 py-2"
            :class="shunt.senseMode === 'unipolar' ? 'bg-[var(--color-primary)] text-white' : ''">
            <input v-model="shunt.senseMode" type="radio" value="unipolar" class="sr-only" />
            <span class="text-sm font-semibold">片方向: オフセットなし</span>
          </label>
          <label class="flex cursor-pointer items-center gap-2 rounded border border-[var(--color-border)] px-3 py-2"
            :class="shunt.senseMode === 'bidirectional' ? 'bg-[var(--color-primary)] text-white' : ''">
            <input v-model="shunt.senseMode" type="radio" value="bidirectional" class="sr-only" />
            <span class="text-sm font-semibold">双方向: オフセットあり</span>
          </label>
        </div>
      </div>
      <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
        <div class="mb-2 text-xs font-semibold opacity-60">シミュレーション入力</div>
        <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2 text-sm font-semibold">
          電流 → Vshunt → Vout → ADCコード
        </div>
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="grid gap-3">
        <div class="flex items-center gap-3"><label class="w-36 text-sm">Rs シャント抵抗 (Ω)</label>
          <input :value="numericInputValue(shunt, 'Rs')" @input="setNumericInput(shunt, 'Rs', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Rs')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-36 text-sm">Rs電力定格 (W)</label>
          <input :value="numericInputValue(shunt, 'powerRating')" @input="setNumericInput(shunt, 'powerRating', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Rs')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">アンプゲイン</label>
          <input :value="numericInputValue(shunt, 'gain')" @input="setNumericInput(shunt, 'gain', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('gain')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="flex items-center gap-3"><label class="w-28 text-sm">測定電流 最小 (A)</label>
            <input :value="numericInputValue(shunt, 'currentMin')" @input="setNumericInput(shunt, 'currentMin', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('I')" @blur="clearDiagramFocus()"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
          <div class="flex items-center gap-3"><label class="w-28 text-sm">測定電流 最大 (A)</label>
            <input :value="numericInputValue(shunt, 'currentMax')" @input="setNumericInput(shunt, 'currentMax', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('I')" @blur="clearDiagramFocus()"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        </div>
        <div v-if="shunt.senseMode === 'bidirectional'" class="flex items-center gap-3"><label class="w-36 text-sm">0A出力オフセット (V)</label>
          <input :value="numericInputValue(shunt, 'outputOffset')" @input="setNumericInput(shunt, 'outputOffset', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Vzero')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-36 text-sm">シミュレーション電流 (A)</label>
          <input :value="numericInputValue(shunt, 'I')" @input="setNumericInput(shunt, 'I', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('I')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="flex items-center gap-3"><label class="w-28 text-sm">ADCビット数</label>
            <input :value="numericInputValue(shunt, 'adcBits')" @input="setNumericInput(shunt, 'adcBits', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('Vout')" @blur="clearDiagramFocus()"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
          <div class="flex items-center gap-3"><label class="w-28 text-sm">ADC上限 (V)</label>
            <input :value="numericInputValue(shunt, 'adcVref')" @input="setNumericInput(shunt, 'adcVref', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('Vout')" @blur="clearDiagramFocus()"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="flex items-center gap-3"><label class="w-28 text-sm">AMP下限 (V)</label>
            <input :value="numericInputValue(shunt, 'ampOutputMin')" @input="setNumericInput(shunt, 'ampOutputMin', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('Vout')" @blur="clearDiagramFocus()"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
          <div class="flex items-center gap-3"><label class="w-28 text-sm">AMP上限 (V)</label>
            <input :value="numericInputValue(shunt, 'ampOutputMax')" @input="setNumericInput(shunt, 'ampOutputMax', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('Vout')" @blur="clearDiagramFocus()"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        </div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">シミュレーション電流</span>
          <span class="font-mono font-bold text-xl">@{{ shuntResult.I }} A</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">Vshunt</span>
          <span class="font-mono">@{{ shuntResult.Vshunt_mv }} mV</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">Vout</span>
          <span class="font-mono font-bold text-xl">@{{ shuntResult.Vout }} V</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">ADCコード</span>
          <span class="font-mono">@{{ shuntResult.adc_code }} / @{{ shuntResult.adc_bin }}</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">ADCコード位置</span>
          <span class="font-mono">@{{ shuntResult.adc_code_pct }} %</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">シャント損失</span>
          <span class="font-mono">@{{ shuntResult.P_mW }} mW</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">レンジ最大損失</span>
          <span class="font-mono">@{{ shuntResult.range_P_mW }} mW</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">Rs定格余裕</span>
          <span class="font-mono font-semibold" :class="parseNumber(shuntResult.power_margin_mw) < 0 ? 'text-red-500' : 'text-emerald-600'">
            @{{ shuntResult.power_margin_mw }} mW
          </span></div>
        <div v-if="parseNumber(shuntResult.power_margin_mw) < 0" class="rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-700">
          <div class="font-semibold">FAIL理由: Rs電力定格超過</div>
          <p class="mt-1">Voutレンジが成立していても、測定レンジ最大電流でシャント損失が定格を超えています。Rs電力定格、Rs値、電流レンジを見直してください。</p>
        </div>
        <div class="grid grid-cols-2 gap-2 pt-2 text-xs">
          <div class="rounded border border-[var(--color-border)] p-2">
            <div class="opacity-60">最小電流時アンプ出力</div>
            <div class="font-mono font-semibold">@{{ shuntResult.vout_at_imin_v }} V</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-2">
            <div class="opacity-60">最大電流時アンプ出力</div>
            <div class="font-mono font-semibold">@{{ shuntResult.vout_at_imax_v }} V</div>
          </div>
        </div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">入力換算オフセット</span>
          <span class="font-mono">@{{ shuntResult.offset_error_a }} A</span></div>
        <div class="grid grid-cols-2 gap-2 pt-2 text-xs">
          <div class="rounded border border-[var(--color-border)] p-2">
            <div class="opacity-60">ADCレンジ使用率</div>
            <div class="font-mono font-semibold">@{{ shuntResult.adc_range_used_pct }} %</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-2">
            <div class="opacity-60">アンプ出力レンジ使用率</div>
            <div class="font-mono font-semibold">@{{ shuntResult.amp_range_used_pct }} %</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-2">
            <div class="opacity-60">ADC下限余裕</div>
            <div class="font-mono font-semibold">@{{ shuntResult.adc_range_margin_low_v }} V</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-2">
            <div class="opacity-60">ADC上限余裕</div>
            <div class="font-mono font-semibold">@{{ shuntResult.adc_range_margin_high_v }} V</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-2">
            <div class="opacity-60">アンプ出力下限余裕</div>
            <div class="font-mono font-semibold">@{{ shuntResult.amp_range_margin_low_v }} V</div>
          </div>
          <div class="rounded border border-[var(--color-border)] p-2">
            <div class="opacity-60">アンプ出力上限余裕</div>
            <div class="font-mono font-semibold">@{{ shuntResult.amp_range_margin_high_v }} V</div>
          </div>
        </div>
      </div>
    </div>
  </div>
