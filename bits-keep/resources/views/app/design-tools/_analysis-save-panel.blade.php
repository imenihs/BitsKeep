<div class="mt-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-3">
  <div class="mb-3">
    <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">次アクション / 保存</div>
    <p class="mt-1 text-xs leading-5 opacity-60">解析メモ名を先に決め、案件・部品は名前や型番から選択して保存します。</p>
  </div>
  <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(14rem,0.7fr)_minmax(16rem,0.8fr)_minmax(9rem,0.35fr)_auto] xl:items-end">
    <label class="block">
      <span class="block text-[11px] font-semibold opacity-60 mb-1">解析メモ名</span>
      <input v-model="outputSave.title" type="text" class="input-text w-full"
        :placeholder="`${activeTool?.label || activeToolId} ${analysisReport.verdict}`" />
    </label>
    <label class="block">
      <span class="block text-[11px] font-semibold opacity-60 mb-1">案件（任意）</span>
      <project-combo-box v-model="outputSave.project" :allow-new="false" placeholder="案件名・事業名・案件番号で検索"></project-combo-box>
    </label>
    <label class="relative block">
      <span class="block text-[11px] font-semibold opacity-60 mb-1">部品（任意）</span>
      <div class="flex items-center gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] px-3 py-2">
        <input v-model="componentSelection.query" @input="onComponentSearchInput" @focus="openComponentSearch"
          type="text" autocomplete="off" class="min-w-0 flex-1 bg-transparent text-sm outline-none"
          placeholder="型番・通称・メーカーで検索" />
        <button v-if="outputSave.componentId" type="button" @click.stop="clearComponentSelection" class="text-xs opacity-50 hover:opacity-80">クリア</button>
        <span v-else-if="componentSelection.loading" class="text-xs opacity-50">検索中</span>
      </div>
      <div v-if="componentSelection.open"
        class="absolute left-0 right-0 z-50 mt-1 max-h-72 overflow-y-auto rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] shadow-lg">
        <button v-for="component in componentSelection.options" :key="component.id" type="button"
          @mousedown.prevent="selectComponentCandidate(component)"
          class="block w-full border-b border-[var(--color-border)] px-4 py-2.5 text-left text-sm last:border-0 hover:bg-[var(--color-card-odd)]">
          <span class="block truncate font-semibold">@{{ componentLabel(component) }}</span>
          <span class="block truncate text-xs opacity-60">#@{{ component.id }} / 在庫 @{{ (component.quantity_new || 0) + (component.quantity_used || 0) }} pcs</span>
        </button>
        <div v-if="!componentSelection.loading && !componentSelection.options.length && !componentSelection.error" class="px-4 py-3 text-sm opacity-50">候補がありません。</div>
        <div v-if="componentSelection.error" class="px-4 py-3 text-sm text-[var(--color-tag-eol)]">@{{ componentSelection.error }}</div>
      </div>
    </label>
    <label class="block">
      <span class="block text-[11px] font-semibold opacity-60 mb-1">BOM行</span>
      <input v-model="outputSave.bomLineKey" type="text" class="input-text w-full font-mono" />
    </label>
    <button type="button" @click="saveAnalysisReport" :disabled="outputSave.saving"
      class="rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)] disabled:cursor-not-allowed disabled:opacity-50">
      @{{ outputSave.saving ? '保存中...' : '解析セッション保存' }}
    </button>
  </div>

  <div class="mt-4 text-[11px] uppercase tracking-[0.18em] opacity-50">入力へ戻す操作 / 保存済み確認</div>
  <div class="mt-2 grid gap-3 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.3fr)_minmax(0,0.85fr)]">
    <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
      <div class="text-xs font-semibold opacity-60">入力へ反映: 登録部品</div>
      <button type="button" @click="loadComponentContext" :disabled="componentImport.loading || !outputSave.componentId"
        class="mt-2 rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)] disabled:cursor-not-allowed disabled:opacity-50">
        @{{ componentImport.loading ? '読込中...' : '部品から取り込み' }}
      </button>
      <div v-if="componentImport.component || outputSave.componentId" class="mt-2 text-xs leading-5">
        <div class="font-semibold">@{{ loadedComponentName }}</div>
        <div v-if="componentImport.component" class="opacity-60">在庫 @{{ loadedComponentStock }} pcs / スペック件数 @{{ componentImport.component.specs?.length || 0 }}</div>
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
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-xs font-semibold opacity-60">保存済み解析一覧</div>
        <button type="button" @click="loadSavedAnalysis" :disabled="savedAnalysis.loading"
          class="rounded border border-[var(--color-border)] px-3 py-2 text-xs font-semibold hover:bg-[var(--color-card-odd)] disabled:cursor-not-allowed disabled:opacity-50">
          @{{ savedAnalysis.loading ? '取得中...' : '保存一覧を取得' }}
        </button>
      </div>
      <div v-if="savedAnalysis.sessions.length" class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-1">
        <div v-for="session in savedAnalysis.sessions" :key="session.id"
          class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3 text-xs"
          :class="savedAnalysis.selectedId === session.id ? 'border-[var(--color-primary)]' : ''">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="break-words font-semibold">@{{ session.title }}</div>
              <div class="mt-1 break-words opacity-65">@{{ session.summary || 'サマリなし' }}</div>
            </div>
            <span class="tag text-[10px]">@{{ session.verdict || session.result_payload?.verdict || 'CHECK' }}</span>
          </div>
          <div class="mt-2 grid gap-1 font-mono text-[11px] opacity-70 sm:grid-cols-2">
            <div>案件: @{{ sessionProjectLabel(session) }}</div>
            <div>部品: @{{ sessionComponentLabel(session) }}</div>
            <div>BOM: @{{ session.bom_line_key || '-' }}</div>
            <div>更新: @{{ sessionUpdatedAtLabel(session) }}</div>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" @click="selectSavedAnalysis(session)"
              class="rounded border border-[var(--color-border)] px-2 py-1 font-semibold hover:bg-[var(--color-card-odd)]">差分表示</button>
            <button type="button" @click="restoreSavedAnalysis(session)"
              class="rounded border border-[var(--color-border)] px-2 py-1 font-semibold hover:bg-[var(--color-card-odd)]">入力へ復元</button>
            <button type="button" @click="beginDeleteSavedAnalysis(session)"
              class="rounded border border-[var(--color-tag-eol)] px-2 py-1 font-semibold text-[var(--color-tag-eol)] hover:bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))]">削除</button>
          </div>
        </div>
      </div>
      <div v-if="savedAnalysis.diff" class="mt-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3 text-xs leading-5">
        <div class="font-semibold">@{{ savedAnalysis.diff.title }}</div>
        <div class="opacity-60">@{{ savedAnalysis.diff.previousVerdict }} → @{{ savedAnalysis.diff.currentVerdict }}</div>
        <ul v-if="savedAnalysis.diff.changes.length" class="mt-1 list-disc pl-4 opacity-70">
          <li v-for="change in savedAnalysis.diff.changes" :key="change">@{{ change }}</li>
        </ul>
        <p v-else class="mt-1 opacity-60">入力条件に差分はありません。</p>
      </div>
      <p v-if="savedAnalysis.status === 'success'" class="mt-2 text-xs text-[var(--color-tag-ok)]">@{{ savedAnalysis.message }}</p>
      <div v-if="savedAnalysis.status === 'error'" class="mt-2 rounded-lg border border-[var(--color-tag-eol)] bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))] p-3 text-xs">
        <div class="font-semibold text-[var(--color-tag-eol)]">一覧取得に失敗しました</div>
        <p class="mt-1 opacity-80">@{{ savedAnalysis.error }}</p>
        <button type="button" @click="loadSavedAnalysis" class="mt-2 rounded border border-[var(--color-tag-eol)] px-2 py-1">再試行</button>
      </div>
      <div v-if="savedAnalysis.error && savedAnalysis.status !== 'error'" class="mt-2 rounded-lg border border-[var(--color-tag-eol)] bg-[color-mix(in_srgb,var(--color-tag-eol)_8%,var(--color-bg))] p-3 text-xs">
        <div class="font-semibold text-[var(--color-tag-eol)]">操作に失敗しました</div>
        <p class="mt-1 opacity-80">@{{ savedAnalysis.error }}</p>
      </div>
    </div>

    <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
      <div class="text-xs font-semibold opacity-60">入力へ戻す: 解析テンプレート</div>
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

<div v-if="savedAnalysis.deleteTarget" class="modal-overlay" v-esc="cancelDeleteSavedAnalysis">
  <div class="modal-window modal-sm" @click.stop>
    <div class="p-5">
      <h3 class="text-lg font-bold">保存済み解析を削除</h3>
      <p class="mt-2 text-sm leading-6 opacity-75">
        「@{{ savedAnalysis.deleteTarget.title }}」を削除します。復元対象からも外れます。
      </p>
      <div class="mt-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-card-even)] p-3 text-xs leading-5">
        <div>判定: @{{ savedAnalysis.deleteTarget.verdict || savedAnalysis.deleteTarget.result_payload?.verdict || 'CHECK' }}</div>
        <div>更新: @{{ sessionUpdatedAtLabel(savedAnalysis.deleteTarget) }}</div>
      </div>
      <div class="mt-5 flex justify-end gap-3">
        <button type="button" @click="cancelDeleteSavedAnalysis" class="rounded border border-[var(--color-border)] px-4 py-2 text-sm">キャンセル</button>
        <button type="button" @click="deleteSavedAnalysis" :disabled="savedAnalysis.deletingId === savedAnalysis.deleteTarget.id"
          class="rounded bg-[var(--color-tag-eol)] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
          @{{ savedAnalysis.deletingId === savedAnalysis.deleteTarget.id ? '削除中...' : '削除する' }}
        </button>
      </div>
    </div>
  </div>
</div>
