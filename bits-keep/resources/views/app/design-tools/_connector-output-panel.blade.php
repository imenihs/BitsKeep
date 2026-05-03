{{-- コネクタ割当結果、テンプレート、ピン表を表示するpartial。 --}}
  <section v-if="activeToolId === 'connector'" data-review-stage="connector-output" class="mt-5 mb-6 grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
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
            <input :value="numericInputValue(quickForms.connector, 'userTemplatePins')" @input="setNumericInput(quickForms.connector, 'userTemplatePins', $event)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">列数</span>
            <input :value="numericInputValue(quickForms.connector, 'userTemplateRows')" @input="setNumericInput(quickForms.connector, 'userTemplateRows', $event)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">ピッチ (m)</span>
            <input :value="numericInputValue(quickForms.connector, 'userTemplatePitchMm')" @input="setNumericInput(quickForms.connector, 'userTemplatePitchMm', $event, 1e-3, true)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">1pin定格(A)</span>
            <input :value="numericInputValue(quickForms.connector, 'userTemplateCurrentRatingPerPin')" @input="setNumericInput(quickForms.connector, 'userTemplateCurrentRatingPerPin', $event)" class="input-text w-full text-xs font-mono" />
          </label>
          <label class="block">
            <span class="mb-1 block text-[11px] opacity-60">定格電圧(V)</span>
            <input :value="numericInputValue(quickForms.connector, 'userTemplateVoltageRatingV')" @input="setNumericInput(quickForms.connector, 'userTemplateVoltageRatingV', $event)" class="input-text w-full text-xs font-mono" />
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
