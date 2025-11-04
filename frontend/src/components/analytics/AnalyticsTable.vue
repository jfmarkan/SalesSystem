<script setup>
import { computed, ref, onMounted, nextTick, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  months: { type: Array, required: true },
  sales: { type: [Array, Object], default: () => [] },
  budget: { type: [Array, Object], default: () => [] },
  forecast: { type: [Array, Object], default: () => [] },
  ventas: { type: [Array, Object], default: null },
  viewportStart: { type: Number, default: 0 },
  viewportSize: { type: Number, default: 12 },
})

function yyyymm(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function fmtMonthDE(ym) {
  if (!ym) return '—'
  const [yS, mS] = String(ym).split('-')
  const y = yS?.slice(2) ?? ''
  const m = parseInt(mS || '1', 10)
  const map = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
  return `${map[m - 1] || '—'} ${y}`
}

const curIdx = computed(() => {
  const key = yyyymm(new Date())
  return Array.isArray(props.months) ? props.months.findIndex((m) => m === key) : -1
})

function toNumber(v) {
  if (typeof v === 'number') return Number.isFinite(v) ? v : 0
  if (v == null) return 0
  let s = String(v).trim()
  if (s === '') return 0
  s = s.replace(/[\s\u00A0\u202F]/g, '').replace(/\./g, '').replace(',', '.')
  const n = Number(s)
  return Number.isFinite(n) ? n : 0
}

function normalizeSeries(src, months, fallback = []) {
  const source = src ?? fallback ?? []
  if (Array.isArray(source)) {
    return months.map((_, i) => toNumber(source[i] ?? 0))
  }
  if (source && typeof source === 'object') {
    return months.map((ym) => toNumber(source[ym] ?? 0))
  }
  return months.map(() => 0)
}

const sales = computed(() => normalizeSeries(
  Array.isArray(props.sales) && props.sales.length ? props.sales : props.ventas,
  props.months
))
const budget = computed(() => normalizeSeries(props.budget, props.months))
const forecast = computed(() => normalizeSeries(props.forecast, props.months))

function pctLabel(num, den) {
  if (!den) return '0%'
  return new Intl.NumberFormat('de-DE', {
    style: 'percent',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num / den)
}

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

function formatNumber(v) {
  const n = parseFloat(v)
  if (isNaN(n)) return '—'
  return new Intl.NumberFormat('de-DE', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(n)
}

/* Layout responsivo */
const container = ref(null)
const scroller = ref(null)
const leftStickyW = ref(150)
const colW = ref(96)

const viewportWidth = computed(() => leftStickyW.value + props.viewportSize * colW.value)
const tableMinWidth = computed(() => leftStickyW.value + (props.months?.length || 0) * colW.value)

let ro = null
function recomputeColW() {
  if (!container.value) return
  const full = container.value.clientWidth || 0
  const usable = Math.max(200, full - leftStickyW.value)
  const w = Math.floor(usable / props.viewportSize)
  colW.value = Math.max(70, Math.min(180, w))
  nextTick(() => scrollToIndex(props.viewportStart, false))
}
onMounted(() => {
  recomputeColW()
  ro = new ResizeObserver(recomputeColW)
  ro.observe(container.value)
})
onBeforeUnmount(() => {
  try { ro?.disconnect() } catch {}
})
function scrollToIndex(i, smooth = true) {
  const target = Math.max(0, i) * colW.value
  scroller.value?.scrollTo({ left: target, top: 0, behavior: smooth ? 'smooth' : 'auto' })
}
defineExpose({ scrollToIndex })
watch(() => props.viewportStart, (v) => nextTick(() => scrollToIndex(v)))
</script>

<template>
  <div class="table-shell" ref="container">
    <div class="table-scroll-x" ref="scroller" :style="{ width: viewportWidth + 'px' }">
      <table
        class="tbl"
        :style="{
          minWidth: tableMinWidth + 'px',
          borderCollapse: 'separate',
          borderSpacing: '0',
        }"
      >
        <thead>
          <tr>
            <th class="p-2 text-left sticky left-0 z-3 stick-left head-left"
                :style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }">
            </th>
            <th
              v-for="(m, i) in props.months"
              :key="'m' + i"
              class="p-2 text-center stick-head month-col"
              :style="{ minWidth: colW + 'px', width: colW + 'px' }"
              :class="{
                'cur-left': i === curIdx,
                'cur-right': i === curIdx,
                'cur-top': i === curIdx,
                'head-current': i === curIdx,
              }"
            >
              {{ fmtMonthDE(m) }}
            </th>
          </tr>
        </thead>

        <tbody>
          <tr>
            <td class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
                :style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }">
              Ist
            </td>
            <td v-for="(m, i) in props.months"
                :key="'v' + i"
                class="p-2 text-center cell cell-sales"
                :style="{ minWidth: colW + 'px', width: colW + 'px' }"
                :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }">
              {{ formatNumber(sales[i]) }}
            </td>
          </tr>

          <tr>
            <td class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
                :style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }">
              Budget
            </td>
            <td v-for="(m, i) in props.months"
                :key="'b' + i"
                class="p-2 text-center cell cell-budget"
                :style="{ minWidth: colW + 'px', width: colW + 'px' }"
                :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }">
              {{ formatNumber(budget[i]) }}
            </td>
          </tr>

          <tr>
            <td class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
                :style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }">
              Forecast
            </td>
            <td v-for="(m, i) in props.months"
                :key="'f' + i"
                class="p-2 text-center cell"
                :style="{ minWidth: colW + 'px', width: colW + 'px' }"
                :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx, 'cur-bottom': i === curIdx }">
              <div class="ro-forecast">{{ formatNumber(forecast[i]) }}</div>
            </td>
          </tr>

          <tr>
            <td class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
                :style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }">
              % Ist / Bud.
            </td>
            <td v-for="(m, i) in props.months"
                :key="'ivb' + i"
                class="p-2 text-center cell dev-cell"
                :style="{ minWidth: colW + 'px', width: colW + 'px' }"
                :class="[clsSalesDev(sales[i], budget[i]), { 'cur-left': i === curIdx, 'cur-right': i === curIdx }]">
              {{ pctLabel(sales[i], budget[i]) }}
            </td>
          </tr>

          <tr>
            <td class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
                :style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }">
              % For. / Bud.
            </td>
            <td v-for="(m, i) in props.months"
                :key="'ifb' + i"
                class="p-2 text-center cell dev-cell"
                :style="{ minWidth: colW + 'px', width: colW + 'px' }"
                :class="[clsFcstDev(forecast[i], budget[i]), { 'cur-left': i === curIdx, 'cur-right': i === curIdx }]">
              {{ pctLabel(forecast[i], budget[i]) }}
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
	width: 100%;
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
	text-align: right;
	background: var(--surface-card);
}

.cell {
	border-bottom: 1px solid rgba(0, 0, 0, 0.06);
	text-align: center;
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
	transition: background-color 0.2s ease, color 0.2s ease;
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
