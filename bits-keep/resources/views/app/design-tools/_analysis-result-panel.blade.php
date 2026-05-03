{{-- 設計判定、指標、未評価条件、保存フォームを表示するpartial。 --}}
  <section v-if="analysisReport" data-review-stage="result" class="mb-6 rounded-2xl border bg-[var(--color-card-even)] p-4"
    :class="{
      'border-[var(--color-tag-ok)]': analysisReport.tone === 'ok',
      'border-[var(--color-tag-warning)]': analysisReport.tone === 'warn',
      'border-[var(--color-tag-eol)]': analysisReport.tone === 'bad',
      'border-[var(--color-border)]': analysisReport.tone === 'neutral'
    }">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-[11px] uppercase tracking-[0.18em] opacity-50">最終判定 / 結果確認</span>
          <span class="tag"
            :class="{
              'tag-ok': analysisReport.tone === 'ok' && analysisReport.verdict !== 'CHECK',
              'tag-warning': analysisReport.tone === 'warn',
              'tag-eol': analysisReport.tone === 'bad',
              'border border-[var(--color-tag-warning)] text-[var(--color-tag-warning)]': analysisReport.verdict === 'CHECK'
            }">@{{ analysisReport.verdict }}</span>
        </div>
        <div class="mt-2 space-y-1 text-sm font-semibold leading-6">
          <p v-for="(line, index) in analysisReport.summaryLines" :key="`summary-line-${index}`" class="break-words">
            @{{ line }}
          </p>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <button type="button" @click="copyAnalysisSummary"
            class="rounded border border-[var(--color-border)] px-3 py-1.5 text-xs font-semibold hover:bg-[var(--color-card-odd)]">
            サマリコピー
          </button>
          <button type="button" @click="saveAnalysisReport" :disabled="outputSave.saving"
            class="rounded border border-[var(--color-border)] px-3 py-1.5 text-xs font-semibold hover:bg-[var(--color-card-odd)] disabled:cursor-not-allowed disabled:opacity-50">
            @{{ outputSave.saving ? '保存中...' : '解析セッション保存' }}
          </button>
          <span v-if="outputSave.status === 'success'" class="self-center text-xs text-[var(--color-tag-ok)]">@{{ outputSave.message }}</span>
          <span v-if="outputSave.status === 'error'" class="self-center text-xs text-[var(--color-tag-eol)]">@{{ outputSave.error }}</span>
        </div>
      </div>
      <div v-if="analysisReport.dominantFactors.length" class="shrink-0 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
        <div class="text-[11px] font-semibold opacity-50">支配要因</div>
        <div class="mt-1 flex flex-wrap gap-1">
          <span v-for="factor in analysisReport.dominantFactors" :key="factor" class="tag max-w-[16rem] whitespace-normal break-words text-[10px] leading-4">@{{ factor }}</span>
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

    @include('app.design-tools._analysis-save-panel')
  </section>
