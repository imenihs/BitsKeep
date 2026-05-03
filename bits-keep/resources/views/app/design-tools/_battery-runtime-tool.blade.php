{{-- バッテリー稼働時間の電池パック、動作条件、周期負荷、グラフを表示するpartial。 --}}
  <!-- ══════ バッテリー稼働時間 ══════ -->
  <div v-if="activeToolId === 'battery-runtime'" data-review-stage="tool-input" class="mb-6">
    <h2 class="font-bold text-lg mb-4">バッテリー稼働時間</h2>
    <div class="space-y-4">
        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold">電池パック</h3>
            <div class="font-mono text-xs opacity-70">@{{ batteryResult.profile.label }} / @{{ batteryResult.cellCount }}cell</div>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">種類</span>
              <select v-model="battery.type" class="input-text w-full text-sm">
                <option value="lipo">LiPo</option>
                <option value="nimh">ニッケル水素</option>
                <option value="alkaline">アルカリ</option>
                <option value="manganese">マンガン</option>
                <option value="lead">鉛</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">セル数</span>
              <input :value="numericInputValue(battery, 'cellCount')" @input="setNumericInput(battery, 'cellCount', $event)"
                type="text" inputmode="numeric" autocomplete="off"
                @focus="focusDiagram('batteryType')" @blur="clearDiagramFocus()"
                class="input-text w-full font-mono text-sm" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">容量(Ah)</span>
              <input :value="numericInputValue(battery, 'capacityMah')" @input="setNumericInput(battery, 'capacityMah', $event, 1e-3, true)"
                type="text" inputmode="decimal" autocomplete="off"
                @focus="focusDiagram('capacity')" @blur="clearDiagramFocus()"
                class="input-text w-full font-mono text-sm" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">公称電圧(V)</span>
              <input :value="numericInputValue(battery, 'nominalVoltage')" @input="setNumericInput(battery, 'nominalVoltage', $event)"
                type="text" inputmode="decimal" autocomplete="off"
                @focus="focusDiagram('batteryType')" @blur="clearDiagramFocus()"
                class="input-text w-full font-mono text-sm" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">内部抵抗(Ω)</span>
              <input :value="numericInputValue(battery, 'internalResistance')" @input="setNumericInput(battery, 'internalResistance', $event)"
                type="text" inputmode="decimal" autocomplete="off"
                class="input-text w-full font-mono text-sm" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">使用可能容量(%)</span>
              <input :value="numericInputValue(battery, 'usablePct')" @input="setNumericInput(battery, 'usablePct', $event)"
                type="text" inputmode="decimal" autocomplete="off"
                class="input-text w-full font-mono text-sm" />
            </label>
          </div>
          <div class="mt-3 grid gap-2 sm:grid-cols-3">
            <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-2">
              <div class="text-xs opacity-60">満充電電圧</div>
              <div class="font-mono text-base font-semibold">@{{ batteryResult.standardFullVoltage }} V</div>
              <div class="font-mono text-[11px] opacity-60">計算 @{{ batteryResult.fullVoltage }} V</div>
            </div>
            <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-2">
              <div class="text-xs opacity-60">公称電圧</div>
              <div class="font-mono text-base font-semibold">@{{ batteryResult.standardNominalVoltage }} V</div>
              <div class="font-mono text-[11px] opacity-60">入力 @{{ batteryResult.nominalVoltage }} V</div>
            </div>
            <div class="rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-2">
              <div class="text-xs opacity-60">下限電圧</div>
              <div class="font-mono text-base font-semibold">@{{ batteryResult.standardCutoffVoltage }} V</div>
              <div class="font-mono text-[11px] opacity-60">最低設定 @{{ batteryResult.systemMinVoltage }} V</div>
            </div>
          </div>
        </div>

        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
          <h3 class="mb-3 text-sm font-semibold">動作条件</h3>
          <div class="grid gap-3 sm:grid-cols-3">
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">周期時間(s)</span>
              <input :value="numericInputValue(battery, 'cycleSec')" @input="setNumericInput(battery, 'cycleSec', $event)"
                type="text" inputmode="decimal" autocomplete="off"
                @focus="focusDiagram('cycle')" @blur="clearDiagramFocus()"
                class="input-text w-full font-mono text-sm" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">システム最低電圧(V)</span>
              <input :value="numericInputValue(battery, 'systemMinVoltage')" @input="setNumericInput(battery, 'systemMinVoltage', $event)"
                type="text" inputmode="decimal" autocomplete="off"
                class="input-text w-full font-mono text-sm" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs opacity-60">要求稼働時間(h)</span>
              <input :value="numericInputValue(battery, 'requiredHours')" @input="setNumericInput(battery, 'requiredHours', $event)"
                type="text" inputmode="decimal" autocomplete="off"
                class="input-text w-full font-mono text-sm" />
            </label>
          </div>
        </div>

        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold">周期負荷</h3>
            <button type="button" @click="addBatteryLoad" class="rounded border border-[var(--color-border)] px-2 py-1 text-xs">+ 追加</button>
          </div>
          <div class="space-y-2 overflow-x-auto">
            <div class="grid min-w-[42rem] grid-cols-[minmax(7rem,1fr)_7rem_7rem_6.5rem_6.5rem_4rem] gap-2 text-[11px] font-semibold opacity-60">
              <div>名称</div>
              <div>負荷電圧(V)</div>
              <div>負荷電流(A)</div>
              <div>変換効率(%)</div>
              <div>ON時間(s)</div>
              <div></div>
            </div>
            <div v-for="(load, index) in battery.loads" :key="index" class="grid min-w-[42rem] grid-cols-[minmax(7rem,1fr)_7rem_7rem_6.5rem_6.5rem_4rem] items-center gap-2">
              <input v-model="load.name" type="text" placeholder="名称"
                @focus="focusDiagram('loads')" @blur="clearDiagramFocus()"
                class="input-text text-sm" />
              <input :value="numericInputValue(load, 'voltageV')" @input="setNumericInput(load, 'voltageV', $event)"
                type="text" inputmode="decimal" autocomplete="off" placeholder="3.3"
                @focus="focusDiagram('loads')" @blur="clearDiagramFocus()"
                class="input-text font-mono text-sm" />
              <input :value="numericInputValue(load, 'currentMa')" @input="setNumericInput(load, 'currentMa', $event, 1e-3, true)"
                type="text" inputmode="decimal" autocomplete="off" placeholder="30m"
                @focus="focusDiagram('loads')" @blur="clearDiagramFocus()"
                class="input-text font-mono text-sm" />
              <input :value="numericInputValue(load, 'efficiencyPct')" @input="setNumericInput(load, 'efficiencyPct', $event)"
                type="text" inputmode="decimal" autocomplete="off" placeholder="90"
                @focus="focusDiagram('loads')" @blur="clearDiagramFocus()"
                class="input-text font-mono text-sm" />
              <input :value="numericInputValue(load, 'durationSec')" @input="setNumericInput(load, 'durationSec', $event)"
                type="text" inputmode="decimal" autocomplete="off" placeholder="30"
                @focus="focusDiagram('loads')" @blur="clearDiagramFocus()"
                class="input-text font-mono text-sm" />
              <button type="button" @click="removeBatteryLoad(index)" class="rounded border border-red-300 px-2 py-1 text-xs text-red-600">削除</button>
            </div>
          </div>
        </div>
        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-4">
          <h3 class="mb-3 text-sm font-semibold">結果</h3>
          <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2 xl:col-span-2">
              <div class="text-xs opacity-60">実効稼働時間</div>
              <div class="mt-1 font-mono text-2xl font-bold">@{{ batteryResult.runtimeHours }} h</div>
              <div class="mt-1 text-xs opacity-70">制限要因: @{{ batteryResult.limitingFactor }}</div>
            </div>
            <div>
              <div class="text-xs opacity-60">容量ベース時間</div>
              <div class="mt-1 font-mono text-lg font-semibold">@{{ batteryResult.capacityRuntimeHours }} h</div>
              <div class="mt-1 text-xs opacity-70">@{{ batteryResult.capacityRuntimeScaleText }}</div>
            </div>
            <div>
              <div class="text-xs opacity-60">電圧下限到達時間</div>
              <div class="mt-1 font-mono text-lg font-semibold">@{{ batteryResult.runtimeToMinVoltageHours }} h</div>
              <div class="mt-1 text-xs opacity-70">@{{ batteryResult.runtimeToMinScaleText }}</div>
            </div>
            <div>
              <div class="text-xs opacity-60">Wh/周期</div>
              <div class="mt-1 font-mono font-semibold">@{{ batteryResult.whPerCycle }} Wh</div>
              <div class="mt-1 text-xs opacity-70">@{{ batteryResult.mahPerCycle }} mAh換算</div>
            </div>
            <div>
              <div class="text-xs opacity-60">平均電力</div>
              <div class="mt-1 font-mono font-semibold">@{{ batteryResult.averagePowerW }} W</div>
            </div>
            <div>
              <div class="text-xs opacity-60">平均電流</div>
              <div class="mt-1 font-mono font-semibold">@{{ batteryResult.averageCurrentMa }} mA</div>
            </div>
            <div>
              <div class="text-xs opacity-60">ピーク負荷</div>
              <div class="mt-1 font-mono font-semibold">@{{ batteryResult.peakPowerW }} W</div>
              <div class="mt-1 text-xs opacity-70">@{{ batteryResult.peakLoadName }}</div>
            </div>
            <div>
              <div class="text-xs opacity-60">電池/セル</div>
              <div class="mt-1 font-mono font-semibold">@{{ batteryResult.profile.label }} / @{{ batteryResult.cellCount }}cell</div>
            </div>
            <div>
              <div class="text-xs opacity-60">支配負荷</div>
              <div class="mt-1 font-mono font-semibold">@{{ batteryResult.dominantLoadName }} @{{ batteryResult.dominantLoadPct }}%</div>
            </div>
          </div>
        </div>

        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-card-odd)] p-3">
          <div class="mb-2 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold">グラフ</h3>
          </div>
          <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0">
              <div class="mb-2 text-xs opacity-60">電圧降下 / 放電時間</div>
              <svg :viewBox="`0 0 ${batteryGraph.width} ${batteryGraph.height}`" class="h-72 w-full"
                @mousemove="updateBatteryGraphCursor" @mouseleave="clearBatteryGraphCursor">
                <rect :x="batteryGraph.plot.left" :y="batteryGraph.plot.top" :width="batteryGraph.plot.width" :height="batteryGraph.plot.height"
                  class="fill-transparent stroke-[var(--color-border)]" />
                <g v-for="tick in batteryGraph.yTicks" :key="`battery-y-${tick.label}`">
                  <line :x1="batteryGraph.plot.left" :x2="batteryGraph.plot.left + batteryGraph.plot.width"
                    :y1="tick.y" :y2="tick.y" stroke="#e5e7eb" stroke-width="1" />
                  <text :x="batteryGraph.plot.left - 10" :y="tick.y + 4" text-anchor="end" class="fill-current text-[11px]">@{{ tick.label }}</text>
                </g>
                <g v-for="tick in batteryGraph.xTicks" :key="`battery-x-${tick.ratio}`">
                  <line :x1="tick.x" :x2="tick.x" :y1="batteryGraph.plot.top" :y2="batteryGraph.plot.top + batteryGraph.plot.height"
                    stroke="#f3f4f6" stroke-width="1" />
                  <text :x="tick.x" :y="batteryGraph.plot.top + batteryGraph.plot.height + 24"
                    :text-anchor="tick.ratio === 0 ? 'start' : (tick.ratio === 1 ? 'end' : 'middle')"
                    class="fill-current text-[11px]">@{{ tick.label }}</text>
                </g>
                <polyline :points="batteryGraph.points" fill="none" stroke="#2563eb" stroke-width="3" />
                <line :x1="batteryGraph.plot.left" :x2="batteryGraph.plot.left + batteryGraph.plot.width"
                  :y1="batteryGraph.systemMinY" :y2="batteryGraph.systemMinY"
                  stroke="#f59e0b" stroke-width="2" stroke-dasharray="6 4" />
                <line :x1="batteryGraph.plot.left" :x2="batteryGraph.plot.left + batteryGraph.plot.width"
                  :y1="batteryGraph.nominalY" :y2="batteryGraph.nominalY"
                  stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="5 5" />
                <text :x="batteryGraph.plot.left - 48" :y="batteryGraph.plot.top + batteryGraph.plot.height / 2"
                  transform="rotate(-90 30 127)" class="fill-current text-[11px]">電圧 (V)</text>
                <text :x="batteryGraph.plot.left + batteryGraph.plot.width / 2" :y="batteryGraph.plot.top + batteryGraph.plot.height + 48"
                  text-anchor="middle" class="fill-current text-[11px]">時間 (h)</text>
                <text :x="batteryGraph.plot.left + 6" :y="batteryGraph.systemMinY - 6" class="fill-current text-[11px]">最低 @{{ batteryGraph.systemMinLabel }}</text>
                <text :x="batteryGraph.plot.left + batteryGraph.plot.width - 6" :y="batteryGraph.nominalY - 6" text-anchor="end" class="fill-current text-[11px]">公称 @{{ batteryGraph.nominalLabel }}</text>
                <g v-if="batteryGraphCursor.active">
                  <line :x1="batteryGraphCursor.x" :x2="batteryGraphCursor.x" :y1="batteryGraph.plot.top" :y2="batteryGraph.plot.top + batteryGraph.plot.height"
                    stroke="#111827" stroke-width="1" stroke-dasharray="4 4" />
                  <circle :cx="batteryGraphCursor.x" :cy="batteryGraphCursor.y" r="4" fill="#2563eb" />
                  <g v-if="batteryGraphTooltipBox">
                    <rect :x="batteryGraphTooltipBox.x" :y="batteryGraphTooltipBox.y" :width="batteryGraphTooltipBox.width" :height="batteryGraphTooltipBox.height"
                      rx="4" fill="#111827" opacity="0.92" />
                    <text :x="batteryGraphTooltipBox.x + 10" :y="batteryGraphTooltipBox.y + 18" class="fill-white text-[11px]">@{{ batteryGraphTooltipBox.time }}</text>
                    <text :x="batteryGraphTooltipBox.x + 10" :y="batteryGraphTooltipBox.y + 34" class="fill-white text-[11px]">@{{ batteryGraphTooltipBox.voltage }}</text>
                    <text :x="batteryGraphTooltipBox.x + 10" :y="batteryGraphTooltipBox.y + 50" class="fill-white text-[11px]">@{{ batteryGraphTooltipBox.remaining }}</text>
                  </g>
                </g>
              </svg>
            </div>
            <div class="border-t border-[var(--color-border)] pt-3 xl:border-l xl:border-t-0 xl:pl-4 xl:pt-0">
              <div class="mb-2">
                <h4 class="text-sm font-semibold">容量消費内訳</h4>
                <div class="font-mono text-[11px] opacity-60">@{{ batteryCapacityPie.totalWhLabel }}</div>
              </div>
              <svg :viewBox="`0 0 ${batteryCapacityPie.width} ${batteryCapacityPie.height}`" class="mx-auto h-48 w-full max-w-[16rem]">
                <circle :cx="batteryCapacityPie.cx" :cy="batteryCapacityPie.cy" :r="batteryCapacityPie.radius" fill="#e5e7eb" />
                <path v-for="segment in batteryCapacityPie.segments" :key="`battery-pie-${segment.key}`"
                  :d="segment.path" :fill="segment.color" />
                <circle :cx="batteryCapacityPie.cx" :cy="batteryCapacityPie.cy" :r="40" fill="var(--color-card-odd)" opacity="0.94" />
                <text :x="batteryCapacityPie.cx" :y="batteryCapacityPie.cy - 4" text-anchor="middle" class="fill-current text-[11px]">負荷内訳</text>
                <text :x="batteryCapacityPie.cx" :y="batteryCapacityPie.cy + 16" text-anchor="middle" class="fill-current font-mono text-[13px] font-semibold">@{{ batteryCapacityPie.totalSharePctLabel }}</text>
                <text x="120" y="186" text-anchor="middle" class="fill-current text-[11px] opacity-70">電池100%の消費配分</text>
              </svg>
              <div class="space-y-1 text-xs">
                <div v-for="row in batteryCapacityPie.rows" :key="`battery-pie-row-${row.key}`" class="grid grid-cols-[0.75rem_minmax(0,1fr)_5.5rem] items-center gap-2">
                  <span class="h-3 w-3 rounded-sm" :style="{ backgroundColor: row.color }"></span>
                  <span class="truncate">@{{ row.name }}</span>
                  <span class="text-right font-mono">@{{ row.sharePctLabel }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
    </div>
  </div>
