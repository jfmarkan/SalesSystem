<script setup>
/* Table with editable forecast cells. Emits edit events. Horizontal scroll only. */
import InputText from 'primevue/inputtext'

const props = defineProps({
  months:   { type: Array, required: true }, // e.g. ['2025-04', ...]
  ventas:   { type: Array, required: true },
  budget:   { type: Array, required: true },
  forecast: { type: Array, required: true }
})
const emit = defineEmits(['edit-forecast'])

function pct(num, den){
  const n = Number(num) || 0
  const d = Number(den) || 0
  if (!d) return '0%'
  return Math.round((n / d) * 100) + '%'
}

/* DE month labels */
const DE_ABBR = ['Jän','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez']
function formatMonthDE(key){
  if (typeof key !== 'string') return String(key ?? '')
  const m = key.match(/^(\d{4})-(\d{2})(?:-\d{2})?$/)
  if (!m) return key
  const y = m[1]
  const mm = Math.max(1, Math.min(12, parseInt(m[2],10)))
  return `${DE_ABBR[mm-1]} ${y.slice(2)}`
}

/* Deviation classes:
   ventas/budget: |dev|>10 red, >5 orange, >2 yellow, else green
   forecast/budget: |dev|>5 red, >2 yellow, else green
   dev = |(value/budget)*100 - 100|  */
function devFrom(value, budget){
  const b = Number(budget) || 0
  const v = Number(value) || 0
  if (!b) return null
  return Math.abs((v/b)*100 - 100)
}
function devClassSales(i){
  const d = devFrom(props.ventas[i], props.budget[i])
  if (d === null) return 'dev-neutral'
  if (d > 10) return 'dev-red'
  if (d > 5)  return 'dev-orange'
  if (d > 2)  return 'dev-yellow'
  return 'dev-green'
}
function devClassForecast(i){
  const d = devFrom(props.forecast[i], props.budget[i])
  if (d === null) return 'dev-neutral'
  if (d > 5)  return 'dev-red'
  if (d > 2)  return 'dev-yellow'
  return 'dev-green'
}
</script>

<template>
  <div class="table-shell">
    <div class="table-scroll-x">
      <table class="w-full" style="min-width: 1200px; border-collapse: separate; border-spacing: 0;">
        <thead>
          <tr>
            <th class="p-2 text-left sticky left-0 z-2 stick-left rounded-tl-md">Begriff</th>
            <th v-for="(m,i) in months" :key="'m'+i" class="p-2 text-center stick-head">
              {{ formatMonthDE(m) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr class="row-sales">
            <td class="p-2 sticky left-0 z-2 stick-left">Verkauf</td>
            <td v-for="(m,i) in months" :key="'v'+i" class="p-2 text-right cell">
              {{ ventas[i] }}
            </td>
          </tr>

          <tr class="row-budget">
            <td class="p-2 sticky left-0 z-2 stick-left">Budget</td>
            <td v-for="(m,i) in months" :key="'b'+i" class="p-2 text-right cell">
              {{ budget[i] }}
            </td>
          </tr>

          <tr>
            <td class="p-2 sticky left-0 z-2 stick-left">Prognose</td>
            <td v-for="(m,i) in months" :key="'f'+i" class="p-1 cell">
              <InputText
                class="w-full p-inputtext-sm text-right input-forecast"
                :value="forecast[i]"
                @input="e=>emit('edit-forecast',{ index:i, value:e.target.value })"
              />
            </td>
          </tr>

          <tr>
            <td class="p-2 sticky left-0 z-2 stick-left">% Verkauf / Budget</td>
            <td v-for="(m,i) in months" :key="'ivb'+i" class="p-2 text-right cell dev" :class="devClassSales(i)">
              {{ pct(ventas[i], budget[i]) }}
            </td>
          </tr>

          <tr>
            <td class="p-2 sticky left-0 z-2 stick-left">% Prognose / Budget</td>
            <td v-for="(m,i) in months" :key="'ifb'+i" class="p-2 text-right cell dev" :class="devClassForecast(i)">
              {{ pct(forecast[i], budget[i]) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.table-shell{ height: 100%; overflow: hidden; display: flex; flex-direction: column; }
.table-scroll-x{ overflow-x: auto; overflow-y: hidden; height: 100%; }

/* Sticky header/left with glass */
.stick-head{
  position: sticky; top: 0;
  background: var(--glass);
  backdrop-filter: blur(var(--blur));
  -webkit-backdrop-filter: blur(var(--blur));
  box-shadow: 0 1px 0 rgba(0,0,0,0.08);
  z-index: 1;
}
.stick-left{
  background: var(--glass);
  backdrop-filter: blur(var(--blur));
  -webkit-backdrop-filter: blur(var(--blur));
  left: 0; min-width: 180px;
  box-shadow: 1px 0 0 rgba(0,0,0,0.08);
}

/* Base cells */
.cell{ border-bottom: 1px solid rgba(0,0,0,0.06); white-space: nowrap; }

/* Row tints */
.row-sales > td:not(.stick-left){ background: rgba(var(--c-blue-rgb), 0.10); }
.row-budget > td:not(.stick-left){ background: rgba(14,165,233,0.10); } /* celestito suave */

/* Forecast input -> glass-friendly */
.input-forecast :deep(.p-inputtext){
  background: rgba(255,255,255,0.55);
  border: 1px solid rgba(0,0,0,0.08);
  color: var(--txt);
}

/* Deviation colors */
.dev{ font-variant-numeric: tabular-nums; border-bottom: 1px solid rgba(0,0,0,0.06); }
.dev-red   { color: var(--c-red);    background: rgba(var(--c-red-rgb), .10); }
.dev-orange{ color: var(--c-orange); background: rgba(var(--c-orange-rgb), .10); }
.dev-yellow{ color: var(--c-yellow); background: rgba(var(--c-yellow-rgb), .10); }
.dev-green { color: var(--c-green);  background: rgba(var(--c-green-rgb), .10); }

/* Ensure transparency against theme defaults */
:deep(table), :deep(thead th), :deep(tbody td){ background-clip: padding-box; }
</style>
