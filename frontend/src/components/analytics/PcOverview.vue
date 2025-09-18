<template>
    <div class="pc-overview grid">
        <!-- 🔶 NUR IM DRUCK: Kopfbereich mit Details -->
        <div class="col-12 print-only">
            <div class="report-print-header">
                <h2>Vertriebsprognose nach Profit Center</h2>
                <div class="meta">
                    <span
                        >Profit Center: <strong>{{ profitCenterCode }}</strong></span
                    >
                    <span class="dot">•</span>
                    <span
                        >Einheit: <strong>{{ unitLabel }}</strong></span
                    >
                    <span class="dot">•</span>
                    <span
                        >Erstellungsdatum: <strong>{{ createdAtDE }}</strong></span
                    >
                </div>
            </div>
        </div>

        <!-- KPI Karten -->
        <div class="col-12 md:col-2" v-for="card in kpiCards" :key="card.key">
            <Card class="kpi-card">
                <template #content>
                    <div class="kpi-wrap">
                        <div>
                            <div class="kpi-title">{{ card.title }}</div>
                            <div class="kpi-value" v-if="!card.kind">{{ card.value }}</div>
                            <div v-else class="spark-top">
                                <div class="spark-pct" :class="pctClass(card.pct)">
                                    {{ pct(card.pct) }}
                                </div>
                            </div>
                        </div>

                        <div v-if="card.kind === 'spark' && card.chart" class="spark-zone">
                            <Chart
                                type="line"
                                :data="card.chart"
                                :options="sparkOptions"
                                class="sparkline"
                            />
                        </div>

                        <div class="subline">
                            <small class="kpi-sub" v-if="card.sub">{{ card.sub }}</small>
                            <div
                                v-if="card.kind === 'spark' && trendsMap[card.key]?.show"
                                class="trend-inline"
                                :title="'Tendenz: ' + trendsMap[card.key].deltaLabel"
                            >
                                <i
                                    class="pi pi-arrow-right trend-icon"
                                    :class="trendsMap[card.key].colorClass"
                                    :style="{
                                        transform: `rotate(${trendsMap[card.key].angle}deg)`,
                                    }"
                                />
                                <span class="trend-label">Tendenz</span>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Linienchart -->
        <div class="col-12 md:col-9">
            <Card class="viz-card">
                <template #title>Monatliche Entwicklung</template>
                <template #content>
                    <div class="viz-body">
                        <Chart
                            type="line"
                            :data="lineData"
                            :options="lineOptions"
                            class="viz-line"
                        />
                    </div>
                </template>
            </Card>
        </div>

        <!-- Donutchart -->
        <div class="col-12 md:col-3">
            <Card class="viz-card">
                <template #title>Zusammensetzung</template>
                <template #content>
                    <div class="viz-body donut-layout">
                        <div class="pie-area">
                            <Chart
                                type="doughnut"
                                :data="multiPieData"
                                :options="multiPieOptions"
                                class="viz-donut"
                            />
                        </div>

                        <!-- Legende (de) -->
                        <div class="legend mt-2">
                            <div class="legend-row">
                                <span class="lg-dot" :style="{ background: '#16a34a' }"></span>
                                Budget (Außenring)
                                <span class="lg-dot ml-3" :style="{ background: '#7c3aed' }"></span>
                                Extra-Quota (Außenring)
                            </div>
                            <div class="legend-row">
                                <span class="lg-dot" :style="{ background: '#f59e0b' }"></span>
                                Budget prognostiziert
                                <span class="lg-dot ml-3" :style="{ background: '#86efac' }"></span>
                                Budget offen
                            </div>
                            <div class="legend-row">
                                <span class="lg-dot" :style="{ background: '#a78bfa' }"></span>
                                Quota prognostiziert
                                <span class="lg-dot ml-3" :style="{ background: '#ddd0ff' }"></span>
                                Quota offen
                            </div>
                            <div class="legend-row">
                                <span class="lg-dot" :style="{ background: '#7ba3ea' }"></span> Ist
                                (YTD)
                                <span class="lg-dot ml-3" :style="{ background: '#f1d97d' }"></span>
                                Forecast (Rest)
                                <span class="lg-dot ml-3" :style="{ background: '#94a3b8' }"></span>
                                Differenz zu Ziel
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Monatstabelle -->
        <div class="col-12">
            <Card>
                <template #title>Monatliche Werte (FY)</template>
                <template #content>
                    <PcMonthlyTable
                        :months="months"
                        :sales="sArr"
                        :budget="bArr"
                        :forecast="fArr"
                    />
                </template>
            </Card>
        </div>
    </div>
</template>

<script setup>
/**
 * PcOverview.vue
 * - Shows monthly cumulative charts and a 3-ring donut for Budget vs Extra-Quota vs Execution.
 * - All method/variable names and comments are in English. UI strings are in German.
 */
import { ref, computed, onMounted, watch } from 'vue'
import Card from 'primevue/card'
import Chart from 'primevue/chart'
import PcMonthlyTable from './PcMonthlyTable.vue'
import api from '@/plugins/axios'

const props = defineProps({
    profitCenterCode: { type: String, required: true },
    fiscalYearStart: { type: Number, default: null },
    asOf: { type: String, default: null }, // 'YYYY-MM'
    unit: { type: String, default: 'm3' }, // 'm3' | 'euro' | 'units'
})

/* PRINT meta (de) */
const unitLabel = computed(() =>
    props.unit === 'euro' ? 'EUR' : props.unit === 'units' ? 'VK-EH' : 'm³',
)
const createdAtDE = new Date().toLocaleDateString('de-DE')

/* Fetch overview data */
const data = ref(null)
async function fetchOverview() {
    if (!props.profitCenterCode) {
        data.value = null
        return
    }
    const params = { profit_center_code: props.profitCenterCode }
    if (props.fiscalYearStart) params.fiscal_year = props.fiscalYearStart
    if (props.asOf) params.as_of = props.asOf
    const { data: resp } = await api.get('/api/analytics/pc/overview', { params })
    data.value = resp
}
onMounted(fetchOverview)
watch(() => [props.profitCenterCode, props.fiscalYearStart, props.asOf, props.unit], fetchOverview)

/* Helpers */
const toNum = (v) =>
    typeof v === 'number' ? v : typeof v === 'string' ? Number(v.replace(/\./g, '')) || 0 : 0
const fmt = (v) =>
    Math.round(toNum(v))
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.')
const months = computed(() => data.value?.months || [])
const lastIdx = computed(() => Number(data.value?.last_complete_index ?? 0))
const nextIdx = computed(() => Math.min(11, lastIdx.value + 1))
const labelDE = (ym) => {
    const m = Number(ym?.slice(5, 7))
    const map = {
        4: 'Apr',
        5: 'Mai',
        6: 'Jun',
        7: 'Jul',
        8: 'Aug',
        9: 'Sep',
        10: 'Okt',
        11: 'Nov',
        12: 'Dez',
        1: 'Jan',
        2: 'Feb',
        3: 'Mär',
    }
    return map[m] || ym
}

/* Series per unit */
const unitKey = computed(() => (['m3', 'euro', 'units'].includes(props.unit) ? props.unit : 'm3'))
const sArr = computed(() => data.value?.raw?.monthly?.sales?.[unitKey.value] || [])
const bArr = computed(() => data.value?.raw?.monthly?.budgets?.[unitKey.value] || [])
const fArr = computed(() => data.value?.raw?.monthly?.forecast?.[unitKey.value] || [])

/* Math helpers */
const sum = (a) => a.reduce((acc, v) => acc + toNum(v), 0)
const cumSeries = (arr, upTo = 11) => {
    const out = []
    let acc = 0
    for (let i = 0; i <= upTo; i++) {
        acc += toNum(arr[i] || 0)
        out.push(acc)
    }
    return out
}

/* YTD + trend calcs */
const ytdSales = computed(() => sum(sArr.value.slice(0, lastIdx.value + 1)))
const ytdBudget = computed(() => sum(bArr.value.slice(0, lastIdx.value + 1)))
const ytdForecast = computed(() => sum(fArr.value.slice(0, lastIdx.value + 1)))
const attBudgetPct = computed(() =>
    ytdBudget.value > 0 ? (ytdSales.value / ytdBudget.value) * 100 : 0,
)
const attForecastPct = computed(() =>
    ytdForecast.value > 0 ? (ytdSales.value / ytdForecast.value) * 100 : 0,
)

const ytdLabels = computed(() =>
    months.value.slice(0, lastIdx.value + 1).map((ym) => labelDE(ym).slice(0, 1)),
)
const ratioSeriesPct = (numArr, denArr) => {
    const L = lastIdx.value
    const numCum = cumSeries(numArr, L),
        denCum = cumSeries(denArr, L)
    return numCum.map((n, i) => (toNum(denCum[i]) > 0 ? (n / denCum[i]) * 100 : 0))
}
const bandColor = (p) => {
    const v = Number(p) || 0
    if (v >= 96) return { line: '#16a34a', fill: '#16a34a22' }
    if (v >= 90) return { line: '#eab308', fill: '#eab30822' }
    if (v >= 80) return { line: '#f59e0b', fill: '#f59e0b22' }
    return { line: '#ef4444', fill: '#ef444422' }
}
const seriesBudArr = computed(() => ratioSeriesPct(sArr.value, bArr.value))
const seriesFcArr = computed(() => ratioSeriesPct(sArr.value, fArr.value))
const trendBud = computed(() => {
    const s = seriesBudArr.value
    const { line, fill } = bandColor(s.at(-1) ?? 0)
    return s.length
        ? {
              labels: ytdLabels.value,
              datasets: [
                  { data: s, borderColor: line, backgroundColor: fill, fill: true, tension: 0.3 },
              ],
          }
        : null
})
const trendFc = computed(() => {
    const s = seriesFcArr.value
    const { line, fill } = bandColor(s.at(-1) ?? 0)
    return s.length
        ? {
              labels: ytdLabels.value,
              datasets: [
                  { data: s, borderColor: line, backgroundColor: fill, fill: true, tension: 0.3 },
              ],
          }
        : null
})

/* 6M outlook */
const idx6 = computed(() => {
    const a = []
    for (let i = nextIdx.value; i < Math.min(12, nextIdx.value + 6); i++) a.push(i)
    return a
})
const future6Budget = computed(() =>
    idx6.value.reduce((acc, i) => acc + toNum(bArr.value[i] || 0), 0),
)
const future6Forecast = computed(() =>
    idx6.value.reduce((acc, i) => acc + toNum(fArr.value[i] || 0), 0),
)
const future6Pct = computed(() =>
    future6Budget.value > 0 ? (future6Forecast.value / future6Budget.value) * 100 : 0,
)

/* KPI cards */
const ytdPeriod = computed(() =>
    months.value.length
        ? `${labelDE(months.value[0])}–${labelDE(months.value[lastIdx.value])}`
        : '',
)
const pct = (p) => `${(Number(p) || 0).toFixed(1)}%`
const pctClass = (p) => {
    const n = Number(p) || 0
    return n >= 100 ? 'pct-good' : n >= 90 ? 'pct-warn' : n >= 80 ? 'pct-mid' : 'pct-bad'
}
const kpiCards = computed(() => [
    {
        key: 'ytd_s',
        title: 'YTLFM Ist',
        value: fmt(ytdSales.value),
        sub: `Zeitraum: ${ytdPeriod.value}`,
    },
    {
        key: 'ytd_b',
        title: 'YTLFM Budget',
        value: fmt(ytdBudget.value),
        sub: `inkl. Extra-Quota · ${ytdPeriod.value}`,
    },
    { key: 'ytd_f', title: 'YTLFM Forecast', value: fmt(ytdForecast.value), sub: ytdPeriod.value },
    {
        key: 'att_b',
        title: 'Budgeterreichung',
        kind: 'spark',
        pct: attBudgetPct.value,
        chart: trendBud.value,
        sub: 'Evolution (YTLFM)',
    },
    {
        key: 'att_f',
        title: 'Forecasterreichung',
        kind: 'spark',
        pct: attForecastPct.value,
        chart: trendFc.value,
        sub: 'Evolution (YTLFM)',
    },
    {
        key: 'sixm',
        title: 'Prognose',
        kind: 'spark',
        pct: future6Pct.value,
        chart: trendBud.value,
        sub: `Budget: ${fmt(future6Budget.value)} · Forecast: ${fmt(future6Forecast.value)}`,
    },
])

/* Spark options */
const sparkOptions = {
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: true } },
    elements: { point: { radius: 0 } },
    scales: {
        x: { display: true, ticks: { font: { size: 10 } }, grid: { display: false } },
        y: { display: false, grid: { display: false } },
    },
}

/* Cumulative line chart */
const budgetFY = computed(() => sum(bArr.value)) // includes extra quota from backend
const lineData = computed(() => ({
    labels: months.value,
    datasets: [
        {
            label: 'Ist',
            data: cumSeries(sArr.value),
            borderColor: '#2563eb',
            backgroundColor: '#2563eb33',
            fill: false,
            tension: 0.3,
        },
        {
            label: 'Budget',
            data: cumSeries(bArr.value),
            borderColor: '#16a34a',
            backgroundColor: '#16a34a33',
            fill: false,
            tension: 0.3,
        },
        {
            label: 'Forecast',
            data: cumSeries(fArr.value),
            borderColor: '#f59e0b',
            backgroundColor: '#f59e0b33',
            fill: false,
            tension: 0.3,
        },
        {
            label: 'Budget Ziel',
            data: Array(12).fill(budgetFY.value),
            borderColor: '#64748b',
            borderDash: [6, 6],
            fill: false,
            tension: 0,
        },
    ],
}))
const lineOptions = {
    maintainAspectRatio: false,
    layout: { padding: { top: 12, right: 12, left: 12, bottom: 12 } },
    plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${fmt(ctx.parsed.y)}` } },
    },
    scales: {
        x: { ticks: { callback: (v, i) => labelDE(months.value[i] || '') } },
        y: { beginAtZero: true, ticks: { callback: (v) => fmt(v) } },
    },
}

/* ==================== DONUT 3 RINGS ==================== */
/**
 * Ring 1 (outer): Base Budget vs Extra Quota (target composition)
 * Ring 2 (middle): From each origin: forecasted/used vs remaining
 * Ring 3 (inner): Execution: Sales YTD + Future Forecast (capped) + Gap to Target
 */

/* Extra quota totals from backend (allocated = extra budget, used = extra forecast) */
const extraQuotaTotal = computed(() =>
    toNum(data.value?.extra_quota?.allocated?.[unitKey.value] ?? 0),
)
const extraQuotaUsed = computed(() => toNum(data.value?.extra_quota?.used?.[unitKey.value] ?? 0))
const extraRemaining = computed(() => Math.max(0, extraQuotaTotal.value - extraQuotaUsed.value))

/* Target and base budget (base = target - extra) */
const targetTotal = computed(() => budgetFY.value)
const baseBudget = computed(() => Math.max(0, targetTotal.value - extraQuotaTotal.value))

/* Global forecast totals */
const forecastTotal = computed(() => sum(fArr.value))

/* Middle ring: split forecast between base and extra using the backend "used" for extra */
const forecastToExtra = computed(() => Math.min(extraQuotaTotal.value, extraQuotaUsed.value))
const forecastToBase = computed(() =>
    Math.max(0, Math.min(baseBudget.value, forecastTotal.value - forecastToExtra.value)),
)
const baseRemaining = computed(() => Math.max(0, baseBudget.value - forecastToBase.value))

/* Inner ring: execution decomposition (cap forecast to remaining target) */
const ytdCapped = computed(() => Math.min(ytdSales.value, targetTotal.value))
const remainingAfterYTD = computed(() => Math.max(0, targetTotal.value - ytdCapped.value))
const forecastFuture = computed(() => sum(fArr.value.slice(nextIdx.value)))
const forecastFutureShown = computed(() =>
    Math.min(remainingAfterYTD.value, Math.max(0, forecastFuture.value)),
)
const gapToTarget = computed(() =>
    Math.max(0, targetTotal.value - ytdCapped.value - forecastFutureShown.value),
)

/* Chart.js expects dataset[0] as OUTER ring for doughnut */
const multiPieData = computed(() => ({
    labels: [],
    datasets: [
        // OUTER – origin of target
        {
            label: 'Total Target',
            data: [baseBudget.value, extraQuotaTotal.value],
            backgroundColor: ['#16a34a', '#7c3aed'],
            borderWidth: 2,
        },
        // MIDDLE – usage by origin
        {
            label: 'Quota Usage',
            data: [
                forecastToBase.value,
                baseRemaining.value,
                forecastToExtra.value,
                extraRemaining.value,
            ],
            backgroundColor: ['#f59e0b', '#86efac', '#a78bfa', '#ddd0ff'],
            borderWidth: 2,
        },
        // INNER – execution vs target
        {
            label: 'Execution',
            data: [ytdCapped.value, forecastFutureShown.value, gapToTarget.value],
            backgroundColor: ['#7BA3EA', '#F1D97D', '#94a3b8'],
            borderWidth: 2,
        },
    ],
}))

/* Tooltip labels per ring (de) */
const ringLabels = [
    ['Budget', 'Extra-Quota'], // outer
    ['Budget prognostiziert', 'Budget offen', 'Quota prognostiziert', 'Quota offen'], // middle
    ['Ist (YTD)', 'Forecast (Rest)', 'Differenz zu Ziel'], // inner
]

const multiPieOptions = {
    maintainAspectRatio: false,
    radius: '98%',
    cutout: '25%',
    layout: { padding: 0 },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const ds = ctx.datasetIndex ?? 0
                    const di = ctx.dataIndex ?? 0
                    const seg = ringLabels[ds]?.[di] ?? ctx.dataset?.label ?? ''
                    const val = ctx.raw != null ? fmt(ctx.raw) : '0'
                    return `${seg}: ${val}`
                },
            },
        },
    },
}

/* Trend arrows (same logic) */
const deltaFromSeries = (arr) =>
    Array.isArray(arr) && arr.length >= 2 ? (arr.at(-1) ?? 0) - (arr.at(-2) ?? 0) : 0
function arrowForDelta(delta) {
    const d = Number(delta) || 0
    if (Math.abs(d) <= 2.5)
        return { angle: 0, colorClass: 'tr-yellow', show: true, deltaLabel: `${d.toFixed(1)} pp` }
    if (d > 2.5 && d <= 5)
        return { angle: -25, colorClass: 'tr-yellow', show: true, deltaLabel: `${d.toFixed(1)} pp` }
    if (d < -2.5 && d >= -5)
        return { angle: 25, colorClass: 'tr-yellow', show: true, deltaLabel: `${d.toFixed(1)} pp` }
    if (d > 5 && d <= 10)
        return { angle: -60, colorClass: 'tr-green', show: true, deltaLabel: `${d.toFixed(1)} pp` }
    if (d < -5 && d >= -10)
        return { angle: 60, colorClass: 'tr-red', show: true, deltaLabel: `${d.toFixed(1)} pp` }
    if (d > 10)
        return { angle: -90, colorClass: 'tr-green', show: true, deltaLabel: `${d.toFixed(1)} pp` }
    if (d < -10)
        return { angle: 90, colorClass: 'tr-red', show: true, deltaLabel: `${d.toFixed(1)} pp` }
    return { angle: 0, colorClass: 'tr-yellow', show: false, deltaLabel: '0.0 pp' }
}
const trendsMap = computed(() => ({
    att_b: arrowForDelta(deltaFromSeries(seriesBudArr.value)),
    att_f: arrowForDelta(deltaFromSeries(seriesFcArr.value)),
    sixm: arrowForDelta(deltaFromSeries(seriesBudArr.value)),
}))
</script>

<style>
.print-only {
    display: none !important;
}
.viz-card .p-card-header {
    display: none !important;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 20mm 16mm 16mm 16mm;
    }
    .print-only {
        display: block !important;
    }
    .viz-card .p-card-header {
        display: block !important;
    }
    .viz-card {
        margin-top: 8mm;
    }
}
</style>

<style scoped>
.pc-overview {
    --viz-body-h: 640px;
    padding: 10px 12px 12px 12px;
}

.kpi-card :deep(.p-card-content) {
    padding: 0.5rem 0.5rem !important;
    height: 160px;
}
.kpi-wrap {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}
.kpi-title {
    font-size: 0.85rem;
    color: #64748b;
}
.kpi-value {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1.2;
}
.kpi-sub {
    color: #94a3b8;
}

.spark-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.spark-zone {
    padding-top: 0.25rem;
}
.sparkline {
    height: 62px;
}

.subline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 0.25rem;
}
.trend-inline {
    display: flex;
    align-items: center;
    gap: 6px;
}
.trend-inline .trend-icon.pi {
    font-size: 1rem;
    display: inline-block;
    transition: transform 0.2s ease;
    color: var(--trend-color, currentColor) !important;
    opacity: 1 !important;
}
.trend-label {
    font-size: 0.8rem;
    color: #64748b;
}

.tr-yellow {
    --trend-color: #eab308;
}
.tr-green {
    --trend-color: #16a34a;
}
.tr-red {
    --trend-color: #ef4444;
}

.pct-good,
.pct-warn,
.pct-mid,
.pct-bad {
    font-weight: 700;
    font-size: 2.5rem;
}
.pct-good {
    color: #16a34a;
}
.pct-warn {
    color: #eab308;
}
.pct-mid {
    color: #f59e0b;
}
.pct-bad {
    color: #ef4444;
}

.viz-card :deep(.p-card-content) {
    display: flex;
    flex-direction: column;
    height: var(--viz-body-h);
    padding: 0.5rem 0.75rem !important;
}
.viz-body {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
.viz-line {
    flex: 1 1 auto;
    height: 100% !important;
    width: 100%;
}

.donut-layout {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    height: 100%;
}
.pie-area {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.viz-donut {
    height: 100% !important;
    width: 100% !important;
    max-height: 100%;
}

.legend {
    flex: 0 0 auto;
    color: #475569;
    font-size: 0.85rem;
}
.legend-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.15rem;
}
.lg-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

@media print {
    .pc-overview {
        --viz-body-h: 560px;
        padding-top: 6mm;
    }
    .viz-card :deep(.p-card-content) {
        height: var(--viz-body-h);
    }
    .report-print-header {
        margin: 0 0 8mm 0;
        padding-bottom: 4mm;
        border-bottom: 1px solid #ddd;
    }
    .report-print-header h2 {
        margin: 0 0 2mm 0;
        font-size: 18pt;
        font-weight: 700;
    }
    .report-print-header .meta {
        font-size: 11pt;
        color: #333;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .report-print-header .dot {
        opacity: 0.6;
    }
}
</style>