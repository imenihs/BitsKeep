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
<div id="app" data-page="design-tools" data-tool="" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
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
  <div class="mb-4 border-b border-[var(--color-border)] pb-2">
    <div class="mb-3">
      <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <div>
          <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">設計目的</div>
          <p class="mt-1 text-xs opacity-60">@{{ selectedToolGroup.desc }}</p>
        </div>
        <button type="button" @click="isToolOrderEditing = !isToolOrderEditing"
          :disabled="activeToolGroup !== 'all'"
          class="rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] px-3 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40">
          @{{ isToolOrderEditing ? '並び替え完了' : '並び替え' }}
        </button>
      </div>
      <div class="flex flex-wrap gap-1" role="group" aria-label="設計目的で絞り込み">
        <button v-for="group in toolGroups" :key="group.id" type="button" @click="setToolGroup(group.id)"
          :class="activeToolGroup === group.id ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-card-odd)] hover:opacity-90'"
          class="rounded border border-[var(--color-border)] px-3 py-1.5 text-xs font-semibold transition-colors">
          @{{ group.label }}
        </button>
      </div>
    </div>
    <div v-if="!activeToolInVisibleGroup" class="mb-2 rounded-lg border border-[var(--color-tag-warning)] bg-[color-mix(in_srgb,var(--color-tag-warning)_8%,var(--color-bg))] px-3 py-2 text-xs">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span>現在のツール「@{{ activeTool?.label }}」は、選択中の設計目的には含まれていません。</span>
        <button type="button" @click="setToolGroup('all')" class="rounded border border-[var(--color-tag-warning)] px-2 py-1 font-semibold">
          すべて表示
        </button>
      </div>
    </div>
    <div v-if="activeToolGroup !== 'all' && isToolOrderEditing" class="mb-2 text-xs opacity-60">
      並び替えは「すべて」表示で操作できます。
    </div>
    <div class="flex flex-wrap gap-1" role="tablist" aria-label="設計解析ツール">
      <div v-for="(t, index) in visibleTools" :key="t.id" class="flex overflow-hidden rounded border border-[var(--color-border)]">
        <button type="button" role="tab" :aria-selected="activeToolId === t.id" @click="activeToolId = t.id"
          :class="activeToolId === t.id ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-card-odd)] hover:opacity-90'"
          class="px-3 py-1.5 text-sm transition-colors">
          @{{ t.label }}
        </button>
        <button v-if="isToolOrderEditing && activeToolGroup === 'all'" type="button" @click.stop="moveToolTab(t.id, -1)" :disabled="index === 0"
          class="border-l border-[var(--color-border)] bg-[var(--color-card-even)] px-2 text-xs disabled:opacity-30"
          aria-label="左へ移動" title="左へ移動">←</button>
        <button v-if="isToolOrderEditing && activeToolGroup === 'all'" type="button" @click.stop="moveToolTab(t.id, 1)" :disabled="index === visibleTools.length - 1"
          class="border-l border-[var(--color-border)] bg-[var(--color-card-even)] px-2 text-xs disabled:opacity-30"
          aria-label="右へ移動" title="右へ移動">→</button>
      </div>
      <button v-if="isToolOrderEditing && activeToolGroup === 'all'" type="button" @click="resetToolOrder"
        class="rounded border border-[var(--color-border)] bg-[var(--color-card-odd)] px-3 py-1.5 text-xs">
        初期状態へ戻す
      </button>
    </div>
  </div>
  <!-- アクティブツールの説明 -->
  <p v-if="activeTool?.desc" class="text-xs opacity-60 mb-5">@{{ activeTool.desc }}</p>

  <section class="mb-5 rounded-xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-3">
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="(step, index) in (workflow?.length ? workflow : [
          { label: '仕様・前提確認', desc: '要求値、使用条件、不足条件をそろえる' },
          { label: 'パラメータ入力', desc: '設計値、定格、公差、最悪条件を入れる' },
          { label: '結果確認', desc: '判定、余裕、支配要因を確認する' },
          { label: '次アクション', desc: '見直し項目や保存・比較へ進む' },
        ])"
        :key="step.key || step.label || index"
        class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
        <div class="flex items-center gap-2">
          <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[var(--color-primary)] text-[10px] font-bold text-white">@{{ index + 1 }}</span>
          <span class="text-xs font-bold">@{{ step.label || step.title || step.name }}</span>
        </div>
        <p v-if="step.desc || step.description || step.text" class="mt-1 text-[11px] leading-4 opacity-60">
          @{{ step.desc || step.description || step.text }}
        </p>
      </div>
    </div>
  </section>

  @include('app.design-tools._passive-network-tool')

  @include('app.design-tools._battery-runtime-spec')

  @include('app.design-tools._circuit-context-panel')

  @include('app.design-tools._eia96-adc-tool')

  @include('app.design-tools._quick-tool-panel')

  @include('app.design-tools._connector-output-panel')

  <!-- ══════ コンデンサ寿命 ══════ -->
  <div v-if="activeToolId === 'cap-life'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">電解コンデンサ寿命推定（アレニウス則）</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div v-for="[key, label, step, diagramKey] in [['L0','定格寿命 L₀ (h)',100,'L0'],['T0','定格温度 T₀ (°C)',5,'L0'],['T','動作温度 T (°C)',1,'T'],['Vr','定格電圧 Vr (V)',1,'V'],['V','動作電圧 V (V)',1,'V']]" :key="key"
          class="flex items-center gap-3">
          <label class="w-32 text-sm">@{{ label }}</label>
          <input :value="numericInputValue(cap, key)" @input="setNumericInput(cap, key, $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram(diagramKey)" @blur="clearDiagramFocus()"
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

  @include('app.design-tools._thermistor-divider-tool')

  @include('app.design-tools._shunt-current-tool')

  <!-- ══════ 電源余裕 ══════ -->
  <div v-if="activeToolId === 'power'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">電源余裕解析</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">供給電力 (W)</label>
          <input :value="numericInputValue(power, 'supply_w')" @input="setNumericInput(power, 'supply_w', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('supply')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(l, i) in power.loads" :key="i" class="flex items-center gap-2">
            <input v-model="l.label" type="text" placeholder="名称"
              @focus="focusDiagram('loads')" @blur="clearDiagramFocus"
              class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input :value="numericInputValue(l, 'mA')" @input="setNumericInput(l, 'mA', $event, 1e-3, true)" type="text" inputmode="decimal" autocomplete="off" placeholder="50m"
              @focus="focusDiagram('loads')" @blur="clearDiagramFocus()"
              class="w-20 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">A @</span>
            <input :value="numericInputValue(l, 'V')" @input="setNumericInput(l, 'V', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('loads')" @blur="clearDiagramFocus()"
              class="w-16 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">V</span>
            <input v-model="l.rail" type="text" placeholder="レール"
              @focus="focusDiagram('loads')" @blur="clearDiagramFocus"
              class="w-20 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <button @click="removeLoad(i)" class="text-red-400 hover:text-red-600 text-sm">✕</button>
          </div>
        </div>
        <button @click="addLoad" class="text-xs px-3 py-1.5 border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">+ 追加</button>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">消費電力合計</span>
          <span class="font-mono font-bold text-xl">@{{ powerResult.totalW }} W</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">通常余裕</span>
          <span class="font-mono font-bold" :class="powerResult.capacityExceeded ? 'text-red-500' : 'text-emerald-600'">
            @{{ powerResult.margin }} W
          </span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">上流換算負荷</span>
          <span class="font-mono">@{{ powerResult.inputEquivalentW }} W</span></div>
        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
          <div class="h-2 rounded-full transition-all"
            :class="powerResult.capacityExceeded ? 'bg-red-500' : 'bg-emerald-500'"
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
        <div v-if="powerResult.capacityExceeded" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-700">
          <div class="font-semibold">電源余裕の警告</div>
          <p v-if="powerResult.supplyExceeded" class="mt-1">供給電力を超過しています。負荷または供給電力を見直してください。</p>
          <p v-else class="mt-1">いずれかのレール容量を超過しています。レール定格または負荷割当を見直してください。</p>
          <button type="button" @click="activeToolId = 'power'" class="mt-2 rounded border border-red-300 px-2 py-1">再確認</button>
        </div>
      </div>
    </div>
  </div>

  @include('app.design-tools._battery-runtime-tool')

  <!-- ══════ 比較器 ══════ -->
  <div v-if="activeToolId === 'comparator'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">比較器しきい値/ヒステリシス</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
          <button type="button" @click="comp.inputPolarity = 'positive'; focusDiagram('inputPolarity')"
            class="rounded border px-3 py-1.5 text-sm"
            :class="comp.inputPolarity !== 'negative' ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-[var(--color-border)]'">+入力(非反転)</button>
          <button type="button" @click="comp.inputPolarity = 'negative'; focusDiagram('inputPolarity')"
            class="rounded border px-3 py-1.5 text-sm"
            :class="comp.inputPolarity === 'negative' ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-[var(--color-border)]'">-入力(反転)</button>
          <button type="button" @click="comp.referenceMode = 'external'; focusDiagram('Vref')"
            class="rounded border px-3 py-1.5 text-sm"
            :class="comp.referenceMode === 'external' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-[var(--color-border)]'">Vref方式</button>
          <button type="button" @click="comp.referenceMode = 'divider'; focusDiagram('R2')"
            class="rounded border px-3 py-1.5 text-sm"
            :class="comp.referenceMode === 'divider' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-[var(--color-border)]'">Vcc分圧方式</button>
        </div>
        <div v-for="[key, label, diagramKey] in [['Vcc','Vcc 電源電圧 (V)','Vcc'],['VOH','出力High電圧 (V)','out'],['VOL','出力Low電圧 (V)','out'],['R1','R1 Vin側抵抗 (Ω)','R1'],['R3','R3 OUT帰還抵抗 (Ω, 0=なし)','R3']]" :key="key" class="flex items-center gap-3">
          <label class="w-40 text-sm">@{{ key === 'R1' ? (comp.inputPolarity === 'negative' ? (comp.referenceMode === 'divider' ? 'R1 ショート扱い' : 'R1 基準側抵抗 (Ω)') : 'R1 Vin側抵抗 (Ω)') : label }}</label>
          <input :value="key === 'R1' && comp.inputPolarity === 'negative' && comp.referenceMode === 'divider' ? '0Ω (short)' : numericInputValue(comp, key)"
            @input="key === 'R1' && comp.inputPolarity === 'negative' && comp.referenceMode === 'divider' ? null : setNumericInput(comp, key, $event)" type="text" inputmode="decimal" autocomplete="off"
            :disabled="key === 'R1' && comp.inputPolarity === 'negative' && comp.referenceMode === 'divider'"
            @focus="focusDiagram(diagramKey)" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono disabled:opacity-50" />
        </div>
        <div v-if="comp.referenceMode === 'external'" class="flex items-center gap-3">
          <label class="w-40 text-sm">Vref 基準電圧 (V)</label>
          <input :value="numericInputValue(comp, 'Vref')" @input="setNumericInput(comp, 'Vref', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Vref')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="flex items-center gap-3">
            <label class="w-32 text-sm">R2 Vcc側 (Ω)</label>
            <input :value="numericInputValue(comp, 'R2')" @input="setNumericInput(comp, 'R2', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('R2')" @blur="clearDiagramFocus()"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
          </div>
          <div class="flex items-center gap-3">
            <label class="w-32 text-sm">R4 GND側 (Ω)</label>
            <input :value="numericInputValue(comp, 'R4')" @input="setNumericInput(comp, 'R4', $event)" type="text" inputmode="decimal" autocomplete="off"
              @focus="focusDiagram('R4')" @blur="clearDiagramFocus()"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
          </div>
        </div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="mb-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3 font-mono text-xs">
          <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 text-center">
            <div>@{{ comp.inputPolarity === 'negative' ? 'Vin→V-' : 'Vin→R1→V+' }}</div><div>OUT→R3→V+</div><div>@{{ comp.inputPolarity === 'negative' ? '入力は反転' : '入力は非反転' }}</div>
            <div class="col-span-3 border-t border-[var(--color-border)]"></div>
            <div>@{{ comp.referenceMode === 'divider' ? 'Vcc→R2/R4' : 'Vref' }}</div><div>@{{ comp.inputPolarity === 'negative' ? 'R1→V+ 基準' : '基準入力 V-' }}</div><div>@{{ comp.inputPolarity === 'negative' ? 'V+しきい値' : 'V-基準' }}</div>
          </div>
        </div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">Vin上昇時しきい値</span>
          <span class="font-mono font-bold text-lg">@{{ compResult.Vth_rising }} V</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">出力遷移</span>
          <span class="font-mono">@{{ compResult.rising_transition }}</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">Vin下降時しきい値</span>
          <span class="font-mono font-bold text-lg">@{{ compResult.Vth_falling }} V</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">出力遷移</span>
          <span class="font-mono">@{{ compResult.falling_transition }}</span></div>
        <div class="flex justify-between border-t border-[var(--color-border)] pt-2"><span class="opacity-60 text-sm">ヒステリシス幅</span>
          <span class="font-mono font-bold">@{{ compResult.hysteresis }} V</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">モデル</span>
          <span class="font-mono">@{{ compResult.topology }}</span></div>
      </div>
    </div>
  </div>

  <!-- ══════ 熱設計 ══════ -->
  <div v-if="activeToolId === 'thermal'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">熱設計 / 熱抵抗チェーン</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="flex items-center gap-3 mb-3">
          <label class="w-32 text-sm font-medium">消費電力 (W)</label>
          <input :value="numericInputValue(thermal, 'P')" @input="setNumericInput(thermal, 'P', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('P')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">周囲温度 Ta (°C)</label>
          <input :value="numericInputValue(thermal, 'Tambient')" @input="setNumericInput(thermal, 'Tambient', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Tambient')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">Tj閾値 (°C)</label>
          <input :value="numericInputValue(thermal, 'TjLimit')" @input="setNumericInput(thermal, 'TjLimit', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('Tj')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(n, i) in thermal.nodes" :key="i" class="flex items-center gap-2">
            <input v-model="n.label" type="text"
              @focus="focusDiagram('nodes')" @blur="clearDiagramFocus"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input :value="numericInputValue(n, 'Rth')" @input="setNumericInput(n, 'Rth', $event)" type="text" inputmode="decimal" autocomplete="off" placeholder="θ(°C/W)"
              @focus="focusDiagram('nodes')" @blur="clearDiagramFocus()"
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
        <div class="text-xs opacity-60 mb-2">温度チェーン（接合部→周囲）:</div>
        <div class="space-y-1">
          <div v-for="n in thermalResult.cumulative" :key="n.label" class="flex justify-between text-xs">
            <span class="opacity-70">@{{ n.label }}</span>
            <span class="font-mono">@{{ n.hot }} → @{{ n.cold }} °C / Δ@{{ n.drop }} °C</span>
          </div>
        </div>
        <div v-if="!thermalResult.ok" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-700">
          <div class="font-semibold">熱設計の警告</div>
          <p class="mt-1">Tj が閾値を超えています。熱抵抗、消費電力、周囲温度を見直してください。</p>
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
  <div v-if="activeToolId === 'interface'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">インタフェース電圧余裕解析</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
          <div class="mb-2 text-xs font-semibold opacity-60">シリーズ接続</div>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
              <span class="mb-1 block text-[11px] opacity-60">送信側シリーズ</span>
              <select v-model="iface.driverFamily" class="input-text w-full text-sm">
                <option v-for="option in logicConnectionFamilyOptions" :key="`drv-${option[0]}`" :value="option[0]">@{{ option[1] }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-[11px] opacity-60">送信側Vcc (V)</span>
              <input :value="numericInputValue(iface, 'driverVcc')" @input="setNumericInput(iface, 'driverVcc', $event)" type="text" inputmode="decimal" autocomplete="off"
                @focus="focusDiagram('VOH')" @blur="clearDiagramFocus()"
                class="input-text w-full font-mono text-sm" />
            </label>
            <label class="block">
              <span class="mb-1 block text-[11px] opacity-60">受信側シリーズ</span>
              <select v-model="iface.receiverFamily" class="input-text w-full text-sm">
                <option v-for="option in logicConnectionFamilyOptions" :key="`rcv-${option[0]}`" :value="option[0]">@{{ option[1] }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-[11px] opacity-60">受信側Vcc (V)</span>
              <input :value="numericInputValue(iface, 'receiverVcc')" @input="setNumericInput(iface, 'receiverVcc', $event)" type="text" inputmode="decimal" autocomplete="off"
                @focus="focusDiagram('VIH')" @blur="clearDiagramFocus()"
                class="input-text w-full font-mono text-sm" />
            </label>
          </div>
        </div>
        <p class="text-xs font-medium opacity-60">出力側（ドライバ）</p>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VOH (V)</label>
          <input :value="numericInputValue(iface, 'VOH')" @input="setNumericInput(iface, 'VOH', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('VOH')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VOL (V)</label>
          <input :value="numericInputValue(iface, 'VOL')" @input="setNumericInput(iface, 'VOL', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('VOL')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <p class="text-xs font-medium opacity-60 pt-2">入力側（レシーバ）</p>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VIH (V)</label>
          <input :value="numericInputValue(iface, 'VIH')" @input="setNumericInput(iface, 'VIH', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('VIH')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VIL (V)</label>
          <input :value="numericInputValue(iface, 'VIL')" @input="setNumericInput(iface, 'VIL', $event)" type="text" inputmode="decimal" autocomplete="off"
            @focus="focusDiagram('VIL')" @blur="clearDiagramFocus()"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-3">
        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="flex items-center justify-between gap-3">
            <span class="text-sm opacity-60">シリーズ接続</span>
            <span class="font-mono text-sm font-semibold" :class="ifaceResult.logic_level_verdict === 'FAIL' ? 'text-red-500' : (ifaceResult.logic_level_verdict === 'WARN' ? 'text-amber-600' : 'text-emerald-600')">
              @{{ ifaceResult.logic_level_verdict }}
            </span>
          </div>
          <div class="mt-2 font-mono text-xs">@{{ ifaceResult.logic_pair }}</div>
          <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
            <div><span class="opacity-60">H</span> <span class="font-mono">@{{ ifaceResult.logic_high_margin }} V</span></div>
            <div><span class="opacity-60">L</span> <span class="font-mono">@{{ ifaceResult.logic_low_margin }} V</span></div>
            <div><span class="opacity-60">耐圧</span> <span class="font-mono">@{{ ifaceResult.logic_input_overvoltage_margin }} V</span></div>
          </div>
        </div>
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

  <section v-if="toolSupplementInputGroups.length" data-review-stage="tool-input" class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <div>
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">パラメータ入力</div>
        <h2 class="mt-1 text-sm font-bold">@{{ activeTool?.label }} 追加条件</h2>
      </div>
      <span class="tag text-[10px]">1条件1入力</span>
    </div>
    <div class="grid gap-3 lg:grid-cols-4">
      <div v-for="group in toolSupplementInputGroups" :key="group.label" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
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
            <input v-else :value="numericInputValue(item.target, item.key)" @input="setNumericInput(item.target, item.key, $event, item.storedUnitFactor, item.forceUnitConversion)"
              @focus="focusDiagram(item.diagramKey || item.key)" @blur="clearDiagramFocus"
              type="text" inputmode="decimal" autocomplete="off" class="input-text w-full font-mono text-xs" />
          </label>
        </div>
      </div>
    </div>
  </section>

  @include('app.design-tools._analysis-result-panel')
</div>
</body>
</html>
