<script setup>
/* Table with editable forecast cells + reactive deviation colors. */
import { computed } from 'vue'
import InputText from 'primevue/inputtext'

const props = defineProps({
  months:   { type: Array,  required: true },
  sales:    { type: Array,  default: () => [] },     // <-- use this in English
  budget:   { type: Array,  default: () => [] },
  forecast: { type: Array,  default: () => [] },

  // TEMP backwards-compat (do not use anymore)
  ventas:   { type: Array,  default: null },
})
const emit = defineEmits(['edit-forecast'])

/* Formats: "Jän 25", "Mär 26", ... */
function fmtMonthDE(ym) {
  if (!ym) return '—'
  const [yS, mS] = String(ym).split('-')
  const y = yS?.slice(2) ?? ''
  const m = parseInt(mS || '1', 10)
  const map = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
  return `${map[m - 1] || '—'} ${y}`
}

/* Current month index inside provided months (YYYY-MM) */
function yyyymm(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}
function thirdWednesday(d = new Date()) {
  const first = new Date(d.getFullYear(), d.getMonth(), 1)
  const firstWeekday = first.getDay() // 0..6 (0=Dom)
  const deltaToWed = (3 - firstWeekday + 7) % 7
  const firstWed = new Date(first)
  firstWed.setDate(1 + deltaToWed)
  const third = new Date(firstWed)
  third.setDate(firstWed.getDate() + 14)
  return third
}
function isEditableYM(ym) {
  const now = new Date()
  const cur = new Date(now.getFullYear(), now.getMonth(), 1)
  const [yS, mS] = String(ym).split('-')
  const y = +yS,
    m = +mS
  const target = new Date(y, m - 1, 1)

  // bloquea igual o anterior al mes en curso
  if (target <= cur) return false

  // mes siguiente: solo hasta 3er miércoles del mes actual
  const next = new Date(cur.getFullYear(), cur.getMonth() + 1, 1)
  if (target.getTime() === next.getTime()) {
    return now <= thirdWednesday(now)
  }

  // meses posteriores al siguiente: siempre editables
  return true
}

const curIdx = computed(() => {
  const key = yyyymm(new Date())
  return Array.isArray(props.months) ? props.months.findIndex((m) => m === key) : -1
})

/* Deviation helpers: pct vs budget => deviation from 100% */
function devPct(num, den) {
  if (!den) return 0
  return (num / den - 1) * 100
} // +/- deviation
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
  if (!den) return '0%'
  return Math.round((num / den) * 100) + '%'
}

const salesData = computed(() => {
  if (Array.isArray(props.sales) && props.sales.length) return props.sales
  if (Array.isArray(props.ventas)) return props.ventas // fallback (deprecated)
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
              class="p-2 text-center cell cell-sales"
              :class="{
                'cur-left': i === curIdx,
                'cur-right': i === curIdx,
              }"
            >
              {{ ventas[i] ?? 0 }}
            </td>
          </tr>

          <!-- Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Budget</td>
            <td
              v-for="(m, i) in months"
              :key="'b' + i"
              class="p-2 text-center cell cell-budget"
              :class="{
                'cur-left': i === curIdx,
                'cur-right': i === curIdx,
              }"
            >
              {{ budget[i] ?? 0 }}
            </td>
          </tr>

          <!-- Forecast (editable) -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">Forecast</td>
            <td
              v-for="(m, i) in months"
              :key="'f' + i"
              class="p-1 cell"
              :class="{
                'cur-left': i === curIdx,
                'cur-right': i === curIdx,
                'cur-bottom': i === curIdx,
              }"
            >
              <InputText
                class="w-full p-inputtext-sm text-center inp-forecast"
                :value="forecast[i]"
                :disabled="!isEditableYM(m)"
                @input="
                  (e) => {
                    if (isEditableYM(m)) emit('edit-forecast', { index: i, value: e.target.value })
                  }
                "
              />
            </td>
          </tr>

          <!-- % Ist / Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">% Ist / Bud.</td>
            <td
              v-for="(m, i) in months"
              :key="'ivb' + i"
              class="p-2 text-center cell dev-cell"
              :class="[
                clsSalesDev(ventas[i] ?? 0, budget[i] ?? 0),
                { 'cur-left': i === curIdx, 'cur-right': i === curIdx },
              ]"
            >
              {{ pctLabel(ventas[i] ?? 0, budget[i] ?? 0) }}
            </td>
          </tr>

          <!-- % Forecast / Budget -->
          <tr>
            <td class="p-2 sticky text-right left-0 z-2 stick-left">% For. / Bud.</td>
            <td
              v-for="(m, i) in months"
              :key="'ifb' + i"
              class="p-2 text-center cell dev-cell"
              :class="[
                clsFcstDev(forecast[i] ?? 0, budget[i] ?? 0),
                { 'cur-left': i === curIdx, 'cur-right': i === curIdx },
              ]"
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
/* Palette */
:root {
  --blue: #54849a;
  --green: #05a46f;
  --yellow: #e6b729;
  --orange: #e88d1e;
  --orangeDeep: #ea6312;
  --red: #b01513;
}

/* Layout */
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

/* Keep your restyling */
.stick-head {
  position: sticky;
  top: 0;
}
.stick-left {
  width: calc(100% / 13);
  left: 0;
}
.cell {
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

/* Current month red frame */
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

/* Row tints */
.cell-sales {
  background: rgba(31, 86, 115, 0.25);
}
.cell-budget {
  background: rgba(84, 132, 154, 0.25);
}

/* Forecast input look */
.inp-forecast {
  background: rgba(0, 0, 0, 0.65) !important;
  backdrop-filter: blur(6px);
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 6px;
}

/* Deviation colors */
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
