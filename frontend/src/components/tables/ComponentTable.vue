<script setup>
/* Table with editable forecast cells + reactive deviation colors (EU number format). */
import { computed } from 'vue'
import InputText from 'primevue/inputtext'

const props = defineProps({
  months:   { type: Array,  required: true },
  sales:    { type: Array,  default: () => [] },
  budget:   { type: Array,  default: () => [] },
  forecast: { type: Array,  default: () => [] },
  // TEMP backwards-compat
  ventas:   { type: Array,  default: null },
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

function yyyymm(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}` }

function thirdWednesday(d = new Date()) {
  const first = new Date(d.getFullYear(), d.getMonth(), 1)
  const deltaToWed = (3 - first.getDay() + 7) % 7
  const firstWed = new Date(first); firstWed.setDate(1 + deltaToWed)
  const third = new Date(firstWed); third.setDate(firstWed.getDate() + 14)
  return third
}

function isEditableYM(ym) {
  const now = new Date()
  const cur = new Date(now.getFullYear(), now.getMonth(), 1)
  const [yS, mS] = String(ym).split('-')
  const target = new Date(+yS, +mS - 1, 1)
  if (target <= cur) return false
  const next = new Date(cur.getFullYear(), cur.getMonth() + 1, 1)
  if (+target === +next) return now <= thirdWednesday(now)
  return true
}

const curIdx = computed(() => {
  const key = yyyymm(new Date())
  return Array.isArray(props.months) ? props.months.findIndex(m => m === key) : -1
})

function devPct(num, den) {
  if (!den) return 0
  return (num / den - 1) * 100
}

function clsSalesDev(v, b) {
  const d = Math.abs(devPct(v, b))
  if (d > 10) return 'dev-red'
  if (d > 5)  return 'dev-orange'
  if (d > 2)  return 'dev-yellow'
  return 'dev-green'
}

function clsFcstDev(v, b) {
  const d = Math.abs(devPct(v, b))
  if (d > 5) return 'dev-red'
  if (d > 2) return 'dev-yellow'
  return 'dev-green'
}

// ✅ NUEVA función para formatear números con . y ,
function formatNumber(value) {
  const number = parseFloat(value)
  if (isNaN(number)) return '—'
  return new Intl.NumberFormat('de-DE', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(number)
}

// ✅ NUEVA función para formatear porcentaje al estilo europeo
function pctLabel(num, den) {
  if (!den) return '0%'
  const pct = (num / den) * 100
  return new Intl.NumberFormat('de-DE', {
    style: 'percent',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(pct / 100)
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
      <table class="w-full" style="min-width:1200px; border-collapse:separate; border-spacing:0">
        <thead>
          <tr>
            <th class="p-2 text-left sticky left-0 z-2 stick-left"></th>
            <th
              v-for="(m, i) in months" :key="'m'+i"
              class="p-2 text-center stick-head"
              :class="{ 'cur-left': i===curIdx, 'cur-right': i===curIdx, 'cur-top': i===curIdx }"
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
              v-for="(m,i) in months" :key="'v'+i"
              class="p-2 text-center cell cell-sales"
              :class="{ 'cur-left': i===curIdx, 'cur-right': i===curIdx }"
            >
              {{ formatNumber(salesData[i] ?? 0) }}
            </td>
          </tr>

          <!-- Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Budget</td>
            <td
              v-for="(m,i) in months" :key="'b'+i"
              class="p-2 text-center cell cell-budget"
              :class="{ 'cur-left': i===curIdx, 'cur-right': i===curIdx }"
            >
              {{ formatNumber(budget[i] ?? 0) }}
            </td>
          </tr>

          <!-- Forecast (editable) -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Forecast</td>
            <td
              v-for="(m,i) in months" :key="'f'+i"
              class="p-1 cell"
              :class="{ 'cur-left': i===curIdx, 'cur-right': i===curIdx, 'cur-bottom': i===curIdx }"
            >
              <InputText
                class="w-full p-inputtext-sm text-center inp-forecast"
                :value="forecast[i]"
                :disabled="!isEditableYM(m)"
                @input="e => { if (isEditableYM(m)) emit('edit-forecast', { index:i, value:e.target.value }) }"
              />
            </td>
          </tr>

          <!-- % Ist / Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">% Ist / Bud.</td>
            <td
              v-for="(m,i) in months" :key="'ivb'+i"
              class="p-2 text-center cell dev-cell"
              :class="[ clsSalesDev(salesData[i] ?? 0, budget[i] ?? 0), { 'cur-left': i===curIdx, 'cur-right': i===curIdx } ]"
            >
              {{ pctLabel(salesData[i] ?? 0, budget[i] ?? 0) }}
            </td>
          </tr>

          <!-- % Forecast / Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">% For. / Bud.</td>
            <td
              v-for="(m,i) in months" :key="'ifb'+i"
              class="p-2 text-center cell dev-cell"
              :class="[ clsFcstDev(forecast[i] ?? 0, budget[i] ?? 0), { 'cur-left': i===curIdx, 'cur-right': i===curIdx } ]"
            >
              {{ pctLabel(forecast[i] ?? 0, budget[i] ?? 0) }}
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

.table-shell{ height:100%; overflow:hidden; display:flex; flex-direction:column; color:inherit; }
.table-scroll-x{ overflow-x:auto; overflow-y:hidden; height:100%; }

/* Sticky */
.stick-head{ position:sticky; top:0; }
.stick-left{ width:calc(100% / 13); left:0; }
.cell{ border-bottom:1px solid rgba(0,0,0,.06); }

/* Current month frame */
.cur-left{ border-left:2px solid var(--red) !important; }
.cur-right{ border-right:2px solid var(--red) !important; }
.cur-top{ border-top:2px solid var(--red) !important; }
.cur-bottom{ border-bottom:2px solid var(--red) !important; }

/* Header bg glass */
thead th{
  text-align:left; font-weight:600; padding:8px 10px;
  border-bottom:1px solid rgba(2,6,23,.12);
  background: rgba(255,255,255,.35);
  backdrop-filter: blur(6px);
}
@media (prefers-color-scheme: dark){
  thead th{ border-bottom-color: rgba(255,255,255,.16); background: rgba(0,0,0,.25); color:#f8fafc; }
}
:global(.dark) thead th{ border-bottom-color: rgba(255,255,255,.16); background: rgba(0,0,0,.25); color:#f8fafc; }

/* Row tints */
.cell-sales{ background: rgba(31,86,115,.12); }
.cell-budget{ background: rgba(84,132,154,.12); }
@media (prefers-color-scheme: dark){
  .cell-sales{ background: rgba(255,255,255,.06); }
  .cell-budget{ background: rgba(255,255,255,.06); }
}
:global(.dark) .cell-sales{ background: rgba(255,255,255,.06); }
:global(.dark) .cell-budget{ background: rgba(255,255,255,.06); }

/* Forecast input: theme-aware */
.inp-forecast{
  background: rgba(255,255,255,.9) !important;
  color:#0f172a !important;
  border:1px solid rgba(2,6,23,.2) !important;
  border-radius:6px;
}
.inp-forecast::placeholder{ color: rgba(15,23,42,.55); }
@media (prefers-color-scheme: dark){
  .inp-forecast{
    background: rgba(255,255,255,.12) !important;
    color:#f8fafc !important;
    border-color: rgba(255,255,255,.24) !important;
  }
  .inp-forecast::placeholder{ color: rgba(248,250,252,.6); }
}
:global(.dark) .inp-forecast{
  background: rgba(255,255,255,.12) !important;
  color:#f8fafc !important;
  border-color: rgba(255,255,255,.24) !important;
}
:global(.dark) .inp-forecast::placeholder{ color: rgba(248,250,252,.6); }

/* Deviation cells: readable in both themes */
.dev-cell{
  transition: background-color .2s ease, color .2s ease;
  font-variant-numeric: tabular-nums;
}
/* Light */
.dev-red{    background: rgba(176,21,19,.16);    color:#3b0d0d; }
.dev-orange{ background: rgba(234,99,18,.16);    color:#3b260d; }
.dev-yellow{ background: rgba(230,183,41,.18);   color:#3a300b; }
.dev-green{  background: rgba(5,164,111,.16);    color:#093a2c; }
/* Dark (override) */
@media (prefers-color-scheme: dark){
  .dev-red{    background: rgba(239,68,68,.28);  color:#fff; }
  .dev-orange{ background: rgba(245,158,11,.28); color:#fff; }
  .dev-yellow{ background: rgba(234,179,8,.28);  color:#111; } /* amarillo permite texto oscuro */
  .dev-green{  background: rgba(34,197,94,.28);  color:#0b1f16; }
}
:global(.dark) .dev-red{    background: rgba(239,68,68,.28);  color:#fff; }
:global(.dark) .dev-orange{ background: rgba(245,158,11,.28); color:#fff; }
:global(.dark) .dev-yellow{ background: rgba(234,179,8,.28);  color:#111; }
:global(.dark) .dev-green{  background: rgba(34,197,94,.28);  color:#0b1f16; }
</style>