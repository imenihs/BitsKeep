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
<div id="app" data-page="design-tools" data-tool="adc" class="px-4 py-4 sm:px-6 sm:py-6 max-w-6xl mx-auto">
  @include('partials.app-breadcrumbs', ['items' => [['label' => '設計解析ツール', 'current' => true]]])

  <header class="mb-6 pb-4 border-b border-[var(--color-border)]">
    <h1 class="text-2xl font-bold">設計解析ツール</h1>
    <p class="text-sm opacity-60 mt-1">電子回路設計支援ツール（全てリアルタイム計算）</p>
  </header>

  <!-- ツールタブ -->
  <div class="flex flex-wrap gap-1 mb-6 pb-2 border-b border-[var(--color-border)]">
    <button v-for="t in tools" :key="t.id" @click="activeToolId = t.id"
      :class="activeToolId === t.id ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-card-odd)] hover:opacity-90'"
      class="px-3 py-1.5 rounded text-sm transition-colors border border-[var(--color-border)]">
      @{{ t.label }}
    </button>
  </div>

  <!-- ══════ ADCスケーリング ══════ -->
  <div v-if="activeToolId === 'adc'">
    <h2 class="font-bold text-lg mb-4">ADCコード/スケーリング設計</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">分解能 (bits)</label>
          <input v-model.number="adc.bits" type="number" min="6" max="24"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">Vref (V)</label>
          <input v-model.number="adc.vref" type="number" step="0.01"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">入力電圧 Vin (V)</label>
          <input v-model.number="adc.vin" type="number" step="0.01"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">オフセット (V)</label>
          <input v-model.number="adc.offset" type="number" step="0.01"
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
        <p v-if="adcResult.clipped" class="text-red-500 text-xs mt-2">⚠ 入力がレンジ外です（クリッピング）</p>
      </div>
    </div>
  </div>

  <!-- ══════ コンデンサ寿命 ══════ -->
  <div v-if="activeToolId === 'cap-life'">
    <h2 class="font-bold text-lg mb-4">電解コンデンサ寿命推定（アレニウス則）</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div v-for="[key, label, step] in [['L0','定格寿命 L₀ (h)',100],['T0','定格温度 T₀ (°C)',5],['T','動作温度 T (°C)',1],['Vr','定格電圧 Vr (V)',1],['V','動作電圧 V (V)',1]]" :key="key"
          class="flex items-center gap-3">
          <label class="w-32 text-sm">@{{ label }}</label>
          <input v-model.number="cap[key]" type="number" :step="step"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">推定寿命</span>
          <span class="font-mono font-bold text-xl">@{{ capResult.life_h.toLocaleString() }} h</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">年換算</span>
          <span class="font-mono font-bold">@{{ capResult.life_y }} 年</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">温度係数</span>
          <span class="font-mono">× @{{ capResult.temp_factor }}</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">電圧係数</span>
          <span class="font-mono">× @{{ capResult.voltage_factor }}</span></div>
        <p class="text-xs opacity-50 mt-2">※ アレニウス則 + 電圧加速則（n=3）による簡易推定</p>
      </div>
    </div>
  </div>

  <!-- ══════ 分圧・温度変換 ══════ -->
  <div v-if="activeToolId === 'divider'">
    <h2 class="font-bold text-lg mb-4">抵抗分圧 / NTC温度変換</h2>
    <div class="flex gap-4 mb-4">
      <label class="flex items-center gap-2 cursor-pointer">
        <input v-model="divider.mode" type="radio" value="voltage" />
        <span class="text-sm">電圧分圧</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input v-model="divider.mode" type="radio" value="ntc" />
        <span class="text-sm">NTC温度計算</span>
      </label>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-if="divider.mode === 'voltage'" class="space-y-3">
        <div class="flex items-center gap-3"><label class="w-24 text-sm">Vin (V)</label>
          <input v-model.number="divider.vin" type="number" step="0.1"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-24 text-sm">R1 (Ω)</label>
          <input v-model.number="divider.r1" type="number"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-24 text-sm">R2 (Ω)</label>
          <input v-model.number="divider.r2" type="number"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div v-else class="space-y-3">
        <div class="flex items-center gap-3"><label class="w-28 text-sm">R₀ @ T₀ (Ω)</label>
          <input v-model.number="divider.R0" type="number"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">T₀ (°C)</label>
          <input v-model.number="divider.T0" type="number"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">B定数</label>
          <input v-model.number="divider.B" type="number"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">測定抵抗 (Ω)</label>
          <input v-model.number="divider.Rmeas" type="number"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <template v-if="divider.mode === 'voltage'">
          <div class="flex justify-between"><span class="opacity-60 text-sm">出力電圧 Vout</span>
            <span class="font-mono font-bold text-xl">@{{ dividerResult.vout }} V</span></div>
          <div class="flex justify-between"><span class="opacity-60 text-sm">分圧比</span>
            <span class="font-mono">@{{ dividerResult.ratio }}%</span></div>
        </template>
        <template v-else>
          <div class="flex justify-between"><span class="opacity-60 text-sm">温度</span>
            <span class="font-mono font-bold text-xl">@{{ dividerResult.temp_c }} °C</span></div>
          <div class="flex justify-between"><span class="opacity-60 text-sm">絶対温度</span>
            <span class="font-mono">@{{ dividerResult.temp_k }} K</span></div>
        </template>
      </div>
    </div>
  </div>

  <!-- ══════ 電流検出 ══════ -->
  <div v-if="activeToolId === 'shunt'">
    <h2 class="font-bold text-lg mb-4">電流検出解析（シャント抵抗）</h2>
    <div class="flex gap-4 mb-4">
      <label class="flex items-center gap-2 cursor-pointer">
        <input v-model="shunt.mode" type="radio" value="from_vout" />
        <span class="text-sm">Vout → 電流</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input v-model="shunt.mode" type="radio" value="from_current" />
        <span class="text-sm">電流 → Vout</span>
      </label>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="flex items-center gap-3"><label class="w-28 text-sm">Rs (Ω)</label>
          <input v-model.number="shunt.Rs" type="number" step="0.001"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">アンプゲイン</label>
          <input v-model.number="shunt.gain" type="number" step="1"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div v-if="shunt.mode === 'from_vout'" class="flex items-center gap-3"><label class="w-28 text-sm">Vout (V)</label>
          <input v-model.number="shunt.Vout" type="number" step="0.001"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div v-else class="flex items-center gap-3"><label class="w-28 text-sm">電流 I (A)</label>
          <input v-model.number="shunt.I" type="number" step="0.1"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <template v-if="shunt.mode === 'from_vout'">
          <div class="flex justify-between"><span class="opacity-60 text-sm">電流 I</span>
            <span class="font-mono font-bold text-xl">@{{ shuntResult.I }} A</span></div>
          <div class="flex justify-between"><span class="opacity-60 text-sm">シャント電圧</span>
            <span class="font-mono">@{{ shuntResult.Vshunt_mv }} mV</span></div>
        </template>
        <template v-else>
          <div class="flex justify-between"><span class="opacity-60 text-sm">Vout</span>
            <span class="font-mono font-bold text-xl">@{{ shuntResult.Vout }} V</span></div>
          <div class="flex justify-between"><span class="opacity-60 text-sm">シャント電圧</span>
            <span class="font-mono">@{{ shuntResult.Vshunt_mv }} mV</span></div>
        </template>
        <div class="flex justify-between"><span class="opacity-60 text-sm">シャント損失</span>
          <span class="font-mono">@{{ shuntResult.P_mW }} mW</span></div>
      </div>
    </div>
  </div>

  <!-- ══════ 電源余裕 ══════ -->
  <div v-if="activeToolId === 'power'">
    <h2 class="font-bold text-lg mb-4">電源余裕解析</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">供給電力 (W)</label>
          <input v-model.number="power.supply_w" type="number" step="0.1"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(l, i) in power.loads" :key="i" class="flex items-center gap-2">
            <input v-model="l.label" type="text" placeholder="名称"
              class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input v-model.number="l.mA" type="number" step="1" placeholder="mA"
              class="w-20 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">mA @</span>
            <input v-model.number="l.V" type="number" step="0.1"
              class="w-16 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">V</span>
            <button @click="removeLoad(i)" class="text-red-400 hover:text-red-600 text-sm">✕</button>
          </div>
        </div>
        <button @click="addLoad" class="text-xs px-3 py-1.5 border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">+ 追加</button>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">消費電力合計</span>
          <span class="font-mono font-bold text-xl">@{{ powerResult.totalW }} W</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">余裕</span>
          <span class="font-mono font-bold" :class="powerResult.ok ? 'text-emerald-600' : 'text-red-500'">
            @{{ powerResult.margin }} W
          </span></div>
        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
          <div class="h-2 rounded-full transition-all"
            :class="powerResult.ok ? 'bg-emerald-500' : 'bg-red-500'"
            :style="{ width: Math.min(100, parseFloat(powerResult.percent)) + '%' }"></div>
        </div>
        <div class="text-xs text-center opacity-60">@{{ powerResult.percent }}% 使用</div>
        <p v-if="!powerResult.ok" class="text-red-500 text-xs">⚠ 供給電力を超過しています</p>
      </div>
    </div>
  </div>

  <!-- ══════ 比較器 ══════ -->
  <div v-if="activeToolId === 'comparator'">
    <h2 class="font-bold text-lg mb-4">比較器しきい値/ヒステリシス</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div v-for="[key, label, step] in [['Vcc','Vcc (V)',0.1],['Vref','Vref入力 (V)',0.01],['R1','R1 (Ω)',1000],['R2','R2 (Ω)',1000],['R3','R3フィードバック (Ω, 0=なし)',1000]]" :key="key"
          class="flex items-center gap-3">
          <label class="w-36 text-sm">@{{ label }}</label>
          <input v-model.number="comp[key]" type="number" :step="step"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="flex justify-between"><span class="opacity-60 text-sm">High → Low しきい値</span>
          <span class="font-mono font-bold text-lg">@{{ compResult.Vth_high }} V</span></div>
        <div class="flex justify-between"><span class="opacity-60 text-sm">Low → High しきい値</span>
          <span class="font-mono font-bold text-lg">@{{ compResult.Vth_low }} V</span></div>
        <div class="flex justify-between border-t border-[var(--color-border)] pt-2"><span class="opacity-60 text-sm">ヒステリシス幅</span>
          <span class="font-mono font-bold">@{{ compResult.hysteresis }} V</span></div>
      </div>
    </div>
  </div>

  <!-- ══════ 熱設計 ══════ -->
  <div v-if="activeToolId === 'thermal'">
    <h2 class="font-bold text-lg mb-4">熱設計 / 熱抵抗チェーン</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="flex items-center gap-3 mb-3">
          <label class="w-32 text-sm font-medium">消費電力 (W)</label>
          <input v-model.number="thermal.P" type="number" step="0.1"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">雰囲気温度 (°C)</label>
          <input v-model.number="thermal.Tambient" type="number" step="1"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(n, i) in thermal.nodes" :key="i" class="flex items-center gap-2">
            <input v-model="n.label" type="text"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input v-model.number="n.Rth" type="number" step="0.1" placeholder="θ(°C/W)"
              class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">°C/W</span>
            <button @click="removeNode(i)" class="text-red-400 hover:text-red-600">✕</button>
          </div>
        </div>
        <button @click="addNode" class="text-xs px-3 py-1.5 border border-[var(--color-border)] rounded hover:bg-[var(--color-card-odd)]">+ 熱抵抗追加</button>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4">
        <div class="flex justify-between mb-3"><span class="opacity-60 text-sm">接合部温度 Tj</span>
          <span class="font-mono font-bold text-xl" :class="parseFloat(thermalResult.Tjunction) > 125 ? 'text-red-500' : 'text-emerald-600'">
            @{{ thermalResult.Tjunction }} °C
          </span></div>
        <div class="text-xs opacity-60 mb-2">温度チェーン（入力端→接合部）:</div>
        <div class="space-y-1">
          <div v-for="n in thermalResult.cumulative" :key="n.label" class="flex justify-between text-xs">
            <span class="opacity-70">@{{ n.label }}</span>
            <span class="font-mono">@{{ n.T }} °C</span>
          </div>
        </div>
        <p v-if="parseFloat(thermalResult.Tjunction) > 125" class="text-red-500 text-xs mt-2">⚠ Tj が 125°C を超えています</p>
      </div>
    </div>
  </div>

  <!-- ══════ インタフェース余裕 ══════ -->
  <div v-if="activeToolId === 'interface'">
    <h2 class="font-bold text-lg mb-4">インタフェース電圧余裕解析</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <p class="text-xs font-medium opacity-60">出力側（ドライバ）</p>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VOH (V)</label>
          <input v-model.number="iface.VOH" type="number" step="0.01"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VOL (V)</label>
          <input v-model.number="iface.VOL" type="number" step="0.01"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <p class="text-xs font-medium opacity-60 pt-2">入力側（レシーバ）</p>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VIH (V)</label>
          <input v-model.number="iface.VIH" type="number" step="0.01"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VIL (V)</label>
          <input v-model.number="iface.VIL" type="number" step="0.01"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-3">
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
        <p v-if="!ifaceResult.high_ok || !ifaceResult.low_ok" class="text-red-500 text-xs mt-2">
          ⚠ 余裕がありません。電圧レベル変換が必要な可能性があります。
        </p>
      </div>
    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '設計解析ツール', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
