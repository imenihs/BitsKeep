{{-- バッテリー稼働時間ツールの前提条件サマリを表示するpartial。 --}}
  <section v-if="activeToolId === 'battery-runtime'" data-review-stage="spec" class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
      <div>
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">仕様・前提確認</div>
        <h2 class="mt-1 text-sm font-bold">バッテリー稼働の前提</h2>
        <p class="mt-1 text-xs leading-5 opacity-60">電池種別、セル数、下限電圧、周期負荷の終端条件を先に揃えてから稼働時間を判定します。</p>
      </div>
      <span class="tag text-[10px]">@{{ activeTool?.label }}</span>
    </div>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="text-[11px] font-semibold opacity-50">電池構成</div>
        <div class="mt-1 text-sm font-bold">@{{ batteryProfiles[battery.type]?.label || battery.type }} / @{{ battery.cellCount }}セル</div>
        <p class="mt-1 text-xs opacity-60">満充電 @{{ batteryResult.standardFullVoltage }} V / 公称 @{{ batteryResult.standardNominalVoltage }} V</p>
      </div>
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="text-[11px] font-semibold opacity-50">終止条件</div>
        <div class="mt-1 text-sm font-bold">下限 @{{ battery.systemMinVoltage }} V</div>
        <p class="mt-1 text-xs opacity-60">放電曲線とピーク負荷の内部抵抗降下から交点を出します。</p>
      </div>
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="text-[11px] font-semibold opacity-50">周期条件</div>
        <div class="mt-1 text-sm font-bold">@{{ battery.cycleSec }} s周期 / @{{ battery.loads.length }}負荷</div>
        <p class="mt-1 text-xs opacity-60">負荷側電力を変換効率で電池側電力へ換算します。</p>
      </div>
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="text-[11px] font-semibold opacity-50">要求稼働時間</div>
        <div class="mt-1 text-sm font-bold">@{{ battery.requiredHours || 0 }} h</div>
        <p class="mt-1 text-xs opacity-60">要求未入力時は実効稼働時間と不足条件だけを確認します。</p>
      </div>
    </div>
  </section>
