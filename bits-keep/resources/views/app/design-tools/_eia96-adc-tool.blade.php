{{-- EIA-96早見表とADCスケーリング入力を表示するpartial。 --}}
  <!-- ══════ EIA-96 早見表 ══════ -->
  <div v-if="activeToolId === 'eia96'" data-review-stage="tool-input" class="mb-6">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div>
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">チップ抵抗マーキング</div>
        <h2 class="mt-1 text-lg font-bold">チップ抵抗器 EIA-96コード早見表</h2>
        <p class="mt-1 text-sm opacity-70">2桁インデックス + 倍率文字を抵抗値へ変換し、倍率別の早見表と抵抗値からの近傍候補を確認します。</p>
      </div>
      <div class="flex flex-wrap gap-2 text-xs">
        <span class="tag">01C = 10 kΩ</span>
        <span class="tag">R/S/H別表記対応</span>
      </div>
    </div>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(340px,1.05fr)]">
      <div class="space-y-4">
        <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">EIA-96コード</span>
              <input v-model="eia96.codeQuery" @focus="focusDiagram('code')" @blur="clearDiagramFocus()" type="text" autocomplete="off" class="input-text w-full font-mono uppercase" placeholder="01C" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">逆引き抵抗値</span>
              <input v-model="eia96.valueQuery" @focus="focusDiagram('value')" @blur="clearDiagramFocus()" type="text" autocomplete="off" class="input-text w-full font-mono" placeholder="10k / 24.3k" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">倍率文字</span>
              <select v-model="eia96.selectedMultiplier" @focus="focusDiagram('multiplier')" @blur="clearDiagramFocus()" class="input-text w-full font-mono">
                <option v-for="mult in eia96Multipliers" :key="mult.letter" :value="mult.letter">@{{ mult.label }} / @{{ mult.range }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold opacity-60">表内検索</span>
              <input v-model="eia96.searchQuery" type="text" autocomplete="off" class="input-text w-full font-mono" placeholder="68X / 49.9 / 100k" />
            </label>
          </div>
          <div class="mt-4 rounded border p-3"
            :class="eia96Lookup.valid ? 'border-[var(--color-tag-ok)] bg-[var(--color-bg)]' : 'border-[var(--color-tag-warning)] bg-[var(--color-card-odd)]'">
            <div class="grid gap-3 sm:grid-cols-3">
              <div>
                <div class="text-xs opacity-50">コード</div>
                <div class="mt-1 font-mono text-xl font-bold">@{{ eia96Lookup.valid ? eia96Lookup.normalized : '--' }}</div>
              </div>
              <div>
                <div class="text-xs opacity-50">抵抗値</div>
                <div class="mt-1 font-mono text-xl font-bold">@{{ eia96Lookup.valid ? eia96Lookup.display : '--' }}</div>
              </div>
              <div>
                <div class="text-xs opacity-50">内訳</div>
                <div class="mt-1 font-mono text-sm">@{{ eia96Lookup.valid ? `${eia96Lookup.baseCode}=${eia96Lookup.baseValue} x ${eia96Lookup.multiplierFactor}` : '01-96 + Z/Y/X/A/B/C/D/E/F' }}</div>
              </div>
            </div>
            <p v-for="warning in eia96Lookup.warnings" :key="warning" class="mt-2 text-xs text-[var(--color-tag-warning)]">@{{ warning }}</p>
          </div>
        </div>

        <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-bold">抵抗値から近いEIA-96コード</h3>
            <span class="tag text-[10px]">@{{ eia96.valueQuery || '未入力' }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
              <thead class="opacity-60">
                <tr><th class="py-1 pr-3">コード</th><th class="py-1 pr-3">抵抗値</th><th class="py-1 pr-3">誤差</th><th class="py-1 pr-3">別表記</th></tr>
              </thead>
              <tbody>
                <tr v-for="row in eia96ReverseMatches" :key="`rev-${row.code}`" class="border-t border-[var(--color-border)]">
                  <td class="py-1.5 pr-3 font-mono font-semibold">@{{ row.code }}</td>
                  <td class="py-1.5 pr-3 font-mono">@{{ row.display }}</td>
                  <td class="py-1.5 pr-3 font-mono">@{{ row.errorPct.toFixed(3) }}%</td>
                  <td class="py-1.5 pr-3 font-mono">@{{ row.aliasCodes.join(' / ') || '-' }}</td>
                </tr>
                <tr v-if="!eia96ReverseMatches.length"><td colspan="4" class="py-3 text-center opacity-50">逆引き抵抗値を入力してください。</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
          <h3 class="mb-3 text-sm font-bold">倍率文字</h3>
          <div class="grid gap-2 sm:grid-cols-3">
            <button v-for="mult in eia96Multipliers" :key="mult.letter" type="button" @click="eia96.selectedMultiplier = mult.letter"
              class="rounded border px-3 py-2 text-left text-xs hover:bg-[var(--color-card-odd)]"
              :class="eia96MultiplierBySelected.letter === mult.letter ? 'border-[var(--color-tag-ok)] bg-[var(--color-bg)]' : 'border-[var(--color-border)]'">
              <div class="font-mono font-bold">@{{ mult.label }}</div>
              <div class="mt-1 opacity-60">@{{ mult.range }}</div>
            </button>
          </div>
        </div>

        <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-bold">倍率別早見表 @{{ eia96MultiplierBySelected.label }}</h3>
            <span class="tag text-[10px]">@{{ eia96MultiplierBySelected.range }}</span>
          </div>
          <div class="max-h-[34rem] overflow-auto rounded border border-[var(--color-border)] bg-[var(--color-bg)]">
            <table class="min-w-full text-left text-xs">
              <thead class="sticky top-0 bg-[var(--color-card-even)]">
                <tr><th class="px-3 py-2">番号</th><th class="px-3 py-2">コード</th><th class="px-3 py-2">抵抗値</th><th class="px-3 py-2">基準値</th></tr>
              </thead>
              <tbody>
                <tr v-for="row in eia96SelectedRows" :key="row.code" class="border-t border-[var(--color-border)]">
                  <td class="px-3 py-1.5 font-mono">@{{ row.baseCode }}</td>
                  <td class="px-3 py-1.5 font-mono font-semibold">@{{ row.code }}</td>
                  <td class="px-3 py-1.5 font-mono">@{{ row.display }}</td>
                  <td class="px-3 py-1.5 font-mono opacity-70">@{{ row.baseValue }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="eia96.searchQuery" class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-bold">全倍率検索結果</h3>
            <span class="tag text-[10px]">@{{ eia96FilteredRows.length }}件</span>
          </div>
          <div class="grid gap-2 sm:grid-cols-2">
            <div v-for="row in eia96FilteredRows" :key="`filter-${row.code}`" class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-2 text-xs">
              <div class="flex items-center justify-between gap-2">
                <span class="font-mono font-bold">@{{ row.code }}</span>
                <span class="font-mono">@{{ row.display }}</span>
              </div>
              <div class="mt-1 font-mono opacity-60">基準 @{{ row.baseCode }}=@{{ row.baseValue }} / @{{ row.multiplierLabel }}</div>
            </div>
            <div v-if="!eia96FilteredRows.length" class="rounded border border-[var(--color-border)] p-3 text-center text-xs opacity-60">一致するコードがありません。</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ ADCスケーリング ══════ -->
  <div v-if="activeToolId === 'adc'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">ADCコード/スケーリング設計</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">分解能 (bits)</label>
          <input :value="numericInputValue(adc, 'bits')" @input="setNumericInput(adc, 'bits', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('adc')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">Vref (V)</label>
          <input :value="numericInputValue(adc, 'vref')" @input="setNumericInput(adc, 'vref', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('vref')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">入力電圧 Vin (V)</label>
          <input :value="numericInputValue(adc, 'vin')" @input="setNumericInput(adc, 'vin', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('vin')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">オフセット (V)</label>
          <input :value="numericInputValue(adc, 'offset')" @input="setNumericInput(adc, 'offset', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('offset')" @blur="clearDiagramFocus()"
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
