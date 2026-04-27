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
  </header>

  <section class="grid gap-3 mb-5 md:grid-cols-3">
    <div v-for="band in hubBands" :key="band.label"
      class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
      <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">@{{ band.label }}</div>
      <div class="mt-1 text-sm font-semibold">@{{ band.value }}</div>
    </div>
  </section>

  <!-- ツールタブ -->
  <div class="flex flex-wrap gap-1 mb-4 pb-2 border-b border-[var(--color-border)]">
    <button v-for="t in tools" :key="t.id" @click="activeToolId = t.id"
      :class="activeToolId === t.id ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-card-odd)] hover:opacity-90'"
      class="px-3 py-1.5 rounded text-sm transition-colors border border-[var(--color-border)]">
      @{{ t.label }}
    </button>
  </div>
  <!-- アクティブツールの説明 -->
  <p v-if="activeTool?.desc" class="text-xs opacity-60 mb-5">@{{ activeTool.desc }}</p>

  <section v-if="activeDiagram" class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(280px,0.75fr)] lg:items-stretch">
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">Circuit Context</div>
            <h2 class="mt-1 text-sm font-bold">@{{ activeDiagram.title }}</h2>
            <p class="mt-1 text-xs leading-5 opacity-60">@{{ activeDiagram.subtitle }}</p>
          </div>
          <span v-if="diagramFocus" class="tag tag-ok">@{{ diagramFocus }}</span>
        </div>

        <svg class="circuit-svg mt-3" viewBox="0 0 640 260" role="img" :aria-label="activeDiagram.title">
          <defs>
            <marker id="circuit-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
              <path d="M 0 0 L 10 5 L 0 10 z" class="circuit-arrow-fill"></path>
            </marker>
          </defs>

          <template v-if="activeDiagram.type === 'divider' || activeDiagram.type === 'divider-ntc'">
            <line x1="180" y1="40" x2="180" y2="68" class="circuit-wire"></line>
            <line x1="180" y1="116" x2="180" y2="146" class="circuit-wire"></line>
            <line x1="180" y1="194" x2="180" y2="216" class="circuit-wire"></line>
            <line x1="180" y1="130" x2="430" y2="130" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass(activeDiagram.keys.input)" @mouseenter="focusDiagram(activeDiagram.keys.input)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.input)">
              <circle cx="180" cy="40" r="18" class="circuit-node"></circle>
              <text x="180" y="44" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'Vin' : 'Rntc' }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.upper)" @mouseenter="focusDiagram(activeDiagram.keys.upper)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.upper)">
              <rect x="155" y="68" width="50" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="97" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'R1' : 'R0' }}</text>
              <text x="222" y="95" class="circuit-note">@{{ activeDiagram.type === 'divider' ? '上側' : '基準' }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.output)" @mouseenter="focusDiagram(activeDiagram.keys.output)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.output)">
              <circle cx="180" cy="130" r="6" class="circuit-junction"></circle>
              <rect x="430" y="105" width="120" height="50" rx="8" class="circuit-box"></rect>
              <text x="490" y="126" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'Vout' : 'Temp' }}</text>
              <text x="490" y="143" text-anchor="middle" class="circuit-note">@{{ activeDiagram.type === 'divider' ? 'ADC/後段へ' : '換算結果' }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.lower)" @mouseenter="focusDiagram(activeDiagram.keys.lower)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.lower)">
              <rect x="155" y="146" width="50" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="175" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'R2' : 'Rntc' }}</text>
              <text x="222" y="173" class="circuit-note">@{{ activeDiagram.type === 'divider' ? '下側' : '測定値' }}</text>
            </g>
            <g>
              <line x1="160" y1="216" x2="200" y2="216" class="circuit-wire"></line>
              <line x1="166" y1="224" x2="194" y2="224" class="circuit-wire"></line>
              <line x1="173" y1="232" x2="187" y2="232" class="circuit-wire"></line>
              <text x="214" y="226" class="circuit-note">GND</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'shunt'">
            <line x1="70" y1="120" x2="170" y2="120" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <line x1="270" y1="120" x2="380" y2="120" class="circuit-wire"></line>
            <g :class="diagramItemClass('I')" @mouseenter="focusDiagram('I')" @mouseleave="clearDiagramFocus" @click="focusDiagram('I')">
              <text x="82" y="100" class="circuit-label">I</text>
              <text x="76" y="143" class="circuit-note">負荷電流</text>
            </g>
            <g :class="diagramItemClass('Rs')" @mouseenter="focusDiagram('Rs')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Rs')">
              <rect x="170" y="96" width="100" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="220" y="124" text-anchor="middle" class="circuit-label">Rs</text>
              <text x="220" y="142" text-anchor="middle" class="circuit-note">シャント</text>
            </g>
            <g :class="diagramItemClass('gain')" @mouseenter="focusDiagram('gain')" @mouseleave="clearDiagramFocus" @click="focusDiagram('gain')">
              <path d="M 355 70 L 455 120 L 355 170 Z" class="circuit-box"></path>
              <text x="385" y="116" text-anchor="middle" class="circuit-label">AMP</text>
              <text x="386" y="134" text-anchor="middle" class="circuit-note">gain</text>
              <line x1="270" y1="102" x2="355" y2="100" class="circuit-wire"></line>
              <line x1="270" y1="138" x2="355" y2="140" class="circuit-wire"></line>
            </g>
            <g :class="diagramItemClass('Vout')" @mouseenter="focusDiagram('Vout')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vout')">
              <line x1="455" y1="120" x2="560" y2="120" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <rect x="560" y="96" width="55" height="48" rx="6" class="circuit-box"></rect>
              <text x="588" y="124" text-anchor="middle" class="circuit-label">Vout</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'comparator'">
            <g :class="diagramItemClass('R1')" @mouseenter="focusDiagram('R1')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R1')">
              <line x1="70" y1="100" x2="145" y2="100" class="circuit-wire"></line>
              <rect x="145" y="78" width="70" height="44" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="105" text-anchor="middle" class="circuit-label">R1</text>
              <text x="94" y="91" class="circuit-note">Vin</text>
            </g>
            <line x1="215" y1="100" x2="290" y2="100" class="circuit-wire"></line>
            <g>
              <path d="M 290 62 L 290 178 L 410 120 Z" class="circuit-box"></path>
              <text x="310" y="104" class="circuit-label">+</text>
              <text x="310" y="151" class="circuit-label">-</text>
              <text x="346" y="124" text-anchor="middle" class="circuit-label">CMP</text>
            </g>
            <g :class="diagramItemClass('Vref')" @mouseenter="focusDiagram('Vref')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vref')">
              <line x1="140" y1="160" x2="290" y2="145" class="circuit-wire"></line>
              <circle cx="140" cy="160" r="18" class="circuit-node"></circle>
              <text x="140" y="164" text-anchor="middle" class="circuit-label">Vref</text>
            </g>
            <g :class="diagramItemClass('R2')" @mouseenter="focusDiagram('R2')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R2')">
              <rect x="176" y="146" width="58" height="34" rx="6" class="circuit-symbol-fill"></rect>
              <text x="205" y="168" text-anchor="middle" class="circuit-label">R2</text>
            </g>
            <g :class="diagramItemClass('out')" @mouseenter="focusDiagram('out')" @mouseleave="clearDiagramFocus" @click="focusDiagram('out')">
              <line x1="410" y1="120" x2="565" y2="120" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <text x="520" y="108" class="circuit-label">OUT</text>
            </g>
            <g :class="diagramItemClass('R3')" @mouseenter="focusDiagram('R3')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R3')">
              <path d="M 510 120 C 510 42 255 42 255 100" class="circuit-wire"></path>
              <rect x="348" y="26" width="70" height="34" rx="6" class="circuit-symbol-fill"></rect>
              <text x="383" y="48" text-anchor="middle" class="circuit-label">R3</text>
              <text x="430" y="44" class="circuit-note">帰還</text>
            </g>
            <g :class="diagramItemClass('Vcc')" @mouseenter="focusDiagram('Vcc')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vcc')">
              <text x="440" y="70" class="circuit-note">Vcc = @{{ comp.Vcc }} V</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'bode'">
            <line x1="70" y1="125" x2="150" y2="125" class="circuit-wire"></line>
            <text x="75" y="108" class="circuit-label">Vin</text>
            <g :class="diagramItemClass(activeDiagram.variant === 'lowpass' ? 'r' : 'c')" @mouseenter="focusDiagram(activeDiagram.variant === 'lowpass' ? 'r' : 'c')" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.variant === 'lowpass' ? 'r' : 'c')">
              <rect x="150" y="101" width="94" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="197" y="130" text-anchor="middle" class="circuit-label">@{{ activeDiagram.variant === 'lowpass' ? 'R' : 'C' }}</text>
            </g>
            <line x1="244" y1="125" x2="390" y2="125" class="circuit-wire"></line>
            <g :class="diagramItemClass('out')" @mouseenter="focusDiagram('out')" @mouseleave="clearDiagramFocus" @click="focusDiagram('out')">
              <circle cx="390" cy="125" r="6" class="circuit-junction"></circle>
              <line x1="390" y1="125" x2="540" y2="125" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <text x="500" y="108" class="circuit-label">Vout</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.variant === 'lowpass' ? 'c' : 'r')" @mouseenter="focusDiagram(activeDiagram.variant === 'lowpass' ? 'c' : 'r')" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.variant === 'lowpass' ? 'c' : 'r')">
              <line x1="390" y1="125" x2="390" y2="158" class="circuit-wire"></line>
              <rect x="365" y="158" width="50" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="390" y="187" text-anchor="middle" class="circuit-label">@{{ activeDiagram.variant === 'lowpass' ? 'C' : 'R' }}</text>
            </g>
            <line x1="370" y1="220" x2="410" y2="220" class="circuit-wire"></line>
            <line x1="376" y1="228" x2="404" y2="228" class="circuit-wire"></line>
            <line x1="383" y1="236" x2="397" y2="236" class="circuit-wire"></line>
            <g :class="diagramItemClass('freq')" @mouseenter="focusDiagram('freq')" @mouseleave="clearDiagramFocus" @click="focusDiagram('freq')">
              <text x="75" y="165" class="circuit-note">評価周波数</text>
              <text x="75" y="183" class="circuit-label">@{{ quickForms.bode.freq }} Hz</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'protection'">
            <g :class="diagramItemClass(activeDiagram.keys.input)" @mouseenter="focusDiagram(activeDiagram.keys.input)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.input)">
              <circle cx="75" cy="120" r="18" class="circuit-node"></circle>
              <text x="75" y="124" text-anchor="middle" class="circuit-label">@{{ activeDiagram.labels.input }}</text>
            </g>
            <line x1="93" y1="120" x2="150" y2="120" class="circuit-wire"></line>
            <g :class="diagramItemClass(activeDiagram.keys.series)" @mouseenter="focusDiagram(activeDiagram.keys.series)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.series)">
              <rect x="150" y="96" width="100" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="200" y="124" text-anchor="middle" class="circuit-label">@{{ activeDiagram.labels.series }}</text>
            </g>
            <line x1="250" y1="120" x2="395" y2="120" class="circuit-wire"></line>
            <g :class="diagramItemClass(activeDiagram.keys.clamp)" @mouseenter="focusDiagram(activeDiagram.keys.clamp)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.clamp)">
              <line x1="330" y1="120" x2="330" y2="160" class="circuit-wire"></line>
              <rect x="304" y="160" width="52" height="44" rx="6" class="circuit-symbol-fill"></rect>
              <text x="330" y="187" text-anchor="middle" class="circuit-label">@{{ activeDiagram.labels.clamp }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.load)" @mouseenter="focusDiagram(activeDiagram.keys.load)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.load)">
              <rect x="395" y="88" width="120" height="64" rx="8" class="circuit-box"></rect>
              <text x="455" y="116" text-anchor="middle" class="circuit-label">@{{ activeDiagram.labels.load }}</text>
              <text x="455" y="136" text-anchor="middle" class="circuit-note">protected node</text>
            </g>
            <line x1="515" y1="120" x2="585" y2="120" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <line x1="310" y1="218" x2="350" y2="218" class="circuit-wire"></line>
            <line x1="316" y1="226" x2="344" y2="226" class="circuit-wire"></line>
            <line x1="323" y1="234" x2="337" y2="234" class="circuit-wire"></line>
            <text x="362" y="226" class="circuit-note">GND</text>
          </template>

          <template v-else-if="activeDiagram.type === 'power'">
            <g :class="diagramItemClass('supply')" @mouseenter="focusDiagram('supply')" @mouseleave="clearDiagramFocus" @click="focusDiagram('supply')">
              <rect x="55" y="92" width="120" height="72" rx="10" class="circuit-box"></rect>
              <text x="115" y="122" text-anchor="middle" class="circuit-label">Supply</text>
              <text x="115" y="142" text-anchor="middle" class="circuit-note">@{{ power.supply_w }} W</text>
            </g>
            <line x1="175" y1="128" x2="540" y2="128" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('loads')" @mouseenter="focusDiagram('loads')" @mouseleave="clearDiagramFocus" @click="focusDiagram('loads')">
              <g v-for="(load, index) in power.loads.slice(0, 3)" :key="`load-${index}`" :transform="`translate(${245 + index * 110}, 78)`">
                <rect width="86" height="72" rx="8" class="circuit-symbol-fill"></rect>
                <text x="43" y="30" text-anchor="middle" class="circuit-label">@{{ load.label || 'Load' }}</text>
                <text x="43" y="50" text-anchor="middle" class="circuit-note">@{{ load.mA }}mA</text>
              </g>
              <text v-if="power.loads.length > 3" x="570" y="92" class="circuit-note">+@{{ power.loads.length - 3 }}</text>
            </g>
            <g :class="diagramItemClass('margin')" @mouseenter="focusDiagram('margin')" @mouseleave="clearDiagramFocus" @click="focusDiagram('margin')">
              <rect x="260" y="180" width="160" height="42" rx="8" class="circuit-box"></rect>
              <text x="340" y="206" text-anchor="middle" class="circuit-label">margin @{{ powerResult.margin }} W</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'thermal'">
            <g :class="diagramItemClass('Tambient')" @mouseenter="focusDiagram('Tambient')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Tambient')">
              <rect x="45" y="96" width="95" height="62" rx="8" class="circuit-box"></rect>
              <text x="92" y="122" text-anchor="middle" class="circuit-label">Ta</text>
              <text x="92" y="142" text-anchor="middle" class="circuit-note">@{{ thermal.Tambient }} degC</text>
            </g>
            <line x1="140" y1="127" x2="520" y2="127" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('nodes')" @mouseenter="focusDiagram('nodes')" @mouseleave="clearDiagramFocus" @click="focusDiagram('nodes')">
              <g v-for="(node, index) in thermal.nodes.slice(0, 3)" :key="`thermal-${index}`" :transform="`translate(${175 + index * 112}, 86)`">
                <rect width="90" height="82" rx="8" class="circuit-symbol-fill"></rect>
                <text x="45" y="30" text-anchor="middle" class="circuit-label">Rth</text>
                <text x="45" y="50" text-anchor="middle" class="circuit-note">@{{ node.Rth }}</text>
                <text x="45" y="68" text-anchor="middle" class="circuit-note">@{{ node.label.slice(0, 8) }}</text>
              </g>
            </g>
            <g :class="diagramItemClass('P')" @mouseenter="focusDiagram('P')" @mouseleave="clearDiagramFocus" @click="focusDiagram('P')">
              <path d="M 510 92 L 590 127 L 510 162 Z" class="circuit-box"></path>
              <text x="535" y="122" text-anchor="middle" class="circuit-label">P</text>
              <text x="536" y="142" text-anchor="middle" class="circuit-note">@{{ thermal.P }} W</text>
            </g>
            <g :class="diagramItemClass('Tj')" @mouseenter="focusDiagram('Tj')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Tj')">
              <text x="510" y="206" class="circuit-label">Tj @{{ thermalResult.Tjunction }} degC</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'interface'">
            <g>
              <rect x="70" y="78" width="150" height="104" rx="10" class="circuit-box"></rect>
              <text x="145" y="108" text-anchor="middle" class="circuit-label">Driver</text>
            </g>
            <g :class="diagramItemClass('VOH')" @mouseenter="focusDiagram('VOH')" @mouseleave="clearDiagramFocus" @click="focusDiagram('VOH')">
              <text x="110" y="138" class="circuit-label">VOH @{{ iface.VOH }}V</text>
            </g>
            <g :class="diagramItemClass('VOL')" @mouseenter="focusDiagram('VOL')" @mouseleave="clearDiagramFocus" @click="focusDiagram('VOL')">
              <text x="110" y="158" class="circuit-label">VOL @{{ iface.VOL }}V</text>
            </g>
            <line x1="220" y1="130" x2="420" y2="130" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g>
              <rect x="420" y="78" width="150" height="104" rx="10" class="circuit-box"></rect>
              <text x="495" y="108" text-anchor="middle" class="circuit-label">Receiver</text>
            </g>
            <g :class="diagramItemClass('VIH')" @mouseenter="focusDiagram('VIH')" @mouseleave="clearDiagramFocus" @click="focusDiagram('VIH')">
              <text x="455" y="138" class="circuit-label">VIH @{{ iface.VIH }}V</text>
            </g>
            <g :class="diagramItemClass('VIL')" @mouseenter="focusDiagram('VIL')" @mouseleave="clearDiagramFocus" @click="focusDiagram('VIL')">
              <text x="455" y="158" class="circuit-label">VIL @{{ iface.VIL }}V</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'connector'">
            <g :class="diagramItemClass('endA')" @mouseenter="focusDiagram('endA')" @mouseleave="clearDiagramFocus" @click="focusDiagram('endA')">
              <rect x="95" y="78" width="150" height="112" rx="10" class="circuit-box"></rect>
              <text x="170" y="104" text-anchor="middle" class="circuit-label">端A / pin1</text>
              <circle v-for="pin in 5" :key="`a-${pin}`" :cx="125 + pin * 16" cy="132" r="5" class="circuit-junction"></circle>
              <text x="170" y="166" text-anchor="middle" class="circuit-note">mating face</text>
            </g>
            <line x1="245" y1="132" x2="395" y2="132" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('endB')" @mouseenter="focusDiagram('endB')" @mouseleave="clearDiagramFocus" @click="focusDiagram('endB')">
              <rect x="395" y="78" width="150" height="112" rx="10" class="circuit-box"></rect>
              <text x="470" y="104" text-anchor="middle" class="circuit-label">端B / pin1</text>
              <circle v-for="pin in 5" :key="`b-${pin}`" :cx="425 + pin * 16" cy="132" r="5" class="circuit-junction"></circle>
              <text x="470" y="166" text-anchor="middle" class="circuit-note">solder side?</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'startup'">
            <g :class="diagramItemClass('rails')" @mouseenter="focusDiagram('rails')" @mouseleave="clearDiagramFocus" @click="focusDiagram('rails')">
              <rect x="72" y="86" width="92" height="54" rx="8" class="circuit-box"></rect>
              <text x="118" y="118" text-anchor="middle" class="circuit-label">VIN</text>
              <line x1="164" y1="113" x2="250" y2="113" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <rect x="250" y="86" width="92" height="54" rx="8" class="circuit-box"></rect>
              <text x="296" y="118" text-anchor="middle" class="circuit-label">3V3</text>
              <line x1="342" y1="113" x2="428" y2="113" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <rect x="428" y="86" width="92" height="54" rx="8" class="circuit-box"></rect>
              <text x="474" y="118" text-anchor="middle" class="circuit-label">1V8</text>
            </g>
            <g :class="diagramItemClass('reset')" @mouseenter="focusDiagram('reset')" @mouseleave="clearDiagramFocus" @click="focusDiagram('reset')">
              <path d="M 296 140 C 296 186 430 186 430 148" class="circuit-wire" marker-end="url(#circuit-arrow)"></path>
              <rect x="250" y="182" width="140" height="40" rx="8" class="circuit-symbol-fill"></rect>
              <text x="320" y="207" text-anchor="middle" class="circuit-label">RESET / PG</text>
            </g>
          </template>

          <template v-else>
            <g v-for="(block, index) in activeDiagram.blocks" :key="block.key"
              :transform="`translate(${40 + index * 145}, 92)`"
              :class="diagramItemClass(block.key)"
              @mouseenter="focusDiagram(block.key)" @mouseleave="clearDiagramFocus" @click="focusDiagram(block.key)">
              <rect width="112" height="76" rx="10" class="circuit-box"></rect>
              <text x="56" y="34" text-anchor="middle" class="circuit-label">@{{ block.label }}</text>
              <text x="56" y="56" text-anchor="middle" class="circuit-note">@{{ block.sub }}</text>
            </g>
            <g v-if="activeDiagram.blocks?.length > 1">
              <line v-for="index in activeDiagram.blocks.length - 1" :key="`flow-${index}`"
                :x1="40 + (index - 1) * 145 + 112" y1="130"
                :x2="40 + index * 145" y2="130"
                class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            </g>
          </template>
        </svg>
      </div>

      <aside class="rounded-xl border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">Labels / Formula</div>
        <div class="mt-3 flex flex-wrap gap-2">
          <button v-for="part in activeDiagram.parts" :key="part.key" type="button"
            @mouseenter="focusDiagram(part.key)" @mouseleave="clearDiagramFocus" @click="focusDiagram(part.key)"
            class="rounded-lg border border-[var(--color-border)] px-2 py-1 text-left text-xs transition"
            :class="isDiagramFocused(part.key) ? 'border-[var(--color-primary)] bg-[color-mix(in_srgb,var(--color-primary)_10%,var(--color-card-odd))]' : 'bg-[var(--color-bg)]'">
            <span class="block font-semibold">@{{ part.label }}</span>
            <span class="block max-w-[15rem] truncate opacity-60">@{{ part.desc }}</span>
          </button>
        </div>
        <div class="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-[11px] font-semibold opacity-50">式</div>
          <div class="mt-1 break-words font-mono text-xs font-semibold">@{{ activeDiagram.formula }}</div>
        </div>
        <div v-if="activeDiagram.assumptions?.length" class="mt-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
          <div class="text-[11px] font-semibold opacity-50">未評価条件</div>
          <ul class="mt-1 list-disc pl-4 text-xs leading-5 opacity-70">
            <li v-for="item in activeDiagram.assumptions" :key="item">@{{ item }}</li>
          </ul>
        </div>
      </aside>
    </div>
  </section>

  <section v-if="analysisReport" class="mb-6 rounded-2xl border bg-[var(--color-card-even)] p-4"
    :class="{
      'border-[var(--color-tag-ok)]': analysisReport.tone === 'ok',
      'border-[var(--color-tag-warning)]': analysisReport.tone === 'warn',
      'border-[var(--color-tag-eol)]': analysisReport.tone === 'bad',
      'border-[var(--color-border)]': analysisReport.tone === 'neutral'
    }">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-[11px] uppercase tracking-[0.18em] opacity-50">Design Verdict</span>
          <span class="tag"
            :class="{
              'tag-ok': analysisReport.tone === 'ok',
              'tag-warning': analysisReport.tone === 'warn',
              'tag-eol': analysisReport.tone === 'bad'
            }">@{{ analysisReport.verdict }}</span>
        </div>
        <p class="mt-2 text-sm font-semibold leading-6">@{{ analysisReport.summary }}</p>
      </div>
      <div v-if="analysisReport.dominantFactors.length" class="shrink-0 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2">
        <div class="text-[11px] font-semibold opacity-50">支配要因</div>
        <div class="mt-1 flex flex-wrap gap-1">
          <span v-for="factor in analysisReport.dominantFactors" :key="factor" class="tag text-[10px]">@{{ factor }}</span>
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
  </section>

  <!-- ══════ ADCスケーリング ══════ -->
  <div v-if="activeToolId === 'adc'">
    <h2 class="font-bold text-lg mb-4">ADCコード/スケーリング設計</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">分解能 (bits)</label>
          <input v-model.number="adc.bits" type="number" min="6" max="24"
            @focus="focusDiagram('adc')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">Vref (V)</label>
          <input v-model.number="adc.vref" type="number" step="0.01"
            @focus="focusDiagram('vref')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">入力電圧 Vin (V)</label>
          <input v-model.number="adc.vin" type="number" step="0.01"
            @focus="focusDiagram('vin')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3">
          <label class="w-28 text-sm">オフセット (V)</label>
          <input v-model.number="adc.offset" type="number" step="0.01"
            @focus="focusDiagram('offset')" @blur="clearDiagramFocus"
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
        <div v-for="[key, label, step, diagramKey] in [['L0','定格寿命 L₀ (h)',100,'L0'],['T0','定格温度 T₀ (°C)',5,'L0'],['T','動作温度 T (°C)',1,'T'],['Vr','定格電圧 Vr (V)',1,'V'],['V','動作電圧 V (V)',1,'V']]" :key="key"
          class="flex items-center gap-3">
          <label class="w-32 text-sm">@{{ label }}</label>
          <input v-model.number="cap[key]" type="number" :step="step"
            @focus="focusDiagram(diagramKey)" @blur="clearDiagramFocus"
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
            @focus="focusDiagram('vin')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-32 text-sm">R1 上側抵抗 (Ω)</label>
          <input v-model.number="divider.r1" type="number"
            @focus="focusDiagram('r1')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-32 text-sm">R2 下側抵抗 (Ω)</label>
          <input v-model.number="divider.r2" type="number"
            @focus="focusDiagram('r2')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
      </div>
      <div v-else class="space-y-3">
        <div class="flex items-center gap-3"><label class="w-28 text-sm">R₀ @ T₀ (Ω)</label>
          <input v-model.number="divider.R0" type="number"
            @focus="focusDiagram('R0')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">T₀ (°C)</label>
          <input v-model.number="divider.T0" type="number"
            @focus="focusDiagram('T0')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">B定数</label>
          <input v-model.number="divider.B" type="number"
            @focus="focusDiagram('B')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-32 text-sm">Rntc 測定抵抗 (Ω)</label>
          <input v-model.number="divider.Rmeas" type="number"
            @focus="focusDiagram('Rmeas')" @blur="clearDiagramFocus"
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
        <div class="flex items-center gap-3"><label class="w-36 text-sm">Rs シャント抵抗 (Ω)</label>
          <input v-model.number="shunt.Rs" type="number" step="0.001"
            @focus="focusDiagram('Rs')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">アンプゲイン</label>
          <input v-model.number="shunt.gain" type="number" step="1"
            @focus="focusDiagram('gain')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div v-if="shunt.mode === 'from_vout'" class="flex items-center gap-3"><label class="w-28 text-sm">Vout (V)</label>
          <input v-model.number="shunt.Vout" type="number" step="0.001"
            @focus="focusDiagram('Vout')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div v-else class="flex items-center gap-3"><label class="w-28 text-sm">電流 I (A)</label>
          <input v-model.number="shunt.I" type="number" step="0.1"
            @focus="focusDiagram('I')" @blur="clearDiagramFocus"
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
            @focus="focusDiagram('supply')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(l, i) in power.loads" :key="i" class="flex items-center gap-2">
            <input v-model="l.label" type="text" placeholder="名称"
              @focus="focusDiagram('loads')" @blur="clearDiagramFocus"
              class="w-24 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input v-model.number="l.mA" type="number" step="1" placeholder="mA"
              @focus="focusDiagram('loads')" @blur="clearDiagramFocus"
              class="w-20 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm font-mono" />
            <span class="text-xs opacity-50">mA @</span>
            <input v-model.number="l.V" type="number" step="0.1"
              @focus="focusDiagram('loads')" @blur="clearDiagramFocus"
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
        <div v-for="[key, label, step, diagramKey] in [['Vcc','Vcc 電源電圧 (V)',0.1,'Vcc'],['Vref','Vref 基準入力 (V)',0.01,'Vref'],['R1','R1 入力抵抗 (Ω)',1000,'R1'],['R2','R2 基準側抵抗 (Ω)',1000,'R2'],['R3','R3 帰還抵抗 (Ω, 0=なし)',1000,'R3']]" :key="key"
          class="flex items-center gap-3">
          <label class="w-36 text-sm">@{{ label }}</label>
          <input v-model.number="comp[key]" type="number" :step="step"
            @focus="focusDiagram(diagramKey)" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
      </div>
      <div class="bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded-lg p-4 space-y-2">
        <div class="mb-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3 font-mono text-xs">
          <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 text-center">
            <div>Vin</div><div>R1</div><div>+IN</div>
            <div class="col-span-3 border-t border-[var(--color-border)]"></div>
            <div>Vref</div><div>R2</div><div>OUT → R3 → +IN</div>
          </div>
        </div>
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
            @focus="focusDiagram('P')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">雰囲気温度 (°C)</label>
          <input v-model.number="thermal.Tambient" type="number" step="1"
            @focus="focusDiagram('Tambient')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="flex items-center gap-3 mb-4">
          <label class="w-32 text-sm font-medium">Tj閾値 (°C)</label>
          <input v-model.number="thermal.TjLimit" type="number" step="1"
            @focus="focusDiagram('Tj')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" />
        </div>
        <div class="space-y-2 mb-3">
          <div v-for="(n, i) in thermal.nodes" :key="i" class="flex items-center gap-2">
            <input v-model="n.label" type="text"
              @focus="focusDiagram('nodes')" @blur="clearDiagramFocus"
              class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-2 py-1.5 text-sm" />
            <input v-model.number="n.Rth" type="number" step="0.1" placeholder="θ(°C/W)"
              @focus="focusDiagram('nodes')" @blur="clearDiagramFocus"
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
        <div class="text-xs opacity-60 mb-2">温度チェーン（入力端→接合部）:</div>
        <div class="space-y-1">
          <div v-for="n in thermalResult.cumulative" :key="n.label" class="flex justify-between text-xs">
            <span class="opacity-70">@{{ n.label }}</span>
            <span class="font-mono">@{{ n.T }} °C</span>
          </div>
        </div>
        <p v-if="!thermalResult.ok" class="text-red-500 text-xs mt-2">⚠ Tj が閾値を超えています</p>
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
  <div v-if="activeToolId === 'interface'">
    <h2 class="font-bold text-lg mb-4">インタフェース電圧余裕解析</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-3">
        <p class="text-xs font-medium opacity-60">出力側（ドライバ）</p>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VOH (V)</label>
          <input v-model.number="iface.VOH" type="number" step="0.01"
            @focus="focusDiagram('VOH')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VOL (V)</label>
          <input v-model.number="iface.VOL" type="number" step="0.01"
            @focus="focusDiagram('VOL')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <p class="text-xs font-medium opacity-60 pt-2">入力側（レシーバ）</p>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VIH (V)</label>
          <input v-model.number="iface.VIH" type="number" step="0.01"
            @focus="focusDiagram('VIH')" @blur="clearDiagramFocus"
            class="flex-1 bg-[var(--color-card-odd)] border border-[var(--color-border)] rounded px-3 py-1.5 text-sm font-mono" /></div>
        <div class="flex items-center gap-3"><label class="w-28 text-sm">VIL (V)</label>
          <input v-model.number="iface.VIL" type="number" step="0.01"
            @focus="focusDiagram('VIL')" @blur="clearDiagramFocus"
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

  <!-- ══════ 共通クイック解析フォーム ══════ -->
  <div v-if="quickTool">
    <h2 class="font-bold text-lg mb-4">@{{ quickTool.title }}</h2>
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)] gap-6">
      <div class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
        <div class="grid gap-3 md:grid-cols-2">
          <label v-for="field in quickTool.fields" :key="field.key" class="block">
            <span class="block text-[11px] font-semibold opacity-60 mb-1">@{{ field.label }}</span>
            <select v-if="field.type === 'select'" v-model="quickForms[quickTool.model][field.key]"
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
            <input v-else v-model.number="quickForms[quickTool.model][field.key]"
              @focus="focusDiagram(field.diagramKey || field.key)" @blur="clearDiagramFocus"
              type="number" step="any" class="input-text w-full font-mono" />
          </label>
        </div>
      </div>
      <div class="rounded-2xl border bg-[var(--color-card-odd)] p-4"
        :class="{
          'border-[var(--color-tag-ok)]': quickTool.tone === 'ok',
          'border-[var(--color-tag-warning)]': quickTool.tone === 'warn',
          'border-[var(--color-tag-eol)]': quickTool.tone === 'bad',
          'border-[var(--color-border)]': !quickTool.tone
        }">
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">Result</div>
        <div class="mt-3 space-y-2">
          <div v-for="row in quickTool.rows" :key="row[0]" class="flex items-start justify-between gap-4 border-b border-[var(--color-border)] pb-2 last:border-b-0">
            <span class="text-sm opacity-65">@{{ row[0] }}</span>
            <span class="text-right font-mono font-semibold">@{{ row[1] }}</span>
          </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
          <span class="tag">入力即時反映</span>
          <span class="tag">初期値あり</span>
          <span v-if="quickTool.tone === 'bad'" class="tag tag-eol">要見直し</span>
          <span v-else-if="quickTool.tone === 'warn'" class="tag tag-warning">要確認</span>
          <span v-else class="tag tag-ok">目安OK</span>
        </div>
      </div>
    </div>
  </div>

  @include('partials.app-breadcrumbs', ['items' => [['label' => '設計解析ツール', 'current' => true]], 'class' => 'mt-6'])

</div>
</body>
</html>
