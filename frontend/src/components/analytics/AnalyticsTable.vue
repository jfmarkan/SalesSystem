<script setup>
import { computed } from 'vue'

const props = defineProps({
  months:   { type: Array,  required: true },
  sales:    { type: Array,  default: () => [] },   // preferido
  budget:   { type: Array,  default: () => [] },
  forecast: { type: Array,  default: () => [] },
  ventas:   { type: Array,  default: null },       // compat
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

function yyyymm(d) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }
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

// acepta números y strings "700.000"
function fmt0(v){
  const n = Math.round(Number(String(v ?? 0).replace(/\./g,'')))
  return n.toLocaleString('de-DE', { maximumFractionDigits: 0 })
}

// --- helpers de desvío
function devPct(num, den){ if(!den) return 0; return (num/den - 1) * 100 }
function clsSalesDev(v,b){ const d=Math.abs(devPct(Number(String(v).replace(/\./g,'')), Number(String(b).replace(/\./g,'')))); if(d>10) return 'dev-red'; if(d>5) return 'dev-orange'; if(d>2) return 'dev-yellow'; return 'dev-green' }
function clsFcstDev(v,b){ const d=Math.abs(devPct(Number(String(v).replace(/\./g,'')), Number(String(b).replace(/\./g,'')))); if(d>5) return 'dev-red'; if(d>2) return 'dev-yellow'; return 'dev-green' }
function pctLabel(num, den){
  const n = Number(String(num ?? 0).replace(/\./g,'')), d = Number(String(den ?? 0).replace(/\./g,''))
  if(!d) return '0%'
  return Math.round((n/d)*100) + '%'
}

const salesData = computed(() => {
  if (Array.isArray(props.sales) && props.sales.length) return props.sales
  if (Array.isArray(props.ventas)) return props.ventas
  return Array(props.months?.length || 12).fill(0)
})
</script>

<template>
  <div class="table-shell">
    <div class="table-scroll-x">
      <table class="w-full" style="min-width: 1200px; border-collapse: separate; border-spacing: 0">
        <thead>
          <tr>
            <th class="p-2 text-left sticky left-0 z-2 stick-left"></th>
            <th v-for="(m, i) in months" :key="'m' + i" class="p-2 text-center stick-head"
                :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx, 'cur-top': i === curIdx }">
              {{ fmtMonthDE(m) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <!-- Ist -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Ist</td>
            <td v-for="(m, i) in months" :key="'v' + i" class="p-2 text-center cell cell-sales text-strong"
                :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }">
              {{ fmt0(salesData[i] ?? 0) }}
            </td>
          </tr>

          <!-- Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Budget</td>
            <td v-for="(m, i) in months" :key="'b' + i" class="p-2 text-center cell cell-budget text-strong"
                :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }">
              {{ fmt0(budget[i] ?? 0) }}
            </td>
          </tr>

          <!-- Forecast (READ-ONLY) -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Forecast</td>
            <td v-for="(m, i) in months" :key="'f' + i"
                class="p-2 text-center cell text-strong"
                :class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx, 'cur-bottom': i === curIdx }">
              <div class="ro-forecast">{{ fmt0(forecast[i] ?? 0) }}</div>
            </td>
          </tr>

          <!-- % Ist / Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">% Ist / Bud.</td>
            <td v-for="(m, i) in months" :key="'ivb' + i"
                class="p-2 text-center cell dev-cell"
                :class="[ clsSalesDev(salesData[i] ?? 0, budget[i] ?? 0), { 'cur-left': i === curIdx, 'cur-right': i === curIdx } ]">
              {{ pctLabel(salesData[i] ?? 0, budget[i] ?? 0) }}
            </td>
          </tr>

          <!-- % Forecast / Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">% For. / Bud.</td>
            <td v-for="(m, i) in months" :key="'ifb' + i"
                class="p-2 text-center cell dev-cell"
                :class="[ clsFcstDev(forecast[i] ?? 0, budget[i] ?? 0), { 'cur-left': i === curIdx, 'cur-right': i === curIdx } ]">
              {{ pctLabel(forecast[i] ?? 0, budget[i] ?? 0) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
:root{
  --blue:#54849a; --green:#05a46f; --yellow:#e6b729; --orange:#e88d1e; --orangeDeep:#ea6312; --red:#b01513;
}
.table-shell{ height:100%; overflow:hidden; display:flex; flex-direction:column; }
.table-scroll-x{ overflow-x:auto; overflow-y:hidden; height:100%; }

.stick-head{ position:sticky; top:0; background: var(--surface-card); z-index:1; }
.stick-left{ width:calc(100%/13); left:0; background: var(--surface-card); }

.cell{ border-bottom:1px solid rgba(0,0,0,0.06); }
.cur-left{ border-left:2px solid var(--red)!important; }
.cur-right{ border-right:2px solid var(--red)!important; }
.cur-top{ border-top:2px solid var(--red)!important; }
.cur-bottom{ border-bottom:2px solid var(--red)!important; }

.cell-sales{ background: rgba(31,86,115,0.18); }
.cell-budget{ background: rgba(84,132,154,0.18); }
.text-strong{ color: var(--text-color); font-weight: 600; }

.ro-forecast{ padding:.25rem .5rem; border:1px solid rgba(0,0,0,0.08); border-radius:6px; background: rgba(0,0,0,0.04); }

.dev-cell{ transition: background-color .2s ease, color .2s ease; }
.dev-red{ background: rgba(176,21,19,0.18); color: #3b0d0d; }
.dev-orange{ background: rgba(234,99,18,0.18); color: #3b260d; }
.dev-yellow{ background: rgba(230,183,41,0.2); color: #3a300b; }
.dev-green{ background: rgba(5,164,111,0.18); color: #093a2c; }

@media (prefers-color-scheme: dark) {
  .stick-head,.stick-left{ background: var(--surface-card); }
  .cell-sales{ background: rgba(31,86,115,0.28); }
  .cell-budget{ background: rgba(84,132,154,0.28); }
  .ro-forecast{ background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.12); }

  /* Números en blanco en las dos filas de desvíos */
  .dev-cell,
  .dev-cell.dev-red,
  .dev-cell.dev-orange,
  .dev-cell.dev-yellow,
  .dev-cell.dev-green{
    color:#fff !important;
  }
}
</style>