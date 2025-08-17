<script setup>
import { ref, computed, watch } from 'vue'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'

const props = defineProps({
  dev: { type: Object, required: true },
  saving: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false }
})
const emit = defineEmits(['save'])

const open = ref(false)
const comment = ref(props.dev.comment || '')
watch(() => props.dev.comment, v => { if (!props.readonly) comment.value = v || '' })

const DE_ABBR = ['Jän','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez']
function ym(y,m){ return `${y}-${String(m).padStart(2,'0')}` }
function formatMonthDE(key){
  const m = String(key).match(/^(\d{4})-(\d{2})/)
  if (!m) return key
  const y = m[1], mm = Math.max(1, Math.min(12, parseInt(m[2],10)))
  return `${DE_ABBR[mm-1]} ${y.slice(2)}`
}
function fmtNumber(x){ return (Number(x)||0).toLocaleString('de-DE') }
function fmtPct(p){ return (p==null||isNaN(p)) ? '—' : `${Math.round(Number(p))}%` }
function typeLabel(t){ return String(t).toLowerCase()==='forecast' ? 'Prognose-Abweichung' : 'Vertriebsabweichung' }

const monthKey = computed(() => ym(props.dev.year, props.dev.month))
const bandClass = computed(() => String(props.dev.type).toLowerCase()==='forecast' ? 'forecast' : 'sales')
const severity = computed(() => {
  const d = Math.abs(Number(props.dev?.deltaPct) || 0)
  if (d > 10) return 'danger'
  if (d > 5)  return 'warning'
  return 'info'
})
const itemClass = computed(() => ({
  item:true, glass:true, // fuerza glass en cada render
  justified: !!props.dev.justified,
  unjustified: !props.dev.justified
}))

function doSave(){
  if (props.readonly) return
  emit('save', { id: props.dev.id, comment: comment.value })
}
</script>

<template>
  <div :class="itemClass">
    <div class="header" @click="open = !open">
      <span class="band" :class="bandClass"></span>
      <div class="head-main">
        <div class="title">{{ typeLabel(dev.type) }} • {{ dev.clientName }} • {{ dev.pcCode }} — {{ dev.pcName }}</div>
        <div class="meta">
          {{ formatMonthDE(monthKey) }}
          <span class="sep">•</span>
          Delta: <strong>{{ fmtNumber(dev.deltaAbs) }}</strong>
          <span class="sep">•</span>
          {{ fmtPct(dev.deltaPct) }}
        </div>
      </div>
      <Tag :value="fmtPct(dev.deltaPct)" :severity="severity" rounded />
    </div>

    <transition name="fade">
      <div v-if="open" class="body">
        <div class="kv">
          <div><span class="k">Kunde</span><span class="v">{{ dev.clientName }}</span></div>
          <div><span class="k">Profit Center</span><span class="v">{{ dev.pcCode }} — {{ dev.pcName }}</span></div>
          <div><span class="k">Monat</span><span class="v">{{ formatMonthDE(monthKey) }}</span></div>
          <div><span class="k">Typ</span><span class="v">{{ typeLabel(dev.type) }}</span></div>
        </div>

        <div class="numbers">
          <div class="n"><span class="k">Verkauf</span><span class="v">{{ fmtNumber(dev.sales) }}</span></div>
          <div class="n"><span class="k">Budget</span><span class="v">{{ fmtNumber(dev.budget) }}</span></div>
          <div class="n"><span class="k">Prognose</span><span class="v">{{ fmtNumber(dev.forecast) }}</span></div>
          <div class="n"><span class="k">Delta</span><span class="v">{{ fmtNumber(dev.deltaAbs) }} ({{ fmtPct(dev.deltaPct) }})</span></div>
        </div>

        <div v-if="!readonly" class="actions">
          <Textarea v-model="comment" autoResize rows="2" class="w-full" placeholder="Begründung eingeben…" />
          <div class="btns">
            <Button
              :label="saving ? 'Speichern…' : 'Begründung speichern'"
              icon="pi pi-save"
              class="p-button-sm"
              :loading="saving"
              @click.stop="doSave"
            />
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
/* Glass persistente */
.glass{
  background: var(--glass);
  backdrop-filter: blur(var(--blur));
  -webkit-backdrop-filter: blur(var(--blur));
  box-shadow: 0 2px 4px rgba(0,0,0,.12);
  border: 1px solid rgba(0,0,0,.06);
  will-change: backdrop-filter;
}

.item{
  position: relative;
  border-radius: 10px;
  overflow: hidden;
}

/* Estado */
.item.justified{ background: rgba(var(--c-green-rgb), .10); }
.item.unjustified{ background: rgba(var(--c-red-rgb), .06); }

.header{
  display:flex; align-items:center; gap:12px;
  padding: 10px 12px; cursor: pointer;
}
.band{
  width:6px; align-self:stretch; border-radius:6px;
  box-shadow: 0 0 0 1px rgba(0,0,0,.04) inset;
}
.band.sales{ background: var(--c-blue); }
.band.forecast{ background: var(--c-orange-strong); }

.head-main{ display:flex; flex-direction:column; gap:2px; }
.title{ font-weight:600; }
.meta{ font-size:.9rem; color:#475569; }
.sep{ margin: 0 6px; opacity:.5; }

.body{ padding: 12px; display:flex; flex-direction:column; gap:14px; }
.kv{ display:grid; grid-template-columns: repeat(4, minmax(160px, 1fr)); gap:8px 12px; }
.k{ font-size:.85rem; color:#64748B; display:block; }
.v{ font-weight:600; }

.numbers{ display:flex; flex-wrap:wrap; gap:12px; }
.n{ background: rgba(255,255,255,.6); border:1px solid rgba(0,0,0,.06); border-radius:8px; padding:8px 12px; }
.n .k{ font-size:.8rem; color:#64748B; }
.n .v{ font-weight:700; }

.actions{ display:flex; flex-direction:column; gap:10px; }
.btns{ display:flex; gap:10px; justify-content:flex-end; }

/* Transición */
.fade-enter-active,.fade-leave-active{ transition: opacity .15s ease; }
.fade-enter-from,.fade-leave-to{ opacity:0; }
</style>
