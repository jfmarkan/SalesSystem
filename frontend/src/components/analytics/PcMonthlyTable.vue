<script setup>
import { computed } from 'vue'

const props = defineProps({
    months: { type: Array, required: true },
    sales: { type: Array, default: () => [] }, // preferido (mensual)
    budget: { type: Array, default: () => [] }, // mensual (tal cual backend)
    forecast: { type: Array, default: () => [] }, // mensual (última versión)
    ventas: { type: Array, default: null }, // compat
})
defineEmits(['edit-forecast'])

const len = computed(() => (Array.isArray(props.months) ? props.months.length : 12))

/* ---------- helpers ---------- */
function fmtMonthDE(ym) {
    if (!ym) return '—'
    const [yS, mS] = String(ym).split('-')
    const y = yS?.slice(2) ?? ''
    const m = parseInt(mS || '1', 10)
    const map = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
    return `${map[m - 1] || '—'} ${y}`
}
function yyyymm(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

/** Normaliza números DE: quita miles '.', cambia ',' por '.' y convierte */
function toNumberDE(v) {
    if (v == null) return 0
    if (typeof v === 'number') return Number.isFinite(v) ? v : 0
    const s = String(v).trim()
    if (s === '') return 0
    const cleaned = s
        .replace(/\u00A0/g, '') // NBSP
        .replace(/\s/g, '') // espacios
        .replace(/\./g, '') // miles
        .replace(/,/g, '.') // decimales
    const n = Number(cleaned)
    return Number.isFinite(n) ? n : 0
}

/** Formato entero sin decimales, estilo de-DE */
function fmt0(v) {
    const n = Math.round(toNumberDE(v))
    return n.toLocaleString('de-DE', { maximumFractionDigits: 0 })
}

/** % relativo a 100: (Ist/Forecast*100 - 100) redondeado al entero, con signo */
function pctRelLabel(num, den) {
    const n = toNumberDE(num),
        d = toNumberDE(den)
    if (!d) return '0%'
    const diff = Math.round((n / d) * 100 - 100)
    return (diff > 0 ? '+' : '') + diff + '%'
}

/** Clases de desvío vs Forecast (provisorio; basado en |desvío|) */
function clsIstVsFcst(v, f) {
    const n = toNumberDE(v),
        d = toNumberDE(f)
    if (!d) return 'dev-yellow'
    const p = (n / d - 1) * 100
    const a = Math.abs(p)
    if (a > 5) return 'dev-red'
    if (a > 2) return 'dev-yellow'
    return 'dev-green'
}

/* ---------- datos normalizados ---------- */
const curIdx = computed(() => {
    const key = yyyymm(new Date())
    return Array.isArray(props.months) ? props.months.findIndex((m) => m === key) : -1
})

const salesData = computed(() => {
    const base =
        Array.isArray(props.sales) && props.sales.length
            ? props.sales
            : Array.isArray(props.ventas)
              ? props.ventas
              : []
    return Array.from({ length: len.value }, (_, i) => toNumberDE(base?.[i] ?? 0))
})

const budgetData = computed(() => {
    const base = Array.isArray(props.budget) ? props.budget : []
    return Array.from({ length: len.value }, (_, i) => toNumberDE(base?.[i] ?? 0))
})

const forecastData = computed(() => {
    const base = Array.isArray(props.forecast) ? props.forecast : []
    return Array.from({ length: len.value }, (_, i) => toNumberDE(base?.[i] ?? 0))
})
</script>

<template>
    <div class="table-shell">
        <div class="table-scroll-x">
            <table
                class="w-full"
                style="min-width: 1200px; border-collapse: separate; border-spacing: 0"
            >
                <thead>
                    <tr>
                        <th class="p-2 text-left sticky left-0 z-2 stick-left"></th>
                        <th
                            v-for="(m, i) in months"
                            :key="'m' + i"
                            class="p-2 text-center stick-head"
                            :class="{
                                'cur-left': i === curIdx,
                                'cur-right': i === curIdx,
                                'cur-top': i === curIdx,
                            }"
                        >
                            {{ fmtMonthDE(m) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Ist -->
                    <tr>
                        <td class="p-2 sticky text-right left-0 z-2 stick-left">Ist</td>
                        <td
                            v-for="(m, i) in months"
                            :key="'v' + i"
                            class="p-2 text-center cell cell-sales text-strong"
                            :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }"
                        >
                            {{ fmt0(salesData[i]) }}
                        </td>
                    </tr>

                    <!-- Budget (usar valores EXACTOS del backend; sólo normalizar formato) -->
                    <tr>
                        <td class="p-2 sticky text-right left-0 z-2 stick-left">Budget</td>
                        <td
                            v-for="(m, i) in months"
                            :key="'b' + i"
                            class="p-2 text-center cell cell-budget text-strong"
                            :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }"
                        >
                            {{ fmt0(budgetData[i]) }}
                        </td>
                    </tr>

                    <!-- Forecast (READ-ONLY) -->
                    <tr>
                        <td class="p-2 sticky text-right left-0 z-2 stick-left">Forecast</td>
                        <td
                            v-for="(m, i) in months"
                            :key="'f' + i"
                            class="p-2 text-center cell text-strong"
                            :class="{
                                'cur-left': i === curIdx,
                                'cur-right': i === curIdx,
                                'cur-bottom': i === curIdx,
                            }"
                        >
                            <div class="ro-forecast">{{ fmt0(forecastData[i]) }}</div>
                        </td>
                    </tr>

                    <!-- % Ist (desvío relativo a 100: (Ist/Forecast*100 - 100)) -->
                    <tr>
                        <td class="p-2 sticky text-right left-0 z-2 stick-left">% Ist</td>
                        <td
                            v-for="(m, i) in months"
                            :key="'ivf' + i"
                            class="p-2 text-center cell dev-cell"
                            :class="[
                                clsIstVsFcst(salesData[i], forecastData[i]),
                                { 'cur-left': i === curIdx, 'cur-right': i === curIdx },
                            ]"
                        >
                            {{ pctRelLabel(salesData[i], forecastData[i]) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
:root {
    --blue: #54849a;
    --green: #05a46f;
    --yellow: #e6b729;
    --orange: #e88d1e;
    --orangeDeep: #ea6312;
    --red: #b01513;
}
.table-shell {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.table-scroll-x {
    overflow-x: auto;
    overflow-y: hidden;
    height: 100%;
}

.stick-head {
    position: sticky;
    top: 0;
    background: var(--surface-card);
    z-index: 1;
}
.stick-left {
    width: calc(100% / 13);
    left: 0;
    background: var(--surface-card);
}

.cell {
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
.cur-left {
    border-left: 2px solid var(--red) !important;
}
.cur-right {
    border-right: 2px solid var(--red) !important;
}
.cur-top {
    border-top: 2px solid var(--red) !important;
}
.cur-bottom {
    border-bottom: 2px solid var(--red) !important;
}

.cell-sales {
    background: rgba(31, 86, 115, 0.18);
}
.cell-budget {
    background: rgba(84, 132, 154, 0.18);
}
.text-strong {
    color: var(--text-color);
    font-weight: 600;
}

.ro-forecast {
    padding: 0.25rem 0.5rem;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.04);
}

.dev-cell {
    transition:
        background-color 0.2s ease,
        color 0.2s ease;
}
.dev-red {
    background: rgba(176, 21, 19, 0.18);
    color: #3b0d0d;
}
.dev-orange {
    background: rgba(234, 99, 18, 0.18);
    color: #3b260d;
}
.dev-yellow {
    background: rgba(230, 183, 41, 0.2);
    color: #3a300b;
}
.dev-green {
    background: rgba(5, 164, 111, 0.18);
    color: #093a2c;
}
</style>
