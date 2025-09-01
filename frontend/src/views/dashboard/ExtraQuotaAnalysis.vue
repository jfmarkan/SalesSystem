<!-- src/pages/extra-quota/ExtraQuotasAnalysis.vue -->
<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import api from '@/plugins/axios'

const router = useRouter()

const fy = ref(new Date().getFullYear())
const loading = ref(false)
const errorMsg = ref('')
const items = ref([])
const totals = ref({})

function fmtInt(n){ return Number(n||0).toLocaleString('de-DE') }
function pct(num, den){ const d = den>0 ? den : 1; return Math.max(0, Math.min(100, Math.round((num||0)*100/d))) }
function tone(p){ if(p>=66) return 'good'; if(p>=33) return 'mid'; return 'low' }

async function load(){
  loading.value = true
  errorMsg.value = ''
  try{
    const { data } = await api.get('/api/extra-quota/analysis/summary', { params:{ fiscal_year: fy.value } })
    const rows = Array.isArray(data?.items) ? data.items : []
    items.value = rows.map(r => {
      const assigned  = Number(r.assigned_m3||0)
      const converted = Number(r.converted_m3||0)
      // usa abierto ponderado por prob si viene; fallback a in_progress_m3
      const openProb  = Number(
        r.prob_weighted_open_m3 ??
        r.in_progress_weighted_m3 ??
        r.in_progress_m3_weighted ??
        r.in_progress_m3 ??
        0
      )
      const inWork    = Math.max(0, openProb)
      // disponible recalculado = asignado - (ganado + abierto ponderado)
      const avail     = Math.max(0, assigned - converted - inWork)
      // progreso = ganado + abierto ponderado sobre asignado
      const progress  = pct(converted + inWork, assigned)

      return {
        name: r.profit_center_name || String(r.profit_center_code),
        assigned_m3: assigned,
        converted_m3: converted,
        in_progress_m3: inWork,     // mostrado como "in Arbeit" (ponderado)
        available_m3: avail,        // recalculado
        count_open: Number(r.count_open||0),
        count_won: Number(r.count_won||0),
        count_lost: Number(r.count_lost||0),
        progress_pct: progress,
        tone: tone(progress)
      }
    })
    // totales sin tocar; si quieres ponderación también aquí, pídelo y lo aplico
    totals.value = data?.totals || {}
  } catch (e){
    errorMsg.value = e?.response?.data?.message || 'Fehler beim Laden.'
  } finally {
    loading.value = false
  }
}
onMounted(load)

function goBack(e){
  e?.preventDefault?.()
  try { router.back() } catch { router.push('/') }
}
</script>

<template>
  <div class="ana">
    <!-- HEADER (una sola tarjeta) -->
    <div class="glass-card header-card">
      <div class="row-head">
        <div class="left-head">
          <a href="#" class="back-link" @click="goBack">&lt;- Zurück</a>
          <span class="vbar" aria-hidden="true"></span>
          <ul class="crumb">
            <li><span class="c1">Anwendung</span></li>
            <li class="sep"><i class="pi pi-angle-right" /></li>
            <li><span class="c2">Zusatzquoten Analyse</span></li>
          </ul>
        </div>
        <div class="ctrls">
          <span class="lbl">Wirtschaftsjahr</span>
          <InputText v-model.number="fy" style="width:7rem" />
          <Button label="Aktualisieren" icon="pi pi-refresh" @click="load" />
        </div>
      </div>

      <div class="totals">
        <div class="tbox"><span>Zugewiesen</span><b>{{ fmtInt(totals.assigned_m3||0) }} m³</b></div>
        <div class="tbox"><span>Überführt</span><b>{{ fmtInt(totals.converted_m3||0) }} m³</b></div>
        <div class="tbox"><span>In Arbeit</span><b>{{ fmtInt(totals.in_progress_m3||0) }} m³</b></div>
        <div class="tbox"><span>Verfügbar</span><b>{{ fmtInt(totals.available_m3||0) }} m³</b></div>
        <div class="tbox"><span>Chancen</span><b>{{ fmtInt(totals.count_total||0) }}</b></div>
      </div>
    </div>

    <!-- LISTA DE PROFITCENTER -->
    <div class="list">
      <div v-if="loading" class="loading">Lädt…</div>
      <template v-else>
        <div v-for="(r,i) in items" :key="i" class="glass-card pc-line">
          <div class="col-left">
            <div class="pc-name" :title="r.name">{{ r.name }}</div>

            <div class="bar-row">
              <div class="bar">
                <div class="fill" :class="r.tone" :style="{ width: r.progress_pct + '%' }"></div>
              </div>
              <div class="pct">{{ r.progress_pct }}%</div>
            </div>

            <div class="quotes-row">
              <div class="q">
                <i class="pi pi-database ico-quote"></i>
                <span>Quote gesamt</span><b>{{ fmtInt(r.assigned_m3) }} m³</b>
              </div>
              <div class="q">
                <i class="pi pi-cog ico-work"></i>
                <span>Quote in Arbeit</span><b>{{ fmtInt(r.in_progress_m3) }} m³</b>
              </div>
              <div class="q">
                <i class="pi pi-box ico-free"></i>
                <span>Quote verfügbar</span><b>{{ fmtInt(r.available_m3) }} m³</b>
              </div>
            </div>
          </div>

          <div class="col-right">
            <div class="s">
              <i class="pi pi-folder-open ico-open"></i>
              <span>offen</span><b>{{ fmtInt(r.count_open) }}</b>
            </div>
            <div class="s">
              <i class="pi pi-check-circle ico-won"></i>
              <span>gewonnen</span><b>{{ fmtInt(r.count_won) }}</b>
            </div>
            <div class="s">
              <i class="pi pi-times-circle ico-lost"></i>
              <span>verloren</span><b>{{ fmtInt(r.count_lost) }}</b>
            </div>
          </div>
        </div>

        <div v-if="!items.length" class="empty">Keine Daten</div>
      </template>

      <div v-if="errorMsg" class="err">{{ errorMsg }}</div>
    </div>
  </div>
</template>

<style scoped>
.ana{ padding:16px; display:flex; flex-direction:column; gap:12px; }

/* Card base */
.glass-card{
  background: rgba(255,255,255,0.4);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(0,0,0,.08);
  border-radius: 12px;
  padding: 12px;
}
@media (prefers-color-scheme: dark){
  .glass-card{ background: rgba(0,0,0,0.4); border-color: rgba(255,255,255,.16); }
}

/* Header line */
.header-card{ display:flex; flex-direction:column; gap:10px; }
.row-head{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
.left-head{ display:flex; align-items:center; gap:8px; min-width:0; }
.back-link{ color:#10b981; font-weight:300; text-decoration:none; letter-spacing:.1px; }
.back-link:hover{ text-decoration:underline; }
.vbar{ width:2px; height:16px; background:#fff; opacity:.9; border-radius:1px; }
@media (prefers-color-scheme: light){ .vbar{ background:#0f172a; opacity:.85; } }

.crumb{ list-style:none; padding:0; margin:0; display:flex; align-items:center; gap:8px; font-weight:700; }
.c1{ color:#64748b; } .c2{ color:#0f172a; } .sep{ color:#64748b; }
@media (prefers-color-scheme: dark){ .c1{ color:#94a3b8; } .c2{ color:#e5e7eb; } .sep{ color:#94a3b8; } }

.ctrls{ display:flex; gap:8px; align-items:center; }
.lbl{ color:#64748b; }
@media (prefers-color-scheme: dark){ .lbl{ color:#cbd5e1; } }

/* Totals */
.totals{ display:grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap:10px; }
.tbox{
  background: rgba(255,255,255,.35);
  border:1px solid rgba(0,0,0,.08);
  border-radius:10px;
  padding:10px;
  display:flex; justify-content:space-between; align-items:center;
}
@media (prefers-color-scheme: dark){
  .tbox{ background: rgba(0,0,0,.35); border-color: rgba(255,255,255,.16); }
}
.tbox span{ color:#475569; font-size:12px; }
.tbox b{ color:#0f172a; }
@media (prefers-color-scheme: dark){ .tbox span{ color:#cbd5e1; } .tbox b{ color:#e5e7eb; } }

/* List */
.list{ display:flex; flex-direction:column; gap:10px; }

/* PC line */
.pc-line{
  display:grid;
  grid-template-columns: 1fr 220px;
  gap:12px;
  align-items:center;
  padding:10px 20px; /* padding X */
}

/* Left column */
.col-left{ display:flex; flex-direction:column; gap:6px; min-width:0; }
.pc-name{ font-weight:800; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
@media (prefers-color-scheme: dark){ .pc-name{ color:#e5e7eb; } }

/* Progress bar */
.bar-row{ display:grid; grid-template-columns: 1fr 54px; gap:8px; align-items:center; }
.bar{ height:10px; background:rgba(0,0,0,.08); border-radius:999px; overflow:hidden; }
@media (prefers-color-scheme: dark){ .bar{ background:rgba(255,255,255,.12); } }
.fill{ height:100%; background:#22c55e; }
.fill.mid{ background:#f59e0b; }
.fill.low{ background:#ef4444; }
.pct{ text-align:right; font-weight:700; color:#0f172a; }
@media (prefers-color-scheme: dark){ .pct{ color:#e5e7eb; } }

/* Quotes row */
.quotes-row{ display:flex; flex-wrap:wrap; gap:10px 16px; align-items:center; }
.q{ display:flex; align-items:center; gap:6px; font-size:12px; color:#475569; }
.q b{ color:#0f172a; }
@media (prefers-color-scheme: dark){ .q{ color:#cbd5e1; } .q b{ color:#e5e7eb; } }
.ico-quote{ color:#3b82f6; }
.ico-work{  color:#f59e0b; }
.ico-free{  color:#64748b; }

/* Right column: states */
.col-right{ display:flex; flex-direction:column; gap:6px; justify-self:end; }
.s{ display:flex; align-items:center; gap:8px; font-size:12px; color:#475569; }
.s b{ color:#0f172a; }
@media (prefers-color-scheme: dark){ .s{ color:#cbd5e1; } .s b{ color:#e5e7eb; } }
.ico-open{ color:#f59e0b; } .ico-won{ color:#10b981; } .ico-lost{ color:#ef4444; }

/* Misc */
.loading,.empty{ color:#94a3b8; text-align:center; padding:8px; }
.err{ color:#ef4444; text-align:center; padding:8px; }

/* Responsive */
@media (max-width: 960px){
  .pc-line{ grid-template-columns: 1fr; padding:10px 14px; }
  .col-right{ justify-self:start; flex-direction:row; flex-wrap:wrap; }
}
</style>