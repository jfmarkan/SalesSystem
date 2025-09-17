<!-- src/components/widgets/ExtraQuotaCard.vue -->
<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const props = defineProps({
  title: { type: String, default: 'Zusatzquoten' },
  unit: { type: String, default: 'M3' },        // etiqueta visual
  target: { type: Number, default: 0 },         // m³ asignados
  achieved: { type: Number, default: 0 },       // m³ consumidos (open+won si aplica)
  items: { type: Array, default: () => [] },
  // mix: [{ key,label, amount (m³), color?, displayAmount?, displayUnit? }, ...] o { label: amountM3 }
  mix: { type: [Array, Object], default: null },
  scope: { type: String, default: 'self' },
  currentUserId: { type: [String, Number], default: null },
  currentUserName: { type: String, default: '' },
  pcDetail: { type: Object, default: () => null }
})

const router = useRouter()

/* Utils */
const PALETTE = ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#f97316','#06b6d4','#22c55e','#eab308']
const pick = i => PALETTE[i % PALETTE.length]
const unitLabel = ()=>'m³'
function fmt(n) {
  const v = Number(n) || 0
  if (Math.abs(v) >= 1e6) {
    return new Intl.NumberFormat('de-DE', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(v / 1e6) + ' M'
  }
  if (Math.abs(v) >= 1e3) {
    return new Intl.NumberFormat('de-DE', {
      minimumFractionDigits: 1,
      maximumFractionDigits: 1
    }).format(v / 1e3) + ' k'
  }
  return new Intl.NumberFormat('de-DE', {
    maximumFractionDigits: 0
  }).format(v)
}

function fmtUnit(n, u) {
  const v = Number(n) || 0
  return `${new Intl.NumberFormat('de-DE', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(v)} ${u || ''}`.trim()
}

/* Normaliza segmentos para barra (m³) y listado (unidad original si viene) */
function normalizeMix(m){
  if (!m) return []
  const arr = Array.isArray(m) ? m : Object.entries(m).map(([label,amount]) => ({ label, amount }))
  return arr.map((s,i)=>({
    key: s.key ?? String(i),
    label: String(s.label ?? s.key ?? i),
    amountM3: Number(s.amount_m3 ?? s.amount ?? 0) || 0,      // usado para la barra
    color: s.color || pick(i),
    displayAmount: s.displayAmount ?? s.display_amount ?? null, // solo UI lista
    displayUnit: s.displayUnit ?? s.display_unit ?? null
  }))
}

/* Totales: porcentaje por ASIGNADO (alokiert). 0=rojo, 100=verde */
const totals = computed(()=>{
  const totalAssigned = Math.max(0, Number(props.target)||0)
  const rawUsed       = Math.max(0, Number(props.achieved)||0)
  const totalUsed     = Math.min(totalAssigned || Infinity, rawUsed)
  const totalAvail    = Math.max(0, totalAssigned - totalUsed)
  const pctAlloc      = totalAssigned>0 ? (totalUsed*100/totalAssigned) : 0
  return { totalAssigned, totalUsed, totalAvail, pctAlloc }
})
function toneAlloc(p){ if(p>=66) return 'ok'; if(p>=33) return 'mid'; return 'low' }

const baseSegs = computed(()=> normalizeMix(props.mix))
const listSegs = computed(()=>{
  const T = totals.value.totalAssigned || 0
  return baseSegs.value.map(s => ({ ...s, pctOfTarget: T>0 ? (s.amountM3 * 100 / T) : 0 }))
})

function formatPercentComma(value) {
  const num = Number(value) || 0
  return new Intl.NumberFormat('de-DE', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2
  }).format(num) + ' %'
}

/* Detalle por PC (opcional) */
const pc = computed(()=>{
  const d = props.pcDetail
  if(!d) return null
  const allocated = Math.max(0, Number(d.allocated)||0)
  const won  = Math.max(0, Math.min(allocated, Number(d.won)||0))
  const lost = Math.max(0, Math.min(Math.max(0, allocated-won), Number(d.lost)||0))
  const open = Math.max(0, Number(d.open)!=null ? Number(d.open) : Math.max(0, allocated - won - lost))
  const sum  = (won+lost+open)||1
  return {
    pcName: d.pcName ?? '',
    segs: [
      { key:'won',  label:'Gewonnen', val:won,  pct:won*100/sum,  color:'#10b981' },
      { key:'open', label:'Offen',    val:open, pct:open*100/sum, color:'#f59e0b' },
      { key:'lost', label:'Verloren', val:lost, pct:lost*100/sum, color:'#ef4444' }
    ],
    allocated, won, lost, open
  }
})

const expanded = ref(false)
watch(()=>props.pcDetail, v=>{ expanded.value = !!v })

function goAnalysis(e){
  e?.preventDefault?.()
  try { router.push({ name: 'ExtraQuotasAnalysis' }) }
  catch { router.push('/extra-quota/analyse') }
}
</script>

<template>
  <div class="xq-root">
    <!-- Header -->
    <div class="xq-title-row">
      <div class="xq-title">{{ title }}</div>
      <div class="xq-actions">
        <button v-if="pc" class="xq-toggle" @click.stop.prevent="expanded=!expanded">
          {{ expanded ? 'Details ausblenden' : 'Details anzeigen' }}
        </button>
      </div>
    </div>

    <!-- KPIs -->
    <div class="xq-row">
      <div class="xq-kpis">
        <div class="xq-value">
          {{ fmt(totals.totalUsed) }} <span class="xq-unit">{{ unitLabel() }}</span>
        </div>
        <div class="xq-sub">
          Zugewiesen: {{ fmt(totals.totalAssigned) }} <span>{{ unitLabel() }}</span>
          · Verfügbar: {{ fmt(totals.totalAvail) }} <span>{{ unitLabel() }}</span>
        </div>
      </div>
      <!-- Badge ahora usa pctAlloc con escala positiva -->
      <div class="xq-badge" :class="toneAlloc(totals.pctAlloc)"><span>{{ Math.round(totals.pctAlloc) }}%</span></div>
    </div>

    <!-- Barra apilada (en m³) usando objetivo total -->
    <div v-if="baseSegs.length" class="xq-stack" aria-label="Zusammensetzung">
      <div
        v-for="s in baseSegs"
        :key="s.key"
        class="xq-seg"
        :style="{ width: (totals.totalAssigned>0 ? (s.amountM3*100/totals.totalAssigned) : 0) + '%', background: s.color }"
        :title="`${s.label}: ${fmt(s.amountM3)} m³`"
      ></div>
      <div
        class="xq-marker"
        :style="{ left: (Math.min(100, (totals.totalUsed/Math.max(1, totals.totalAssigned))*100)) + '%' }"
        title="Alokation"
      ></div>
    </div>
    <div v-else class="xq-empty">Keine Zusammensetzung verfügbar.</div>

    <!-- Lista de segmentos -->
    <ul v-if="listSegs.length" class="seg-list">
      <li v-for="s in listSegs" :key="s.key" class="seg-li">
        <div class="seg-left">
          <i class="seg-dot" :style="{ background: s.color }" />
          <span class="name">{{ s.label }}</span>
          <span class="uval">– {{
            s.displayAmount != null
              ? fmtUnit(s.displayAmount, s.displayUnit)
              : fmtUnit(s.amountM3, 'm³')
          }}</span>
        </div>
        <div class="seg-pct">{{ formatPercentComma(s.pctOfTarget) }}</div>
      </li>
    </ul>

    <!-- Detalle PC -->
    <transition name="fade">
      <div v-if="expanded && pc" class="pc-detail">
        <div class="pc-title">{{ pc.pcName }}</div>
        <div class="pc-bar">
          <div v-for="s in pc.segs" :key="s.key" class="pc-seg" :style="{ width: s.pct+'%', background: s.color }"></div>
        </div>
        <div class="pc-legend">
          <span class="pitem"><i class="dot dot-win"></i> Gewonnen: {{ fmt(pc.won) }} {{ unitLabel() }}</span>
          <span class="pitem"><i class="dot dot-open"></i> Offen: {{ fmt(pc.open) }} {{ unitLabel() }}</span>
          <span class="pitem"><i class="dot dot-lost"></i> Verloren: {{ fmt(pc.lost) }} {{ unitLabel() }}</span>
          <span class="pitem sep"></span>
          <span class="pitem total">Zugewiesen: {{ fmt(pc.allocated) }} {{ unitLabel() }}</span>
        </div>
      </div>
    </transition>

    <!-- Botón PrimeVue verde abajo derecha -->
    <Button class="more-btn p-button-success p-button-sm" label="Mehr anzeigen" @click="goAnalysis" />
  </div>
</template>

<style scoped>
.xq-root{ position:relative; display:flex; flex-direction:column; height:100%; gap:.6rem; padding:10px 12px; }

/* Header */
.xq-title-row{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
.xq-title{ font-size:.9rem; font-weight:500; color:#334155; }
@media (prefers-color-scheme: dark){ .xq-title{ color:#e5e7eb; } }
.xq-actions{ display:flex; gap:.4rem; }
.xq-toggle{
  border:1px solid rgba(2,6,23,.15); background:transparent; color:inherit; border-radius:.5rem;
  padding:.25rem .5rem; font-size:.8rem; cursor:pointer;
}
@media (prefers-color-scheme: dark){ .xq-toggle{ border-color: rgba(255,255,255,.25); } }

/* KPIs */
.xq-row{ display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
.xq-kpis{ display:flex; flex-direction:column; gap:.15rem; }
.xq-value{ font-size:1.5rem; font-weight:800; color:#0f172a; }
@media (prefers-color-scheme: dark){ .xq-value{ color:#f8fafc; } }
.xq-unit{ font-size:.95rem; font-weight:600; opacity:.85; }
.xq-sub{ font-size:.85rem; color:#64748b; display:flex; gap:.5rem; flex-wrap:wrap; }
@media (prefers-color-scheme: dark){ .xq-sub{ color:#cbd5e1; } }

/* Badge 0->rojo, 100->verde según asignado */
.xq-badge{ min-width:3.25rem; height:2rem; padding:0 .5rem; border-radius:.75rem; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; background: linear-gradient(to bottom,#ef4444,#b91c1c); }
.xq-badge.mid{ background: linear-gradient(to bottom,#fb923c,#ea580c); }
.xq-badge.ok{  background: linear-gradient(to bottom,#34d399,#059669); }

/* Barra apilada */
.xq-stack{ position:relative; height:12px; border-radius:999px; overflow:hidden; display:flex; width:100%; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06); }
@media (prefers-color-scheme: dark){ .xq-stack{ box-shadow: inset 0 0 0 1px rgba(255,255,255,.16); } }
.xq-seg{ height:100%; }
.xq-marker{ position:absolute; top:-2px; bottom:-2px; width:2px; background:#111827; }
@media (prefers-color-scheme: dark){ .xq-marker{ background:#f8fafc; } }

/* Lista de segmentos */
.seg-list{ list-style:none; padding:0; margin:4px 0 0 0; display:flex; flex-direction:column; gap:6px; }
.seg-li{ display:flex; align-items:center; justify-content:space-between; }
.seg-left{ display:flex; align-items:center; gap:.45rem; min-width:0; }
.seg-dot{ width:.65rem; height:.65rem; border-radius:3px; display:inline-block; }
.name{ font-size:.85rem; color:#475569; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.uval{ font-size:.8rem; color:#64748b; }
@media (prefers-color-scheme: dark){ .name{ color:#e5e7eb; } .uval{ color:#cbd5e1; opacity:.9; } }
.seg-pct{ font-size:.8rem; font-weight:700; color:#0f172a; }
@media (prefers-color-scheme: dark){ .seg-pct{ color:#f8fafc; } }

/* Detalle PC */
.pc-detail{ display:flex; flex-direction:column; gap:.5rem; padding-top:.25rem; }
.pc-title{ font-size:.85rem; font-weight:600; }
.pc-bar{ height:10px; border-radius:999px; overflow:hidden; display:flex; width:100%; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06); }
.pc-seg{ height:100%; }
.pc-legend{ display:flex; flex-wrap:wrap; gap:.5rem .9rem; font-size:.8rem; align-items:center; }
.pitem{ display:flex; align-items:center; gap:.4rem; }
.pitem.total{ font-weight:700; }
.pitem.sep{ flex:0 0 8px; }
.dot-win{ background:#10b981; } .dot-open{ background:#f59e0b; } .dot-lost{ background:#ef4444; }

/* Botón PrimeVue “Mehr anzeigen” abajo derecha */
.more-btn{
  position:absolute; right:10px; bottom:10px;
}
</style>