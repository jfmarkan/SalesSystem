<!-- src/components/analytics/PcTotalsBars.vue -->
<template>
  <div class="pc-bars">
    <div class="head">
      <div class="left">
        <h4 class="m-0">Profitcenter · {{ fyLabel }} <span class="unit">({{ unitLabel }})</span></h4>
        <span class="muted small" v-if="!nodeId">Seleccioná vendedor</span>
      </div>
      <div class="right">
        <Button class="p-button-text p-button-sm" icon="pi pi-refresh" @click="reload" />
      </div>
    </div>

    <div v-if="loading" class="empty">Lade…</div>
    <div v-else-if="error" class="err">{{ error }}</div>
    <div v-else-if="rows.length === 0" class="empty">Keine Profitcenter.</div>

    <ul v-else class="list">
      <li v-for="r in sortedRows" :key="r.code" class="row">
        <div class="title">
          <strong class="name">{{ pcName(r.code) }}</strong>
          <span class="code">· {{ r.code }}</span>
          <span class="pill pill-eq" v-if="r.eq > 0">EQ: {{ fmt(r.eq) }} {{ unitLabel }}</span>
          <span class="pill pill-ist">Ist: {{ fmt(r.sales) }} {{ unitLabel }}</span>
          <span class="pill pill-fc">Fc: {{ fmt(r.fc) }} {{ unitLabel }}</span>
          <span class="pill pill-bud">Bud: {{ fmt(r.bud) }} {{ unitLabel }}</span>
        </div>

        <div class="bar-wrap" :title="tooltip(r)">
          <!-- Fondo total (para contraste) -->
          <div class="bar bg"></div>
          <!-- Budget visible -->
          <div class="bar bar-bud" :style="{ width: pct(r.bud, maxVal) }"></div>
          <!-- Forecast encima -->
          <div class="bar bar-fc" :style="{ width: pct(r.fc, maxVal) }"></div>
          <!-- Sales on top -->
          <div class="bar bar-sales" :style="{ width: pct(r.sales, maxVal) }"></div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Button from 'primevue/button'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const props = defineProps({
  userId: { type: [Number, String], required: true },
  teamId: { type: [Number, String], required: true },
  fiscalYear: {
    type: Number,
    default: () => {
      const d = new Date()
      return d.getMonth() >= 3 ? d.getFullYear() : d.getFullYear() - 1
    },
  },
  unit: { type: String, default: 'M3' }, // 'M3' | 'EUR' (la API devuelve también 'units', pero aquí priorizamos m3/eur)
})

/* state */
const loading = ref(false)
const error = ref('')
const rows = ref([]) // [{code, sales, fc, bud, eq}]
const pcNames = ref({})

/* labels */
const fyLabel = computed(() => `WJ ${props.fiscalYear}/${String(props.fiscalYear + 1).slice(-2)}`)
const unitLabel = computed(() => (String(props.unit || '').toUpperCase() === 'EUR' ? '€' : 'm³'))

/* node */
const nodeId = computed(() => {
  const u = Number(props.userId || 0)
  const t = Number(props.teamId || 0)
  return u && t ? `user_${u}_t${t}` : ''
})

/* helpers */
function toNumDE(x) {
  if (x == null) return 0
  if (typeof x === 'number') return x
  const s = String(x).replace(/\./g, '').replace(',', '.')
  const v = parseFloat(s)
  return Number.isFinite(v) ? v : 0
}
function fmt(n) {
  return Number(n || 0).toLocaleString('de-DE', { maximumFractionDigits: 0 })
}
function pct(v, max) {
  const p = Math.max(0, Math.min(100, (Number(v) / Math.max(1, Number(max))) * 100))
  return p.toFixed(2) + '%'
}
const collator = computed(() => new Intl.Collator('de', { numeric: true, sensitivity: 'base' }))

/* nombres PC */
async function loadPcNames() {
  try {
    await ensureCsrf()
    const { data } = await api.get('/api/company/pc-list')
    const map = {}
    for (const r of Array.isArray(data) ? data : []) {
      map[String(r.code)] = String(r.name || r.code)
    }
    pcNames.value = map
  } catch {
    pcNames.value = {}
  }
}
function pcName(code) { return pcNames.value[code] || code }

/* fetch totals */
async function loadTotals() {
  rows.value = []; error.value = ''
  if (!nodeId.value) return
  loading.value = true
  try {
    await ensureCsrf()
    const { data } = await api.get('/api/company/totals', {
      params: { node_id: nodeId.value, fiscal_year: props.fiscalYear },
    })

    // Elegir campo por unidad
    const key = String(props.unit || 'M3').toUpperCase() === 'EUR' ? 'euro' : 'm3'

    const salesByPc = (data?.sales?.by_pc ?? []).map(r => ({ code:String(r.profit_center_code), v: toNumDE(r[key]) }))
    const fcByPc    = (data?.forecasts?.by_pc ?? []).map(r => ({ code:String(r.profit_center_code), v: toNumDE(r[key]) }))
    const budByPc   = (data?.budgets?.by_pc ?? []).map(r => ({ code:String(r.profit_center_code), v: toNumDE(r[key]) }))
    const eqByPc    = (data?.extra_quotas?.by_pc ?? []).map(r => ({ code:String(r.profit_center_code), v: toNumDE(r[key]) }))

    const map = new Map()
    const add = (arr, field) => {
      for (const r of arr) {
        const o = map.get(r.code) || { code: r.code, sales:0, fc:0, bud:0, eq:0 }
        o[field] = Number(r.v || 0)
        map.set(r.code, o)
      }
    }
    add(salesByPc,'sales')
    add(fcByPc,   'fc')
    add(budByPc,  'bud')
    add(eqByPc,   'eq')

    rows.value = Array.from(map.values())
  } catch (e) {
    error.value = e?.response?.data?.message || 'Fehler beim Laden.'
  } finally {
    loading.value = false
  }
}

/* computed */
const maxVal = computed(() => {
  const vals = rows.value.flatMap(r => [r.sales, r.fc, r.bud])
  const m = Math.max(1, ...vals.map(Number))
  return m <= 0 ? 1 : m
})
const sortedRows = computed(() => {
  const a = rows.value.slice()
  a.sort((x, y) => {
    const nx = pcName(x.code), ny = pcName(y.code)
    const c = collator.value.compare(nx, ny)
    return c !== 0 ? c : String(x.code).localeCompare(String(y.code), 'de', { numeric:true, sensitivity:'base' })
  })
  return a
})
function tooltip(r){
  return [
    `Ist: ${fmt(r.sales)} ${unitLabel.value}`,
    `Forecast: ${fmt(r.fc)} ${unitLabel.value}`,
    `Budget: ${fmt(r.bud)} ${unitLabel.value}`,
    r.eq > 0 ? `EQ: ${fmt(r.eq)} ${unitLabel.value}` : null
  ].filter(Boolean).join(' · ')
}

/* reload */
async function reload(){ await Promise.all([loadPcNames(), loadTotals()]) }

/* wires */
onMounted(reload)
watch(() => [props.userId, props.teamId, props.fiscalYear, props.unit], reload)
</script>

<style scoped>
.pc-bars{display:flex;flex-direction:column;gap:8px}
.head{display:flex;align-items:center;justify-content:space-between}
.muted{opacity:.7}.small{font-size:.9rem}.unit{opacity:.7;font-weight:600}
.empty{text-align:center;opacity:.8;padding:8px}
.err{color:#ef4444;text-align:center;opacity:.9;padding:8px}

.list{display:flex;flex-direction:column;gap:8px}
.row{display:flex;flex-direction:column;gap:6px;padding:8px 6px;border-radius:10px}
.row:hover{background:rgba(0,0,0,.05)}
@media (prefers-color-scheme:dark){.row:hover{background:rgba(255,255,255,.06)}}

.title{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.name{font-weight:800}
.code{opacity:.7;font-weight:600}

.pill{padding:2px 8px;border-radius:999px;font-size:.8rem;font-weight:700}
.pill-ist{background:#dbeafe;color:#1e3a8a}
.pill-fc{background:#fef3c7;color:#854d0e}
.pill-bud{background:#dcfce7;color:#14532d}
.pill-eq{background:#e8f5e9;color:#065f46;border:1px solid rgba(6,95,70,.25)}

.bar-wrap{position:relative;height:14px;background:transparent;border-radius:999px;overflow:hidden;border:1px solid rgba(0,0,0,.08)}
@media (prefers-color-scheme:dark){.bar-wrap{border-color:rgba(255,255,255,.15)}}

.bar{position:absolute;left:0;top:0;bottom:0;border-radius:999px}
.bg{background:rgba(2,6,23,.06)}
.bar-bud{background:#10b981;opacity:.75}
.bar-fc{background:#f59e0b;opacity:.9}
.bar-sales{background:#3b82f6;opacity:1}
</style>
