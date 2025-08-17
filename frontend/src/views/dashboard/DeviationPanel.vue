<template>
  <div class="deviations-wrapper">
    <Toast />

    <GridLayout
      :layout="layout"
      :col-num="12"
      :row-height="8"
      :is-draggable="false"
      :is-resizable="false"
      :margin="[12,12]"
      :use-css-transforms="true"
    >
      <!-- Título en 12 columnas, con GlassCard -->
      <GridItem :i="'title'" :x="0" :y="0" :w="12" :h="4">
        <GlassCard class="title-glass">
          <div class="title-bar">
            <div class="left">
              <h2 class="m-0">Abweichungen</h2>
              <span class="muted">offen: {{ openCount }}</span>
            </div>
            <div class="right">
              <div class="tabs">
                <button class="tab" :class="{ active: tab==='open' }" @click="tab='open'">
                  Offen <span class="badge">{{ openCount }}</span>
                </button>
                <button class="tab" :class="{ active: tab==='just' }" @click="tab='just'">
                  Begründet <span class="badge">{{ justifiedLocal.length }}</span>
                </button>
              </div>
              <div class="actions">
                <Button label="Neu berechnen" icon="pi pi-refresh" class="p-button-sm" :loading="running" @click="runNow" />
                <Button label="Aktualisieren" icon="pi pi-sync" class="p-button-sm p-button-secondary" :loading="loading" @click="loadDeviations" />
              </div>
            </div>
          </div>
        </GlassCard>
      </GridItem>

      <!-- Lista apilada (cada item ya trae su glass) -->
      <GridItem :i="'list'" :x="0" :y="4" :w="12" :h="40">
        <div class="list-wrap">
          <div v-if="loading" class="local-loader">
            <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
            <div class="caption">Wird geladen…</div>
          </div>

          <template v-else>
            <template v-if="tab==='open'">
              <template v-if="deviations.length">
                <DeviationItem
                  v-for="dev in deviations"
                  :key="dev.id"
                  :dev="dev"
                  :saving="savingId===dev.id"
                  @save="onSave"
                />
              </template>
              <div v-else class="empty">Keine offenen Abweichungen.</div>
            </template>

            <template v-else>
              <template v-if="justifiedLocal.length">
                <DeviationItem
                  v-for="dev in justifiedLocal"
                  :key="'j-'+dev.id"
                  :dev="dev"
                  :readonly="true"
                />
              </template>
              <div v-else class="empty">Keine begründeten Abweichungen.</div>
            </template>
          </template>
        </div>
      </GridItem>
    </GridLayout>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import DeviationItem from '@/components/lists/DeviationList.vue'
import GlassCard from '@/components/ui/GlassCard.vue'

const toast = useToast()

const layout = ref([
  { i:'title', x:0, y:0, w:12, h:4, static:true },
  { i:'list',  x:0, y:4, w:12, h:40, static:true }
])

const deviations = ref([])
const justifiedLocal = ref([])
const loading = ref(false)
const running = ref(false)
const savingId = ref(null)
const tab = ref('open')

const openCount = computed(() => deviations.value.filter(d => !d.justified).length)

function normalizeDev(d){
  return {
    id: d.id,
    type: String(d.type || 'sales'),
    clientName: d.clientName || d.client || d.kunde || '',
    pcCode: d.pcCode || d.pc_code || d.code || '',
    pcName: d.pcName || d.pc_name || d.name || '',
    year: Number(d.year || d.y || 0),
    month: Number(d.month || d.m || 0),
    sales: Number(d.sales || 0),
    budget: Number(d.budget || 0),
    forecast: Number(d.forecast || 0),
    deltaAbs: Number(d.deltaAbs ?? d.delta_abs ?? 0),
    deltaPct: Number(d.deltaPct ?? d.delta_pct ?? 0),
    comment: d.comment || '',
    justified: !!d.justified
  }
}

async function loadDeviations(){
  loading.value = true
  try{
    await ensureCsrf()
    const { data } = await api.get('/api/deviations')
    deviations.value = Array.isArray(data) ? data.map(normalizeDev) : []
  } catch {
    deviations.value = []
    toast.add({ severity:'error', summary:'Fehler', detail:'Abweichungen konnten nicht geladen werden', life:2500 })
  } finally { loading.value = false }
}

async function runNow(){
  running.value = true
  try{
    await ensureCsrf()
    await api.post('/api/deviations/run')
    await loadDeviations()
    toast.add({ severity:'success', summary:'OK', detail:'Neu berechnet', life:1600 })
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Neuberechnung fehlgeschlagen', life:2500 })
  } finally { running.value = false }
}

async function onSave(payload){
  const { id, comment } = payload
  savingId.value = id
  try{
    await ensureCsrf()
    await api.put(`/api/deviations/${id}/justify`, { comment })
    const idx = deviations.value.findIndex(d => d.id === id)
    if (idx >= 0) {
      deviations.value[idx] = { ...deviations.value[idx], justified: true, comment }
      if (!justifiedLocal.value.some(j => j.id === id)) {
        justifiedLocal.value.push({ ...deviations.value[idx] })
      }
    }
    toast.add({ severity:'success', summary:'Gespeichert', detail:'Begründung gespeichert', life:1600 })
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Begründung konnte nicht gespeichert werden', life:2500 })
  } finally {
    savingId.value = null
  }
}

onMounted(loadDeviations)
</script>

<style scoped>
.deviations-wrapper{ width: calc(100vw - 70px); overflow: hidden; }

/* Title glass keeps backdrop on every render */
.title-glass{
  padding: 0; /* GlassCard ya tiene padding; usamos layout interno */
  will-change: backdrop-filter;
}
.title-bar{
  display:flex; align-items:center; justify-content:space-between;
  padding: 8px 12px;
  border-radius: 10px;
}
.left{ display:flex; align-items:baseline; gap:10px; }
.muted{ color:#475569; opacity:.9; }
.right{ display:flex; align-items:center; gap:12px; }
.tabs{ display:flex; gap:8px; }
.tab{
  background: rgba(255,255,255,.6);
  border:1px solid rgba(0,0,0,.08);
  border-radius: 999px;
  padding: 6px 10px;
  font-weight:600; color:#111;
}
.tab.active{ border-color: var(--c-blue); box-shadow: 0 0 0 2px rgba(var(--c-blue-rgb),.15) inset; }
.badge{ margin-left: 6px; background: rgba(0,0,0,.06); border-radius: 999px; padding: 2px 6px; font-size:.85em; }

.list-wrap{ position:relative; height:100%; overflow:auto; display:flex; flex-direction:column; gap:12px; }

/* Loader */
.local-loader{
  position:absolute; inset:0;
  display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px;
}
.dots{ display:flex; gap:10px; align-items:center; justify-content:center; }
.dot{ width:10px; height:10px; border-radius:50%; opacity:.9; animation: bounce 1s infinite ease-in-out; box-shadow: 0 2px 6px rgba(0,0,0,.25); }
.dot.g{ background:#22C55E; animation-delay: 0s; } .dot.r{ background:#EF4444; animation-delay:.15s; } .dot.b{ background:#3B82F6; animation-delay:.30s; }
@keyframes bounce{ 0%,80%,100%{ transform: translateY(0) scale(1); opacity:.8; } 40%{ transform: translateY(-8px) scale(1.05); opacity:1; } }
.caption{ font-size:.9rem; color:#334155; opacity:.9; }

.empty{ padding: 18px; text-align: center; color: #475569; opacity: .9; }
</style>