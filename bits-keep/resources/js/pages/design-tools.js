/**
 * 設計解析ツールハブ（SCR-016）
 * タブ切り替えで複数ツールを提供（全てフロント計算）
 *
 * 収録ツール:
 * 1. ADCコード/スケーリング
 * 2. 電解コンデンサ寿命推定
 * 3. 抵抗分圧/温度変換（NTC/PTC対応）
 * 4. 電流検出解析（シャント抵抗）
 * 5. 電源余裕解析（供給電力 vs 消費電力）
 * 6. 比較器しきい値/ヒステリシス
 * 7. 熱設計（熱抵抗チェーン）
 * 8. インタフェース余裕解析（VOH/VOL/VIH/VIL）
 */
import { ref, reactive, computed } from 'vue';

export default function setup() {
    const activeToolId = ref(document.getElementById('app')?.dataset?.tool ?? 'adc');

    const tools = [
        { id: 'adc',        label: 'ADCスケーリング' },
        { id: 'cap-life',   label: 'コンデンサ寿命' },
        { id: 'divider',    label: '分圧・温度変換' },
        { id: 'shunt',      label: '電流検出' },
        { id: 'power',      label: '電源余裕' },
        { id: 'comparator', label: '比較器' },
        { id: 'thermal',    label: '熱設計' },
        { id: 'interface',  label: 'IF余裕' },
    ];

    // ══════════════════════════════════════════════
    // 1. ADCコード/スケーリング
    // ══════════════════════════════════════════════
    const adc = reactive({
        bits: 12, vref: 3.3, vin: 1.65, offset: 0,
    });
    const adcResult = computed(() => {
        const fullScale = Math.pow(2, adc.bits) - 1;
        const code      = Math.round((adc.vin - adc.offset) / adc.vref * fullScale);
        const lsb       = adc.vref / fullScale;
        const clamped   = Math.max(0, Math.min(fullScale, code));
        return {
            code:     clamped,
            hex:      '0x' + clamped.toString(16).toUpperCase().padStart(Math.ceil(adc.bits / 4), '0'),
            lsb_mv:   (lsb * 1000).toFixed(4),
            percent:  (clamped / fullScale * 100).toFixed(2),
            clipped:  code < 0 || code > fullScale,
        };
    });

    // ══════════════════════════════════════════════
    // 2. 電解コンデンサ寿命推定（アレニウス則）
    // ══════════════════════════════════════════════
    const cap = reactive({
        L0: 2000,   // 定格寿命(h)
        T0: 105,    // 定格温度(°C)
        T:  65,     // 動作温度(°C)
        Vr: 50,     // 定格電圧(V)
        V:  35,     // 動作電圧(V)
    });
    const capResult = computed(() => {
        const tempFactor    = Math.pow(2, (cap.T0 - cap.T) / 10);
        const voltageFactor = Math.pow(cap.Vr / cap.V, 3);
        const life          = cap.L0 * tempFactor * voltageFactor;
        return {
            life_h:    Math.round(life),
            life_y:    (life / 8760).toFixed(1),
            temp_factor:    tempFactor.toFixed(2),
            voltage_factor: voltageFactor.toFixed(2),
        };
    });

    // ══════════════════════════════════════════════
    // 3. 抵抗分圧 / NTC温度変換
    // ══════════════════════════════════════════════
    const divider = reactive({
        mode: 'voltage',  // 'voltage' | 'ntc'
        // 電圧分圧
        vin: 5.0, r1: 10000, r2: 10000,
        // NTC
        R0: 10000, T0: 25, B: 3950, Rmeas: 10000,
    });
    const dividerResult = computed(() => {
        if (divider.mode === 'voltage') {
            const vout = divider.vin * divider.r2 / (divider.r1 + divider.r2);
            const ratio = divider.r2 / (divider.r1 + divider.r2);
            return { vout: vout.toFixed(4), ratio: (ratio * 100).toFixed(2) };
        } else {
            // NTC: 1/T = 1/T0 + (1/B)*ln(R/R0)
            const T0k  = divider.T0 + 273.15;
            const Tk   = 1 / (1 / T0k + Math.log(divider.Rmeas / divider.R0) / divider.B);
            const Tc   = Tk - 273.15;
            return { temp_c: Tc.toFixed(2), temp_k: Tk.toFixed(2) };
        }
    });

    // ══════════════════════════════════════════════
    // 4. 電流検出（シャント抵抗）
    // ══════════════════════════════════════════════
    const shunt = reactive({
        Rs: 0.01,    // シャント抵抗(Ω)
        gain: 20,    // アンプゲイン
        Vout: 0.1,   // アンプ出力(V)
        mode: 'from_vout',  // 'from_vout' | 'from_current'
        I: 5,        // 電流(A) → Vout計算用
    });
    const shuntResult = computed(() => {
        if (shunt.mode === 'from_vout') {
            const Vshunt = shunt.Vout / shunt.gain;
            const I      = Vshunt / shunt.Rs;
            const P      = I * I * shunt.Rs;
            return { I: I.toFixed(4), Vshunt_mv: (Vshunt * 1000).toFixed(3), P_mW: (P * 1000).toFixed(3) };
        } else {
            const Vshunt = shunt.I * shunt.Rs;
            const Vout   = Vshunt * shunt.gain;
            const P      = shunt.I * shunt.I * shunt.Rs;
            return { Vout: Vout.toFixed(4), Vshunt_mv: (Vshunt * 1000).toFixed(3), P_mW: (P * 1000).toFixed(3) };
        }
    });

    // ══════════════════════════════════════════════
    // 5. 電源余裕解析
    // ══════════════════════════════════════════════
    const power = reactive({
        supply_w: 10,
        loads: [{ label: 'MCU', mA: 50, V: 3.3 }, { label: 'Sensor', mA: 20, V: 3.3 }],
    });
    const addLoad   = () => power.loads.push({ label: '', mA: 0, V: 3.3 });
    const removeLoad = (i) => power.loads.splice(i, 1);
    const powerResult = computed(() => {
        const totalW  = power.loads.reduce((s, l) => s + l.mA * l.V / 1000, 0);
        const margin  = power.supply_w - totalW;
        const percent = (totalW / power.supply_w * 100).toFixed(1);
        return { totalW: totalW.toFixed(3), margin: margin.toFixed(3), percent, ok: margin >= 0 };
    });

    // ══════════════════════════════════════════════
    // 6. 比較器しきい値/ヒステリシス
    // ══════════════════════════════════════════════
    const comp = reactive({
        Vcc: 3.3, R1: 100000, R2: 100000, R3: 0, Vref: 1.65,
    });
    const compResult = computed(() => {
        // 非反転入力: Vref → しきい値計算
        // ヒステリシス: R3をフィードバック抵抗として使用
        if (comp.R3 <= 0) {
            return { Vth: comp.Vref.toFixed(4), hysteresis: 0, Vth_high: comp.Vref.toFixed(4), Vth_low: comp.Vref.toFixed(4) };
        }
        const Vth_high = (comp.Vref * (1 + comp.R1 / comp.R3) - comp.Vcc * comp.R1 / comp.R3) ;
        const Vth_low  = comp.Vref * (1 + comp.R1 / comp.R3);
        const hyst     = Math.abs(Vth_high - Vth_low);
        return { Vth_high: Vth_high.toFixed(4), Vth_low: Vth_low.toFixed(4), hysteresis: hyst.toFixed(4) };
    });

    // ══════════════════════════════════════════════
    // 7. 熱設計（熱抵抗チェーン）
    // ══════════════════════════════════════════════
    const thermal = reactive({
        P: 1.0,        // 消費電力(W)
        Tambient: 25,  // 雰囲気温度(°C)
        nodes: [
            { label: '接合-ケース(θjc)', Rth: 5 },
            { label: 'ケース-放熱板(θcs)', Rth: 0.5 },
            { label: '放熱板-雰囲気(θsa)', Rth: 10 },
        ],
    });
    const addNode    = () => thermal.nodes.push({ label: '熱抵抗', Rth: 1 });
    const removeNode = (i) => thermal.nodes.splice(i, 1);
    const thermalResult = computed(() => {
        const totalRth = thermal.nodes.reduce((s, n) => s + n.Rth, 0);
        const Tjunction = thermal.Tambient + thermal.P * totalRth;
        const cumulative = [];
        let T = thermal.Tambient;
        for (const n of [...thermal.nodes].reverse()) {
            T += thermal.P * n.Rth;
            cumulative.unshift({ label: n.label, T: T.toFixed(1) });
        }
        return { Tjunction: Tjunction.toFixed(1), totalRth, cumulative };
    });

    // ══════════════════════════════════════════════
    // 8. インタフェース余裕解析
    // ══════════════════════════════════════════════
    const iface = reactive({
        VOH: 2.4, VOL: 0.4,    // 出力側
        VIH: 2.0, VIL: 0.8,    // 入力側
        Vcc_out: 3.3, Vcc_in: 3.3,
    });
    const ifaceResult = computed(() => {
        const high_margin = iface.VOH - iface.VIH;
        const low_margin  = iface.VIL - iface.VOL;
        return {
            high_margin: high_margin.toFixed(3),
            low_margin:  low_margin.toFixed(3),
            high_ok: high_margin > 0,
            low_ok:  low_margin > 0,
        };
    });

    return {
        activeToolId, tools,
        adc, adcResult,
        cap, capResult,
        divider, dividerResult,
        shunt, shuntResult,
        power, powerResult, addLoad, removeLoad,
        comp, compResult,
        thermal, thermalResult, addNode, removeNode,
        iface, ifaceResult,
    };
}
