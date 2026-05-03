{{-- 保護素子、ロジック、配線系などの共通クイック解析フォームを表示するpartial。 --}}
  <!-- ══════ 共通クイック解析フォーム ══════ -->
  <div v-if="quickTool" data-review-stage="quick-input" class="mb-6">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <div>
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">パラメータ入力 / 結果プレビュー</div>
        <h2 class="mt-1 text-lg font-bold">@{{ quickTool.title }}</h2>
      </div>
      <span class="tag text-[10px]">@{{ activeTool?.label }}</span>
    </div>
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
                outputType: '出力'
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
            <input v-else :value="numericInputValue(quickForms[quickTool.model], field.key)" @input="setNumericInput(quickForms[quickTool.model], field.key, $event, field.storedUnitFactor, field.forceUnitConversion)"

              @focus="focusDiagram(field.diagramKey || field.key)" @blur="clearDiagramFocus()"
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
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">結果プレビュー</div>
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
