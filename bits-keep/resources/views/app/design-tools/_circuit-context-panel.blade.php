{{-- 各設計ツールの回路図、式、未評価条件を表示するpartial。 --}}
  <section v-if="activeDiagram && !['network-search', 'divider-design', 'variable-resistor'].includes(activeToolId)" data-review-stage="spec" class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-card-even)] p-4">
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(280px,0.75fr)] lg:items-stretch">
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">回路の前提</div>
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
              <text x="180" y="44" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'Vin' : 'Vref' }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.upper)" @mouseenter="focusDiagram(activeDiagram.keys.upper)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.upper)">
              <rect x="155" y="68" width="50" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="97" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'R1' : (divider.position === 'high' ? 'Rth' : 'Rfix') }}</text>
              <text x="222" y="95" class="circuit-note">@{{ activeDiagram.type === 'divider' ? '上側' : (divider.position === 'high' ? '上側センサ' : '固定') }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.output)" @mouseenter="focusDiagram(activeDiagram.keys.output)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.output)">
              <circle cx="180" cy="130" r="6" class="circuit-junction"></circle>
              <rect x="430" y="105" width="120" height="50" rx="8" class="circuit-box"></rect>
              <text x="490" y="126" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'Vout' : 'Vadc' }}</text>
              <text x="490" y="143" text-anchor="middle" class="circuit-note">@{{ activeDiagram.type === 'divider' ? 'ADC/後段へ' : '温度へ換算' }}</text>
            </g>
            <g :class="diagramItemClass(activeDiagram.keys.lower)" @mouseenter="focusDiagram(activeDiagram.keys.lower)" @mouseleave="clearDiagramFocus" @click="focusDiagram(activeDiagram.keys.lower)">
              <rect x="155" y="146" width="50" height="48" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="175" text-anchor="middle" class="circuit-label">@{{ activeDiagram.type === 'divider' ? 'R2' : (divider.position === 'high' ? 'Rfix' : 'Rth') }}</text>
              <text x="222" y="173" class="circuit-note">@{{ activeDiagram.type === 'divider' ? '下側' : (divider.position === 'high' ? '固定' : '下側センサ') }}</text>
            </g>
            <g v-if="activeDiagram.type === 'divider-ntc'">
              <text x="430" y="180" class="circuit-note">温度モデル</text>
              <text x="430" y="198" class="circuit-note">R0 @{{ divider.R0 }} Ω</text>
              <text x="430" y="216" class="circuit-note">T0 @{{ divider.T0 }} ℃ / B @{{ divider.B }}</text>
            </g>
            <g>
              <line x1="160" y1="216" x2="200" y2="216" class="circuit-wire"></line>
              <line x1="166" y1="224" x2="194" y2="224" class="circuit-wire"></line>
              <line x1="173" y1="232" x2="187" y2="232" class="circuit-wire"></line>
              <text x="214" y="226" class="circuit-note">GND</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'shunt'">
            <line x1="64" y1="72" x2="130" y2="72" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <text x="66" y="54" class="circuit-label">Vbus</text>
            <g :class="diagramItemClass('I')" @mouseenter="focusDiagram('I')" @mouseleave="clearDiagramFocus" @click="focusDiagram('I')">
              <text x="83" y="96" class="circuit-label">Iload</text>
              <text x="76" y="118" class="circuit-note">負荷電流</text>
            </g>
            <g :class="diagramItemClass('load')" @mouseenter="focusDiagram('load')" @mouseleave="clearDiagramFocus" @click="focusDiagram('load')">
              <rect x="130" y="46" width="100" height="52" rx="6" class="circuit-symbol-fill"></rect>
              <text x="180" y="76" text-anchor="middle" class="circuit-label">負荷</text>
              <text x="180" y="94" text-anchor="middle" class="circuit-note">測定対象</text>
            </g>
            <line x1="230" y1="72" x2="302" y2="72" class="circuit-wire"></line>
            <circle cx="302" cy="72" r="4" class="circuit-junction"></circle>
            <g :class="diagramItemClass('Rs')" @mouseenter="focusDiagram('Rs')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Rs')">
              <line x1="302" y1="72" x2="302" y2="100" class="circuit-wire"></line>
              <rect x="272" y="100" width="60" height="62" rx="6" class="circuit-symbol-fill"></rect>
              <text x="302" y="133" text-anchor="middle" class="circuit-label">Rs</text>
              <text x="302" y="151" text-anchor="middle" class="circuit-note">shunt</text>
              <line x1="302" y1="162" x2="302" y2="184" class="circuit-wire"></line>
              <circle cx="302" cy="162" r="4" class="circuit-junction"></circle>
              <line x1="278" y1="184" x2="326" y2="184" class="circuit-wire"></line>
              <line x1="286" y1="193" x2="318" y2="193" class="circuit-wire"></line>
              <line x1="294" y1="202" x2="310" y2="202" class="circuit-wire"></line>
              <text x="336" y="195" class="circuit-note">GND</text>
            </g>
            <g :class="diagramItemClass('Vshunt')" @mouseenter="focusDiagram('Vshunt')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vshunt')">
              <path d="M 246 76 L 246 158" class="circuit-wire"></path>
              <path d="M 238 76 L 254 76 M 238 158 L 254 158" class="circuit-wire"></path>
              <text x="216" y="122" text-anchor="middle" class="circuit-label">Vshunt</text>
              <text x="216" y="140" text-anchor="middle" class="circuit-note">Rs両端</text>
            </g>
            <g :class="diagramItemClass('gain')" @mouseenter="focusDiagram('gain')" @mouseleave="clearDiagramFocus" @click="focusDiagram('gain')">
              <path d="M 402 82 L 510 132 L 402 182 Z" class="circuit-box"></path>
              <text x="436" y="126" text-anchor="middle" class="circuit-label">CSA</text>
              <text x="438" y="145" text-anchor="middle" class="circuit-note">gain</text>
              <line x1="302" y1="72" x2="402" y2="108" class="circuit-wire"></line>
              <line x1="302" y1="162" x2="402" y2="156" class="circuit-wire"></line>
              <text x="346" y="91" class="circuit-note">Kelvin +</text>
              <text x="346" y="177" class="circuit-note">Kelvin -</text>
            </g>
            <g :class="diagramItemClass('Vzero')" @mouseenter="focusDiagram('Vzero')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vzero')">
              <line x1="456" y1="54" x2="456" y2="105" class="circuit-wire"></line>
              <circle cx="456" cy="54" r="16" class="circuit-node"></circle>
              <text x="456" y="58" text-anchor="middle" class="circuit-label">@{{ shunt.senseMode === 'bidirectional' ? 'Vzero' : '0V' }}</text>
              <text x="474" y="70" class="circuit-note">@{{ shunt.senseMode === 'bidirectional' ? '双方向基準' : '片方向基準' }}</text>
            </g>
            <g :class="diagramItemClass('Vout')" @mouseenter="focusDiagram('Vout')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vout')">
              <line x1="510" y1="132" x2="562" y2="132" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <rect x="562" y="106" width="66" height="52" rx="6" class="circuit-box"></rect>
              <text x="595" y="130" text-anchor="middle" class="circuit-label">ADC</text>
              <text x="595" y="148" text-anchor="middle" class="circuit-note">Vout / Bin</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'comparator'">
            <g>
              <path d="M 330 66 L 330 194 L 462 130 Z" class="circuit-box"></path>
              <line x1="306" y1="104" x2="330" y2="104" class="circuit-wire"></line>
              <line x1="306" y1="156" x2="330" y2="156" class="circuit-wire"></line>
              <text x="350" y="102" class="circuit-label">+</text>
              <text x="350" y="158" class="circuit-label">-</text>
              <text x="390" y="134" text-anchor="middle" class="circuit-label">CMP</text>
              <circle cx="306" cy="104" r="4" class="circuit-junction"></circle>
              <circle cx="306" cy="156" r="4" class="circuit-junction"></circle>
              <text x="286" y="92" class="circuit-note">V+</text>
              <text x="286" y="174" class="circuit-note">V-</text>
            </g>

            <g>
              <line v-if="comp.inputPolarity !== 'negative'" x1="52" y1="104" x2="152" y2="104" class="circuit-wire"></line>
              <line v-if="comp.inputPolarity === 'negative' && comp.referenceMode === 'external'" x1="92" y1="104" x2="152" y2="104" class="circuit-wire"></line>
              <line v-if="comp.inputPolarity === 'negative' && comp.referenceMode === 'divider'" x1="86" y1="208" x2="134" y2="208" class="circuit-wire"></line>
              <line v-if="comp.inputPolarity === 'negative' && comp.referenceMode === 'divider'" x1="134" y1="208" x2="134" y2="104" class="circuit-wire"></line>
              <line v-if="comp.inputPolarity === 'negative' && comp.referenceMode === 'divider'" x1="134" y1="104" x2="152" y2="104" class="circuit-wire"></line>
              <text v-if="comp.inputPolarity !== 'negative'" x="54" y="90" class="circuit-label">Vin</text>
              <text v-else-if="comp.referenceMode === 'divider'" x="116" y="100" class="circuit-note">Vref(th)</text>
            </g>

            <g :class="diagramItemClass('R1')" @mouseenter="focusDiagram('R1')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R1')">
              <template v-if="comp.inputPolarity === 'negative' && comp.referenceMode === 'divider'">
                <line x1="152" y1="104" x2="306" y2="104" class="circuit-wire"></line>
                <text x="206" y="92" text-anchor="middle" class="circuit-note">R1短絡</text>
              </template>
              <template v-else>
                <rect x="152" y="84" width="72" height="40" rx="6" class="circuit-symbol-fill"></rect>
                <text x="188" y="109" text-anchor="middle" class="circuit-label">R1</text>
                <text x="188" y="128" text-anchor="middle" class="circuit-note">@{{ comp.inputPolarity === 'negative' ? '基準側' : 'Vin側' }}</text>
                <line x1="224" y1="104" x2="306" y2="104" class="circuit-wire"></line>
              </template>
            </g>

            <g :class="diagramItemClass('inputPolarity')" @mouseenter="focusDiagram('inputPolarity')" @mouseleave="clearDiagramFocus" @click="focusDiagram('inputPolarity')">
              <line v-if="comp.inputPolarity === 'negative'" x1="52" y1="156" x2="306" y2="156" class="circuit-wire"></line>
              <text v-if="comp.inputPolarity === 'negative'" x="54" y="144" class="circuit-label">Vin</text>
            </g>

            <g v-if="comp.referenceMode === 'divider'">
              <line x1="86" y1="164" x2="86" y2="174" class="circuit-wire"></line>
              <text x="52" y="168" class="circuit-note">Vcc</text>
            </g>
            <g v-if="comp.referenceMode === 'divider'" :class="diagramItemClass('R2')" @mouseenter="focusDiagram('R2')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R2')">
              <rect x="58" y="174" width="56" height="28" rx="6" class="circuit-symbol-fill"></rect>
              <text x="86" y="193" text-anchor="middle" class="circuit-label">R2</text>
              <line x1="86" y1="202" x2="86" y2="214" class="circuit-wire"></line>
            </g>
            <g v-if="comp.referenceMode === 'divider'" :class="diagramItemClass('R4')" @mouseenter="focusDiagram('R4')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R4')">
              <circle cx="86" cy="208" r="4" class="circuit-junction"></circle>
              <rect x="58" y="214" width="56" height="28" rx="6" class="circuit-symbol-fill"></rect>
              <text x="86" y="233" text-anchor="middle" class="circuit-label">R4</text>
              <line x1="86" y1="242" x2="86" y2="248" class="circuit-wire"></line>
              <line x1="66" y1="248" x2="106" y2="248" class="circuit-wire"></line>
              <line x1="72" y1="253" x2="100" y2="253" class="circuit-wire"></line>
              <line x1="79" y1="258" x2="93" y2="258" class="circuit-wire"></line>
            </g>

            <g :class="diagramItemClass('Vref')" @mouseenter="focusDiagram('Vref')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vref')">
              <line v-if="comp.referenceMode === 'external' && comp.inputPolarity !== 'negative'" x1="92" y1="156" x2="306" y2="156" class="circuit-wire"></line>
              <line v-if="comp.referenceMode === 'divider' && comp.inputPolarity !== 'negative'" x1="86" y1="208" x2="250" y2="208" class="circuit-wire"></line>
              <line v-if="comp.referenceMode === 'divider' && comp.inputPolarity !== 'negative'" x1="250" y1="208" x2="250" y2="156" class="circuit-wire"></line>
              <line v-if="comp.referenceMode === 'divider' && comp.inputPolarity !== 'negative'" x1="250" y1="156" x2="306" y2="156" class="circuit-wire"></line>
              <circle v-if="comp.referenceMode === 'external'" cx="72" :cy="comp.inputPolarity === 'negative' ? 104 : 156" r="20" class="circuit-node"></circle>
              <text v-if="comp.referenceMode === 'external'" x="72" :y="comp.inputPolarity === 'negative' ? 108 : 160" text-anchor="middle" class="circuit-label">Vref</text>
              <text v-if="comp.referenceMode === 'divider'" x="124" y="224" class="circuit-note">Vref(th)</text>
            </g>

            <g :class="diagramItemClass('out')" @mouseenter="focusDiagram('out')" @mouseleave="clearDiagramFocus" @click="focusDiagram('out')">
              <line x1="462" y1="130" x2="590" y2="130" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
              <circle cx="506" cy="130" r="4" class="circuit-junction"></circle>
              <text x="530" y="114" class="circuit-label">OUT</text>
            </g>
            <g :class="diagramItemClass('R3')" @mouseenter="focusDiagram('R3')" @mouseleave="clearDiagramFocus" @click="focusDiagram('R3')">
              <line x1="506" y1="130" x2="506" y2="42" class="circuit-wire"></line>
              <line x1="506" y1="42" x2="444" y2="42" class="circuit-wire"></line>
              <rect x="372" y="23" width="72" height="38" rx="6" class="circuit-symbol-fill"></rect>
              <text x="408" y="47" text-anchor="middle" class="circuit-label">R3</text>
              <line x1="372" y1="42" x2="306" y2="42" class="circuit-wire"></line>
              <line x1="306" y1="42" x2="306" y2="104" class="circuit-wire"></line>
              <text x="392" y="78" text-anchor="middle" class="circuit-note">OUT→V+ 正帰還</text>
            </g>
            <g :class="diagramItemClass('Vcc')" @mouseenter="focusDiagram('Vcc')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Vcc')">
              <text x="500" y="174" class="circuit-note">Vcc = @{{ comp.Vcc }} V</text>
              <text x="500" y="192" class="circuit-note">VOH/VOL = @{{ comp.VOH }}/@{{ comp.VOL }} V</text>
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
              <text x="455" y="136" text-anchor="middle" class="circuit-note">保護ノード</text>
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
              <text x="115" y="122" text-anchor="middle" class="circuit-label">電源</text>
              <text x="115" y="142" text-anchor="middle" class="circuit-note">@{{ power.supply_w }} W</text>
            </g>
            <line x1="175" y1="128" x2="540" y2="128" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('loads')" @mouseenter="focusDiagram('loads')" @mouseleave="clearDiagramFocus" @click="focusDiagram('loads')">
              <g v-for="(load, index) in power.loads.slice(0, 3)" :key="`load-${index}`" :transform="`translate(${245 + index * 110}, 78)`">
                <rect width="86" height="72" rx="8" class="circuit-symbol-fill"></rect>
                <text x="43" y="30" text-anchor="middle" class="circuit-label">@{{ load.label || '負荷' }}</text>
                <text x="43" y="50" text-anchor="middle" class="circuit-note">@{{ load.mA }}mA</text>
              </g>
              <text v-if="power.loads.length > 3" x="570" y="92" class="circuit-note">+@{{ power.loads.length - 3 }}</text>
            </g>
            <g :class="diagramItemClass('margin')" @mouseenter="focusDiagram('margin')" @mouseleave="clearDiagramFocus" @click="focusDiagram('margin')">
              <rect x="260" y="180" width="160" height="42" rx="8" class="circuit-box"></rect>
              <text x="340" y="206" text-anchor="middle" class="circuit-label">余裕 @{{ powerResult.margin }} W</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'thermal'">
            <g :class="diagramItemClass('P')" @mouseenter="focusDiagram('P')" @mouseleave="clearDiagramFocus" @click="focusDiagram('P')">
              <path d="M 45 92 L 125 127 L 45 162 Z" class="circuit-box"></path>
              <text x="70" y="122" text-anchor="middle" class="circuit-label">P</text>
              <text x="72" y="142" text-anchor="middle" class="circuit-note">@{{ thermal.P }} W</text>
            </g>
            <line x1="125" y1="127" x2="520" y2="127" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('nodes')" @mouseenter="focusDiagram('nodes')" @mouseleave="clearDiagramFocus" @click="focusDiagram('nodes')">
              <g v-for="(node, index) in thermal.nodes.slice(0, 3)" :key="`thermal-${index}`" :transform="`translate(${155 + index * 112}, 86)`">
                <rect width="90" height="82" rx="8" class="circuit-symbol-fill"></rect>
                <text x="45" y="30" text-anchor="middle" class="circuit-label">Rth</text>
                <text x="45" y="50" text-anchor="middle" class="circuit-note">@{{ node.Rth }}</text>
                <text x="45" y="68" text-anchor="middle" class="circuit-note">@{{ node.label.slice(0, 8) }}</text>
              </g>
            </g>
            <g :class="diagramItemClass('Tambient')" @mouseenter="focusDiagram('Tambient')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Tambient')">
              <rect x="520" y="96" width="95" height="62" rx="8" class="circuit-box"></rect>
              <text x="568" y="122" text-anchor="middle" class="circuit-label">Ta</text>
              <text x="568" y="142" text-anchor="middle" class="circuit-note">@{{ thermal.Tambient }} ℃</text>
            </g>
            <g :class="diagramItemClass('Tj')" @mouseenter="focusDiagram('Tj')" @mouseleave="clearDiagramFocus" @click="focusDiagram('Tj')">
              <text x="52" y="206" class="circuit-label">Tj @{{ thermalResult.Tjunction }} ℃</text>
            </g>
          </template>

          <template v-else-if="activeDiagram.type === 'interface'">
            <g>
              <rect x="70" y="78" width="150" height="104" rx="10" class="circuit-box"></rect>
              <text x="145" y="108" text-anchor="middle" class="circuit-label">送信側</text>
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
              <text x="495" y="108" text-anchor="middle" class="circuit-label">受信側</text>
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
              <text x="170" y="166" text-anchor="middle" class="circuit-note">嵌合面</text>
            </g>
            <line x1="245" y1="132" x2="395" y2="132" class="circuit-wire" marker-end="url(#circuit-arrow)"></line>
            <g :class="diagramItemClass('endB')" @mouseenter="focusDiagram('endB')" @mouseleave="clearDiagramFocus" @click="focusDiagram('endB')">
              <rect x="395" y="78" width="150" height="112" rx="10" class="circuit-box"></rect>
              <text x="470" y="104" text-anchor="middle" class="circuit-label">端B / pin1</text>
              <circle v-for="pin in 5" :key="`b-${pin}`" :cx="425 + pin * 16" cy="132" r="5" class="circuit-junction"></circle>
              <text x="470" y="166" text-anchor="middle" class="circuit-note">はんだ面?</text>
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
        <div class="text-[11px] uppercase tracking-[0.18em] opacity-50">確認記号 / 式</div>
        <div class="mt-3 flex flex-wrap gap-2">
          <button v-for="part in activeDiagram.parts" :key="part.key" type="button"
            @mouseenter="focusDiagram(part.key)" @mouseleave="clearDiagramFocus" @click="focusDiagram(part.key)"
            class="rounded-lg border border-[var(--color-border)] px-2 py-1 text-left text-xs transition"
            :class="isDiagramFocused(part.key) ? 'border-[var(--color-primary)] bg-[color-mix(in_srgb,var(--color-primary)_10%,var(--color-card-odd))]' : 'bg-[var(--color-bg)]'">
            <span class="block font-semibold">@{{ part.label }}</span>
            <span class="block max-w-[15rem] break-words opacity-60">@{{ part.desc }}</span>
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
