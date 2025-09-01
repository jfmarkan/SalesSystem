<!-- src/components/widgets/ExtraQuotaCard.vue -->
<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  title: { type: String, default: 'Zusatzquoten' },
  unit: { type: String, default: 'M3' },
  target: { type: Number, default: 0 },
  achieved: { type: Number, default: 0 },
  items: { type: Array, default: () => [] },
  mix: { type: [Array, Object], default: null },
  scope: { type: String, default: 'self' },
  currentUserId: { type: [String, Number], default: null },
  currentUserName: { type: String, default: '' },
  pcDetail: { type: Object, default: () => null }
})

const router = useRouter()

const PALETTE = ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#f97316','#06b6d4','#22c55e','#eab308']
const pick = i => PALETTE[i % PALETTE.length]
const unitLabel = ()=>'m³'
function fmt(n){ const v=Number(n)||0; if(Math.abs(v)>=1e6) return (v/1e6).toFixed(2)+'M'; if(Math.abs(v)>=1e3) return (v/1e3).toFixed(1)+'k'; return v.toLocaleString(undefined,{maximumFractionDigits:0}) }

function normalizeMix(m){
  const arr = Array.isArray(m) ? m : (m ? Object.entries(m).map(([k,v])=>({ key:k, label:k, amount:Number(v)||0 })) : [])
  const withColor = arr.map((s,i)=>({ key:s.key??String(i), label:String(s.label??s.key??i), amount:Number(s.amount)||0, color:s.color||pick(i) }))
  const total = withColor.reduce((a,s)=>a+s.amount,0) || 1
  return { segs: withColor.map(s=>({ ...s, pct: s.amount*100/total })), total }
}

const totals = computed(()=>{
  const totalAssigned = Math.max(0, Number(props.target)||0)
  const totalUsed     = Math.max(0, Number(props.achieved)||0)
  const totalAvail    = Math.max(0, totalAssigned - totalUsed)
  const pctAvail      = totalAssigned>0 ? (totalAvail*100/totalAssigned) : 0
  return { totalAssigned, totalUsed, totalAvail, pctAvail }
})
function level(p){
  if (p >= 80) return 'red'
  if (p >= 60) return 'orange-deep'
  if (p >= 35) return 'orange'
  if (p >= 10) return 'yellow'
  return 'green'
}

const base = computed(()=> normalizeMix(props.mix))

const pc = computed(()=>{
  const d = props.pcDetail
  if(!d) return null
  const allocated = Math.max(0, Number(d.allocated)||0)
  const won  = Math.max(0, Math.min(allocated, Number(d.won)||0))
  const lost = Math.max(0, Math.min(Math.max(0, allocated-won), Number(d.lost)||0))
  const open = Math.max(0, Number(d.open)!=null ? Number(d.open) : Math.max(0, allocated - won - lost))
  const sum  = (won+lost+open)||1
  const mix  = d.mix ? normalizeMix(d.mix) : { segs:[], total:0 }
  return {
    pcName: d.pcName ?? '',
    segs: [
      { key:'won',  label:'Gewonnen', val:won,  pct:won*100/sum,  color:'#10b981' },
      { key:'open', label:'Offen',    val:open, pct:open*100/sum, color:'#f59e0b' },
      { key:'lost', label:'Verloren', val:lost, pct:lost*100/sum, color:'#ef4444' }
    ],
    allocated, won, lost, open, mix
  }
})

const expanded = ref(false)
watch(()=>props.pcDetail, v=>{ expanded.value = !!v })

function goAnalysis(){
  // intenta por nombre; si falla, usa path
  try {
    router.push({ name: 'ExtraQuotasAnalysis' })
  } catch (_e) {
    router.push('/extra-quota/analyse')
  }
}
</script>

<template>
  <div class="xq-root">
    <div class="xq-title-row">
      <div class="xq-title">{{ title }}</div>
      <div class="xq-actions">
        <button class="xq-more" @click.stop.prevent="goAnalysis">Mehr</button>
        <button v-if="pc" class="xq-toggle" @click.stop.prevent="expanded=!expanded">
          {{ expanded ? 'Details ausblenden' : 'Details anzeigen' }}
        </button>
      </div>
    </div>

    <div class="xq-row">
      <div class="xq-kpis">
        <div class="xq-value">
          {{ fmt(totals.totalAvail) }} <span class="xq-unit">{{ unitLabel() }}</span>
        </div>
        <div class="xq-sub">
          Zugewiesen: {{ fmt(totals.totalAssigned) }} <span>{{ unitLabel() }}</span>
          · Verbraucht: {{ fmt(totals.totalUsed) }} <span>{{ unitLabel() }}</span>
        </div>
      </div>
      <div class="xq-badge" :class="level(totals.pctAvail)"><span>{{ Math.round(totals.pctAvail) }}%</span></div>
    </div>

    <div v-if="base.segs.length" class="xq-stack" aria-label="Zusammensetzung">
      <div v-for="s in base.segs" :key="s.key" class="xq-seg" :style="{ width: s.pct+'%', background: s.color }" :title="`${s.label}: ${fmt(s.amount)} m³ (${s.pct.toFixed(1)}%)`"></div>
      <div class="xq-marker" :style="{ left: (Math.min(100, (totals.totalUsed/Math.max(1, totals.totalAssigned))*100)) + '%' }" title="Verbrauch"></div>
    </div>
    <div v-else class="xq-empty">Keine Zusammensetzung verfügbar.</div>

    <div v-if="base.segs.length" class="xq-legend">
      <div v-for="s in base.segs" :key="s.key" class="xq-leg">
        <span class="dot" :style="{ background: s.color }"></span>
        <span class="lbl">{{ s.label }}</span>
        <span class="val">{{ fmt(s.amount) }} {{ unitLabel() }}</span>
      </div>
    </div>

    <transition name="fade">
      <div v-if="expanded && pc" class="pc-detail">
        <div class="pc-title">{{ pc.pcName }}</div>
        <div class="pc-bar"><div v-for="s in pc.segs" :key="s.key" class="pc-seg" :style="{ width: s.pct+'%', background: s.color }"></div></div>
        <div class="pc-legend">
          <span class="pitem"><i class="dot dot-win"></i> Gewonnen: {{ fmt(pc.won) }} {{ unitLabel() }}</span>
          <span class="pitem"><i class="dot dot-open"></i> Offen: {{ fmt(pc.open) }} {{ unitLabel() }}</span>
          <span class="pitem"><i class="dot dot-lost"></i> Verloren: {{ fmt(pc.lost) }} {{ unitLabel() }}</span>
          <span class="pitem sep"></span>
          <span class="pitem total">Zugewiesen: {{ fmt(pc.allocated) }} {{ unitLabel() }}</span>
        </div>
        <div v-if="pc.mix.segs.length" class="xq-stack pc-stack" aria-label="PC-Zusammensetzung">
          <div v-for="s in pc.mix.segs" :key="s.key" class="xq-seg" :style="{ width: s.pct+'%', background: s.color }" :title="`${s.label}: ${fmt(s.amount)} m³ (${s.pct.toFixed(1)}%)`"></div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.xq-root{ display:flex; flex-direction:column; height:100%; gap:.6rem; padding:10px 12px; }
.xq-title-row{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
.xq-title{ font-size:.9rem; font-weight:500; color:#334155; }
@media (prefers-color-scheme: dark){ .xq-title{ color:#e5e7eb; } }
.xq-actions{ display:flex; gap:.4rem; }
.xq-more, .xq-toggle{
  border:1px solid rgba(2,6,23,.15); background:transparent; color:inherit; border-radius:.5rem;
  padding:.25rem .5rem; font-size:.8rem; cursor:pointer;
}
@media (prefers-color-scheme: dark){ .xq-more, .xq-toggle{ border-color: rgba(255,255,255,.25); } }

.xq-row{ display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
.xq-kpis{ display:flex; flex-direction:column; gap:.15rem; }
.xq-value{ font-size:1.5rem; font-weight:800; color:#0f172a; }
@media (prefers-color-scheme: dark){ .xq-value{ color:#f8fafc; } }
.xq-unit{ font-size:.95rem; font-weight:600; opacity:.85; }
.xq-sub{ font-size:.85rem; color:#64748b; display:flex; gap:.5rem; flex-wrap:wrap; }
@media (prefers-color-scheme: dark){ .xq-sub{ color:#cbd5e1; } }

.xq-badge{ min-width:3.25rem; height:2rem; padding:0 .5rem; border-radius:.75rem; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; background: linear-gradient(to bottom,#94a3b8,#475569); }
.xq-badge.green{ background: linear-gradient(to bottom,#34d399,#059669); color:#f0fdf4; }
.xq-badge.yellow{ background: linear-gradient(to bottom,#fde047,#f59e0b); color:#111827; }
.xq-badge.orange{ background: linear-gradient(to bottom,#f59e0b,#ea580c); color:#111827; }
.xq-badge.orange-deep{ background: linear-gradient(to bottom,#ea580c,#c2410c); }
.xq-badge.red{ background: linear-gradient(to bottom,#ef4444,#b91c1c); }

.xq-stack{ position:relative; height:12px; border-radius:999px; overflow:hidden; display:flex; width:100%; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06); }
@media (prefers-color-scheme: dark){ .xq-stack{ box-shadow: inset 0 0 0 1px rgba(255,255,255,.16); } }
.xq-seg{ height:100%; }
.xq-marker{ position:absolute; top:-2px; bottom:-2px; width:2px; background:#111827; }
@media (prefers-color-scheme: dark){ .xq-marker{ background:#f8fafc; } }

.xq-legend{ display:flex; flex-wrap:wrap; gap:.5rem 1rem; margin-top:.25rem; }
.xq-leg{ display:flex; align-items:center; gap:.4rem; font-size:.85rem; color:#475569; }
@media (prefers-color-scheme: dark){ .xq-leg{ color:#e5e7eb; } }
.dot{ width:.8rem; height:.8rem; border-radius:3px; display:inline-block; }

.pc-detail{ display:flex; flex-direction:column; gap:.5rem; padding-top:.25rem; }
.pc-title{ font-size:.85rem; font-weight:600; }
.pc-bar{ height:10px; border-radius:999px; overflow:hidden; display:flex; width:100%; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06); }
.pc-seg{ height:100%; }
.pc-legend{ display:flex; flex-wrap:wrap; gap:.5rem .9rem; font-size:.8rem; align-items:center; }
.pitem{ display:flex; align-items:center; gap:.4rem; }
.pitem.total{ font-weight:700; }
.pitem.sep{ flex:0 0 8px; }
.dot-win{ background:#10b981; } .dot-open{ background:#f59e0b; } .dot-lost{ background:#ef4444; }
.pc-stack{ margin-top:.25rem; }

.xq-empty{ font-size:.85rem; opacity:.8; color:#475569; }
@media (prefers-color-scheme: dark){ .xq-empty{ color:#cbd5e1; } }

.fade-enter-active,.fade-leave-active{ transition:opacity .15s ease } .fade-enter-from,.fade-leave-to{ opacity:0 }
</style>