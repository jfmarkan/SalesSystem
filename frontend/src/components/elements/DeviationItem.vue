<script setup>
import { ref, computed, watch } from 'vue'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import Calendar from 'primevue/calendar'
import MiniDeviationChart from '../charts/MiniDeviationChart.vue'

const props = defineProps({
  dev: { type: Object, required: true },
  saving: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false }
})
const emit = defineEmits(['save'])

const open = ref(false)
const comment = ref(props.dev.comment || '')

/* Plan y acciones solo para forecast<budget */
const plan = ref('')
const actions = ref([]) // [{title,desc,due}]

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
function typeLabel(t){ return String(t).toLowerCase()==='forecast' ? 'Prognose' : 'Vertrieb' }

const monthKey = computed(() => ym(props.dev.year, props.dev.month))
const bandClass = computed(() => String(props.dev.type).toLowerCase()==='forecast' ? 'forecast' : 'sales')

const pillClass = computed(() => {
  const d = Number(props.dev?.deltaPct) || 0
  const ad = Math.abs(d)
  if (ad > 10) return 'pill-red'
  if (ad > 5)  return 'pill-orange'
  if (ad > 2)  return 'pill-yellow'
  return 'pill-green'
})

/* Regla para exigir plan: solo forecast y forecast < budget */
const needsPlan = computed(() => {
  if (String(props.dev.type).toLowerCase() !== 'forecast') return false
  const f = Number(props.dev.forecast || 0)
  const b = Number(props.dev.budget || 0)
  if (b <= 0) return false
  return f < b // bajo 100%
})

function addAction(){
  actions.value.push({ title:'', desc:'', due:null })
}
function removeAction(idx){
  actions.value.splice(idx,1)
}

function doSave(){
  if (props.readonly) return
  // payload completo para back
  emit('save', {
    id: props.dev.id,
    type: props.dev.type,
    comment: comment.value,
    plan: needsPlan.value ? plan.value : null,
    actions: needsPlan.value ? actions.value.map(a => ({
      title: a.title?.trim() || '',
      desc: a.desc?.trim() || '',
      due: a.due ? new Date(a.due).toISOString().slice(0,10) : null
    })) : []
  })
}
</script>

<template>
    <div :class="['item', { open }]">
        <div class="header" @click="open = !open">
            <span class="band" :class="bandClass"></span>
            <div class="head-main">
                <div class="top-row">
                    <span class="type">{{ typeLabel(dev.type) }}-Abweichung</span>
                    <span class="dot">•</span>
                    <span class="client">{{ dev.clientName }}</span>
                    <span class="dot">•</span>
                    <span class="pc">{{ dev.pcCode }} — {{ dev.pcName }}</span>
                </div>
                <div class="meta">
                    {{ formatMonthDE(monthKey) }}
                    <span class="sep">•</span>
                    Delta: <strong>{{ fmtNumber(dev.deltaAbs) }}</strong>
                </div>
            </div>
                <span class="pill" :class="pillClass">{{ fmtPct(dev.deltaPct) }}</span>
            </div>
            <transition name="fade">
            <div v-if="open" class="body">
                <div class="grid">
                    <div class="box">
                        <div class="k">Verkauf</div>
                        <div class="v">{{ fmtNumber(dev.sales) }}</div>
                    </div>
                    <div class="box">
                        <div class="k">Budget</div>
                        <div class="v">{{ fmtNumber(dev.budget) }}</div>
                    </div>
                    <div class="box">
                        <div class="k">Prognose</div>
                        <div class="v">{{ fmtNumber(dev.forecast) }}</div>
                    </div>
                    <div class="box">
                        <div class="k">Delta</div>
                        <div class="v">{{ fmtNumber(dev.deltaAbs) }} ({{ fmtPct(dev.deltaPct) }})</div>
                    </div>
                </div>
                <MiniDeviationChart
                :months="dev.months"
                :sales="dev.salesSeries ?? dev.sales"
                :budget="dev.budgetSeries ?? dev.budget"
                :forecast="dev.forecastSeries ?? dev.forecast"/>
                    <div class="form">
                        <label class="lbl">Begründung</label>
                        <Textarea v-model="comment" autoResize rows="2" class="w-full" placeholder="Begründung eingeben…" :disabled="readonly" />
                    </div>

        <template v-if="String(dev.type).toLowerCase()==='forecast'">
          <div v-if="!needsPlan" class="note ok">Kein Aktionsplan erforderlich. Prognose ≥ Budget.</div>

          <div v-else class="plan">
            <label class="lbl">Aktionsplan</label>
            <Textarea v-model="plan" autoResize rows="2" class="w-full" placeholder="Plan kurz beschreiben…" :disabled="readonly" />

            <div class="actions-head">
              <div class="title">Aktionen</div>
              <Button v-if="!readonly" label="Aktion hinzufügen" icon="pi pi-plus" class="p-button-sm" @click="addAction" />
            </div>

            <div class="actions-list">
              <div v-for="(a,idx) in actions" :key="idx" class="action-row">
                <InputText v-model="a.title" class="w-12 md:w-3" placeholder="Titel" :disabled="readonly" />
                <InputText v-model="a.desc" class="w-12 md:w-6" placeholder="Beschreibung" :disabled="readonly" />
                <Calendar v-model="a.due" dateFormat="yy-mm-dd" class="w-12 md:w-2" :manualInput="true" :showIcon="true" :disabled="readonly" />
                <Button v-if="!readonly" icon="pi pi-trash" class="p-button-text p-button-danger" @click="removeAction(idx)" />
              </div>
            </div>
          </div>
        </template>

        <div v-if="!readonly" class="btns">
          <Button :label="saving ? 'Speichern…' : 'Begründung speichern'" icon="pi pi-save" class="p-button-sm" :loading="saving" @click.stop="doSave" />
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.item{
  position: relative; border-radius: 12px;
  background: rgba(0,0,0,.45);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,.08);
  box-shadow: 0 2px 6px rgba(0,0,0,.35);
  color:#fff;
}
.header{ display:flex; align-items:center; gap:12px; padding:10px 12px; cursor:pointer; }
.band{ width:6px; align-self:stretch; border-radius:6px; box-shadow: 0 0 0 1px rgba(0,0,0,.12) inset; }
.band.sales{ background:#749c75; }
.band.forecast{ background:#6A5D7B; }
.head-main{ flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
.top-row{ display:flex; align-items:center; gap:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.type{ font-weight:700; }
.dot{ opacity:.5; }
.meta{ font-size:.9rem; opacity:.9; }
.pill{ min-width:46px; text-align:center; padding:4px 8px; border-radius:999px; font-weight:700; font-size:.85rem; color:#0a0a0a; background: rgba(255,255,255,.75); }
.pill-green{ background:#2ecc71; color:#0a0a0a; }
.pill-yellow{ background:#E6B729; color:#0a0a0a; }
.pill-orange{ background:#E88D1E; color:#0a0a0a; }
.pill-red{ background:#B01513; color:#fff; }

/* Cuerpo expandible: tope 80vh con scroll interno */
.body{
  padding:12px; display:flex; flex-direction:column; gap:12px;
  max-height:80vh; overflow:auto;
}

.grid{ display:grid; grid-template-columns: repeat(4, minmax(140px, 1fr)); gap:10px; }
.box{ background: rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.10); border-radius:8px; padding:8px 10px; }
.k{ font-size:.8rem; opacity:.85; }
.v{ font-weight:700; }

.form .lbl, .plan .lbl{ font-weight:700; margin-bottom:6px; display:block; }
.actions-head{ display:flex; align-items:center; justify-content:space-between; margin-top:8px; }
.actions-head .title{ font-weight:700; }
.actions-list{ display:flex; flex-direction:column; gap:8px; }
.action-row{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }

.note.ok{ background: rgba(5,164,111,.18); border:1px solid rgba(5,164,111,.35); padding:8px 10px; border-radius:8px; }

/* Transición */
.fade-enter-active,.fade-leave-active{ transition: opacity .15s ease; }
.fade-enter-from,.fade-leave-to{ opacity:0; }
</style>
