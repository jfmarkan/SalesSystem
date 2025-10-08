<script setup>
import { computed } from 'vue'

const props = defineProps({
  months:   { type: Array,  required: true },      // ['2024-04', ...]
  sales:    { type: [Array, Object], default: () => [] },
  budget:   { type: [Array, Object], default: () => [] },
  forecast: { type: [Array, Object], default: () => [] },
  // compat antiguo
  ventas:   { type: [Array, Object], default: null },
})
const emit = defineEmits(['edit-forecast'])

function fmtMonthDE(ym) {
  if (!ym) return '—'
  const [yS, mS] = String(ym).split('-')
  const y = yS?.slice(2) ?? ''
  const m = parseInt(mS || '1', 10)
  const map = ['Jän','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez']
  return `${map[m - 1] || '—'} ${y}`
}

function yyyymm(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}
function thirdWednesday(d = new Date()) {
  const first = new Date(d.getFullYear(), d.getMonth(), 1)
  const wd = first.getDay(), delta = (3 - wd + 7) % 7
  const firstWed = new Date(first); firstWed.setDate(1 + delta)
  const third = new Date(firstWed); third.setDate(firstWed.getDate() + 14)
  return third
}
function isEditableYM() { return false } // RO acá

const curIdx = computed(() => {
  const key = yyyymm(new Date())
  return Array.isArray(props.months) ? props.months.findIndex((m) => m === key) : -1
})

/* === Parsing seguro (no rescalar) === */
function toNumber(v) {
  if (typeof v === 'number') return Number.isFinite(v) ? v : 0
  if (v == null) return 0
  let s = String(v).trim()
  if (s === '') return 0
  s = s.replace(/[\s\u00A0\u202F]/g, '') // quita espacios normales y NBSP/NNBSP
  s = s.replace(/\./g, '')              // quita miles
  s = s.replace(',', '.')               // coma decimal → punto
  const n = Number(s)
  return Number.isFinite(n) ? n : 0
}

/* Acomoda cualquier fuente (array u objeto por 'YYYY-MM') a un array
   de largo = months, indexado exactamente por etiqueta de mes */
function normalizeSeries(src, months, fallback = []) {
  const source = src ?? fallback ?? []
  if (Array.isArray(source)) {
    // si ya viene como array, pad/truncate y parsear números
    const out = months.map((_, i) => toNumber(source[i] ?? 0))
    return out
  }
  if (source && typeof source === 'object') {
    // si viene como mapa {'2024-04': 123, ...}
    return months.map((ym) => toNumber(source[ym] ?? 0))
  }
  return months.map(() => 0)
}

// formateo de salida (solo visual)
function fmt0(v) {
  const n = Math.round(toNumber(v))
  return n.toLocaleString('de-DE', { maximumFractionDigits: 0 })
}

// --- helpers de desvío
function devPct(num, den) {
  const n = toNumber(num), d = toNumber(den)
  if (!d) return 0
  return (n / d - 1) * 100
}
function clsSalesDev(v, b) {
  const d = Math.abs(devPct(v, b))
  if (d > 10) return 'dev-red'
  if (d > 5) return 'dev-orange'
  if (d > 2) return 'dev-yellow'
  return 'dev-green'
}
function clsFcstDev(v, b) {
  const d = Math.abs(devPct(v, b))
  if (d > 5) return 'dev-red'
  if (d > 2) return 'dev-yellow'
  return 'dev-green'
}
function pctLabel(num, den) {
  const n = toNumber(num), d = toNumber(den)
  if (!d) return '0%'
  return Math.round((n / d) * 100) + '%'
}

/* === series ya normalizadas y NUMÉRICAS, alineadas a props.months === */
const normSales    = computed(() => normalizeSeries(
  (Array.isArray(props.sales) && props.sales.length ? props.sales : props.ventas),
  props.months || [],
  [],
))
const normBudget   = computed(() => normalizeSeries(props.budget,   props.months || [], []))
const normForecast = computed(() => normalizeSeries(props.forecast, props.months || [], []))
</script>

<template>
  <div class="table-shell">
    <div class="table-scroll-x">
      <table class="w-full" style="min-width: 1200px; border-collapse: separate; border-spacing: 0">
        <thead>
          <tr>
            <th class="p-2 text-left sticky left-0 z-2 stick-left"></th>
            <th
              v-for="(m, i) in months"
              :key="m"
              class="p-2 text-center stick-head"
              :class="{
                'cur-left':  i === curIdx,
                'cur-right': i === curIdx,
                'cur-top':   i === curIdx,
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
              :key="'v' + m"
              class="p-2 text-center cell cell-sales text-strong"
              :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }"
            >
              {{ fmt0(normSales[i] ?? 0) }}
            </td>
          </tr>

          <!-- Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Budget</td>
            <td
              v-for="(m, i) in months"
              :key="'b' + m"
              class="p-2 text-center cell cell-budget text-strong"
              :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }"
            >
              {{ fmt0(normBudget[i] ?? 0) }}
            </td>
          </tr>

          <!-- Forecast (READ-ONLY) -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Forecast</td>
            <td
              v-for="(m, i) in months"
              :key="'f' + m"
              class="p-2 text-center cell text-strong"
              :class="{
                'cur-left': i === curIdx,
                'cur-right': i === curIdx,
                'cur-bottom': i === curIdx,
              }"
            >
              <div class="ro-forecast">{{ fmt0(normForecast[i] ?? 0) }}</div>
            </td>
          </tr>

          <!-- % Ist / Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">% Ist / Bud.</td>
            <td
              v-for="(m, i) in months"
              :key="'ivb' + m"
              class="p-2 text-center cell dev-cell"
              :class="[
                clsSalesDev(normSales[i] ?? 0, normBudget[i] ?? 0),
                { 'cur-left': i === curIdx, 'cur-right': i === curIdx },
              ]"
            >
              {{ pctLabel(normSales[i] ?? 0, normBudget[i] ?? 0) }}
            </td>
          </tr>

          <!-- % Forecast / Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">% For. / Bud.</td>
            <td
              v-for="(m, i) in months"
              :key="'ifb' + m"
              class="p-2 text-center cell dev-cell"
              :class="[
                clsFcstDev(normForecast[i] ?? 0, normBudget[i] ?? 0),
                { 'cur-left': i === curIdx, 'cur-right': i === curIdx },
              ]"
            >
              {{ pctLabel(normForecast[i] ?? 0, normBudget[i] ?? 0) }}
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

.cell { border-bottom: 1px solid rgba(0, 0, 0, 0.06); }
.cur-left   { border-left:   2px solid var(--red) !important; }
.cur-right  { border-right:  2px solid var(--red) !important; }
.cur-top    { border-top:    2px solid var(--red) !important; }
.cur-bottom { border-bottom: 2px solid var(--red) !important; }

.cell-sales  { background: rgba(31, 86, 115, 0.18); }
.cell-budget { background: rgba(84, 132, 154, 0.18); }
.text-strong { color: var(--text-color); font-weight: 600; }

.ro-forecast {
  padding: 0.25rem 0.5rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.04);
}

.dev-cell { transition: background-color 0.2s ease, color 0.2s ease; }
.dev-red    { background: rgba(176, 21, 19, 0.18); color: #3b0d0d; }
.dev-orange { background: rgba(234, 99, 18, 0.18); color: #3b260d; }
.dev-yellow { background: rgba(230, 183, 41, 0.2); color: #3a300b; }
.dev-green  { background: rgba(5, 164, 111, 0.18); color: #093a2c; }

@media (prefers-color-scheme: dark) {
  .stick-head, .stick-left { background: var(--surface-card); }
  .cell-sales  { background: rgba(31, 86, 115, 0.28); }
  .cell-budget { background: rgba(84, 132, 154, 0.28); }
  .ro-forecast { background: rgba(255, 255, 255, 0.06); border-color: rgba(255, 255, 255, 0.12); }

  .dev-cell,
  .dev-cell.dev-red,
  .dev-cell.dev-orange,
  .dev-cell.dev-yellow,
  .dev-cell.dev-green {
    color: #fff !important;
  }
}
</style>