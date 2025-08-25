<!-- src/pages/extra-quota/ExtraQuotasBoard.vue -->
<template>
  <div class="forecast-wrapper">
    <Toast />

    <!-- Unsaved changes -->
    <Dialog
      v-model:visible="confirmVisible"
      :modal="true"
      :draggable="false"
      :dismissableMask="true"
      header="Ungespeicherte Änderungen"
      :style="{ width:'520px' }"
    >
      <p class="mb-3">Es gibt nicht gespeicherte Änderungen. Möchtest du sie speichern?</p>
      <div class="flex justify-content-end gap-2">
        <Button label="Abbrechen" severity="secondary" @click="confirmVisible=false; pendingChange=null" />
        <Button label="Verwerfen" severity="danger" @click="discardAndApply" />
        <Button label="Speichern" icon="pi pi-save" @click="saveAndApply" />
      </div>
    </Dialog>

    <GridLayout
      :layout="layout"
      :col-num="12"
      :row-height="8"
      :is-draggable="false"
      :is-resizable="false"
      :margin="[10,10]"
      :use-css-transforms="true"
    >
      <GridItem
        v-for="item in layout"
        :key="item.i"
        :i="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h"
      >
        <GlassCard :class="{ 'no-strip': item.type==='title' }" :title="getTitle(item)">
          <!-- TITLE -->
          <div v-if="item.type==='title'" class="h-full p-3 flex align-items-center justify-content-between">
            <h2 class="m-0">Zusätzliche Quoten</h2>
            <Button icon="pi pi-plus" label="neue Chance" class="p-button" @click="startCreateMode" />
          </div>

          <!-- LEFT: LIST -->
          <div v-else-if="item.type==='list'" class="h-full p-3">
            <div v-if="listLoading" class="local-loader">
              <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
              <div class="caption">Wird geladen…</div>
            </div>

            <template v-else>
              <Listbox
                v-if="listOptions.length"
                v-model="selectedGroupId"
                :options="listOptions"
                optionLabel="label" optionValue="value"
                listStyle="max-height: 62vh"
                class="w-full dark-list"
                @change="e => onSelectGroup(e.value)"
              >
                <template #option="slotProps">
                  <div class="row-item">
                    <div class="top">
                      <span class="pc">{{ slotProps.option.pc }}</span>
                      <span class="meta">v{{ slotProps.option.version }}</span>
                    </div>
                    <div class="mid">{{ slotProps.option.name || '—' }}</div>
                    <div class="bot">
                      <span class="amt">€ {{ num(slotProps.option.amount) }}</span>
                      <span class="pct">{{ slotProps.option.pct }}%</span>
                    </div>
                  </div>
                </template>
              </Listbox>

              <div v-else class="text-500">Keine Chancen vorhanden.</div>
            </template>
          </div>

          <!-- CENTER: CHANCE FORM (labels above, compact spacing) -->
          <div v-else-if="item.type==='form'" class="h-full p-3">
            <template v-if="createMode || selectedGroupId">
              <div class="form-card-body">
                <!-- Top row: Potential account + pick + Status (labels above) -->
                <div class="form-toprow">
                  <div class="top-cell">
                    <label class="lbl">Potenzieller Kunde</label>
                    <div class="inline-input">
                      <InputText v-model="opForm.potential_client_name" class="flex-1" />
                      <Button label="Kunde wählen" class="p-button-text p-button-sm" @click="pickExistingClient" />
                    </div>
                  </div>
                  <div class="top-cell">
                    <label class="lbl">Status</label>
                    <Dropdown
                      v-model="opForm.status"
                      :options="statusOpts"
                      optionLabel="label"
                      optionValue="value"
                      class="w-full"
                    />
                  </div>
                </div>

                <!-- Two columns: left 30% stacked fields; right rest comment + bottom buttons -->
                <div class="form-two-col">
                  <!-- LEFT 30% -->
                  <div class="left-col">
                    <div>
                      <label class="lbl">Profitcenter</label>
                      <Dropdown
                        v-model="opForm.profit_center_code"
                        :options="assignedPcOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Profitcenter…"
                        class="w-full"
                      />
                    </div>

                    <div class="">
                      <label class="lbl">Volumen</label>
                      <InputNumber
                        v-model="opForm.opportunity_ammount"
                        mode="decimal"
                        :minFractionDigits="2"
                        :maxFractionDigits="2"
                        :step="0.01"
                        inputClass="w-full"
                      />
                    </div>

                    <div class="mt-1">
                      <label class="lbl">Start (Monat/Jahr)</label>
                      <Calendar
                        v-model="opMonthModel"
                        view="month"
                        dateFormat="mm/yy"
                        :manualInput="false"
                        showIcon
                        class="w-full"
                        @update:modelValue="syncMonthYear"
                      />
                    </div>

                    <div class="mt-1">
                      <label class="lbl">Wahrscheinlichkeit</label>
                      <div class="prob-wrap">
                        <Slider
                          v-model="opForm.probability_pct"
                          :min="0"
                          :max="100"
                          :step="10"
                          class="flex-1"
                          @slideend="snapProb"
                          @change="snapProb"
                        />
                        <span class="pct">{{ opForm.probability_pct }}%</span>
                      </div>
                      <!-- marquitas cada 10% -->
                      <div class="tickbar" aria-hidden="true"></div>
                    </div>
                  </div>

                  <!-- RIGHT rest -->
                  <div class="right-col">
                    <div class="right-top">
                      <label class="lbl">Kommentare</label>
                      <Textarea v-model="opForm.comments" rows="7" autoResize class="w-full comment-box" />
                    </div>

                    <div class="right-bottom">
                      <div class="flex gap-2 justify-content-end">
                        <!-- ONLY Budget erstellen in create mode -->
                        <Button
                          v-if="createMode"
                          label="Budget erstellen"
                          icon="pi pi-table"
                          class="p-button-outlined"
                          :disabled="!canCreateBudget"
                          @click="onGenerateBudget"
                        />
                        <!-- Keep version save for existing -->
                        <Button
                          v-else
                          label="Neue Version speichern"
                          icon="pi pi-save"
                          class="p-button-outlined"
                          :disabled="!opDirty"
                          @click="saveNewVersion"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <div v-else class="text-500">Bitte Chance auswählen oder „neue Chance“ drücken…</div>
          </div>

          <!-- RIGHT: EXTRAS -->
          <div v-else-if="item.type==='extras'" class="h-full p-3">
            <div v-if="!selectedGroupId" class="text-500">Keine Auswahl.</div>
            <template v-else>
              <h4 class="mb-2">Versionen</h4>
              <Listbox
                v-model="selectedVersion"
                :options="versionOptions"
                optionLabel="label"
                optionValue="value"
                class="w-full dark-list"
                @change="e => onSelectVersion(e.value)"
              />
              <div class="mt-3 text-500 text-sm">
                <div>Aktuelle Version: <b>v{{ selectedVersion || '—' }}</b></div>
                <div>Letztes Update: {{ latestMeta.updated_at || '—' }}</div>
              </div>
            </template>
          </div>

          <!-- BOTTOM: TABLE -->
          <div v-else-if="item.type==='table'" class="h-full p-2">
            <template v-if="(selectedGroupId && selectedVersion) || showBudgetTable">
              <div v-if="tableLoading" class="local-loader">
                <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
                <div class="caption">Wird geladen…</div>
              </div>
              <template v-else>
                <ComponentTable
                  :months="months"
                  :ventas="ventas"
                  :budget="budget"
                  :forecast="forecast"
                  @edit-forecast="({index,value}) => { const n=Number(value); forecast[index]=Number.isFinite(n)?n:0 }"
                />
                <div class="mt-2 flex gap-2 justify-content-end" v-if="selectedGroupId">
                  <Button label="Budget speichern" icon="pi pi-save" class="p-button-outlined" :disabled="changedBudgetCount===0" @click="saveBudget" />
                  <Button label="Forecast speichern" icon="pi pi-check" :disabled="changedForecastCount===0" @click="saveForecast" />
                </div>
              </template>
            </template>
            <div v-else class="text-500">Keine Tabelle.</div>
          </div>
        </GlassCard>
      </GridItem>
    </GridLayout>
  </div>
</template>

<script setup>
// Code in English; UI texts in German.
import { ref, computed, watch, onMounted } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Listbox from 'primevue/listbox'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Slider from 'primevue/slider'
import Calendar from 'primevue/calendar'

import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import GlassCard from '@/components/ui/GlassCard.vue'
import ComponentTable from '@/components/tables/ComponentTable.vue'

const toast = useToast()

/* Layout */
const layout = ref([
  { i:'filters', x:0,  y:0,  w:2,  h:47, static:true, type:'list'   },
  { i:'title',   x:2,  y:0,  w:10, h:4,  static:true, type:'title'  },
  { i:'chart',   x:2,  y:4,  w:7,  h:26, static:true, type:'form'   },
  { i:'chart2',  x:9,  y:4,  w:3,  h:26, static:true, type:'extras' },
  { i:'table',   x:2,  y:30, w:10, h:17, static:true, type:'table'  }
])
function getTitle(item){
  if (item.type==='title')  return ''
  if (item.type==='list')   return 'Chancen'
  if (item.type==='form')   return 'Chance bearbeiten / erstellen'
  if (item.type==='extras') return 'Extras'
  if (item.type==='table')  return 'Tabelle'
  return ''
}

/* Guards */
const confirmVisible = ref(false)
const pendingChange = ref(null)
function cloneDeep(v){ return JSON.parse(JSON.stringify(v)) }

/* List */
const listLoading = ref(false)
const listOptions = ref([])
const selectedGroupId = ref(null)
const selectedVersion = ref(null)
const latestMeta = ref({})

async function loadList(){
  listLoading.value = true
  try{
    await ensureCsrf()
    const { data } = await api.get('/api/extra-quota/opportunities', { params: { page: 1 } })
    const rows = Array.isArray(data?.data) ? data.data : []
    listOptions.value = rows.map(r => ({
      value: Number(r.opportunity_group_id),
      label: r.potential_client_name || `Gruppe ${r.opportunity_group_id}`,
      pc: String(r.profit_center_code || ''),
      version: Number(r.version || 1),
      name: r.potential_client_name || '',
      amount: Number(r.opportunity_ammount || 0),
      pct: Number(r.probability_pct || 0),
    }))
  } finally { listLoading.value = false }
}

async function onSelectGroup(gid){
  if (dirtyAny()){ confirmVisible.value=true; pendingChange.value={ kind:'group', value: gid }; return }
  applyChange('group', gid)
}
function onSelectVersion(v){
  if (dirtyAny()){ confirmVisible.value=true; pendingChange.value={ kind:'version', value: v }; return }
  applyChange('version', v)
}

/* Create mode */
const createMode = ref(false)
const showBudgetTable = ref(false)

function startCreateMode(){
  if (dirtyAny()){ confirmVisible.value=true; pendingChange.value={ kind:'new' }; return }
  enterCreateMode()
}
function enterCreateMode(){
  createMode.value = true
  selectedGroupId.value = null
  selectedVersion.value = null
  showBudgetTable.value = false

  opForm.value = {
    user_id: null,
    fiscal_year: new Date().getFullYear(),
    profit_center_code: '',
    opportunity_ammount: 0,
    probability_pct: 0,
    estimated_start_date: null,
    comments: '',
    potential_client_name: '',
    client_group_number: '',
    status: 'open'
  }
  opBaseline.value = cloneDeep(opForm.value)
  opMonthModel.value = null

  initBlankTable()
}

function snapProb(){
  const v = Number(opForm.value.probability_pct || 0)
  const snapped = Math.round(v / 10) * 10
  opForm.value.probability_pct = Math.min(100, Math.max(0, snapped))
}

/* Profit Centers strictly by Extra Quota Assignment (fallback to me/profit-centers) */
const assignedPcOptions = ref([])
async function loadAssignedPcs() {
  try {
    await ensureCsrf()
    let pcs = []
    try {
      const res = await api.get('/api/extra-quota/assignments/my-profit-centers')
      pcs = Array.isArray(res.data) ? res.data : []
    } catch {
      const res = await api.get('/api/me/profit-centers')
      pcs = Array.isArray(res.data) ? res.data : []
    }
    const out = []; const seen = new Set()
    for (const r of pcs) {
      const code = String(r.profit_center_code ?? r.code ?? '').trim()
      if (!code || seen.has(code)) continue
      seen.add(code)
      const name = String(r.name ?? code)
      out.push({ label: `${code} — ${name}`, value: code })
    }
    assignedPcOptions.value = out
  } catch { assignedPcOptions.value = [] }
}

/* Form */
const statusOpts = ref([
  { label:'Entwurf', value:'draft' },
  { label:'Offen',   value:'open'  },
  { label:'Gewonnen',value:'won'   },
  { label:'Verloren',value:'lost'  },
])
const creating = ref(false)
const probTouched = ref(false)
const voucherGuide = ref('') // UI-only

const opMonthModel = ref(null)
const opForm = ref({
  user_id: null,
  fiscal_year: new Date().getFullYear(),
  profit_center_code: '',
  opportunity_ammount: 0,
  probability_pct: 0,
  estimated_start_date: null,
  comments: '',
  potential_client_name: '',
  client_group_number: '',
  status: 'draft'
})
const opBaseline = ref(cloneDeep(opForm.value))
const opDirty = computed(() => {
  const active = !!selectedGroupId.value || !!createMode.value
  if (!active) return false
  if (!opBaseline.value) return false
  return JSON.stringify(opForm.value) !== JSON.stringify(opBaseline.value)
})
const canCreateBudget = computed(() =>
  !!opForm.value.potential_client_name &&
  !!opForm.value.profit_center_code &&
  Number(opForm.value.opportunity_ammount) > 0 &&
  !!opForm.value.estimated_start_date &&
  Number(opForm.value.probability_pct) > 0
)

function pickExistingClient(){ /* TODO: open client picker dialog */ }
function syncMonthYear(d) {
  if (!d) { opForm.value.estimated_start_date = null; return }
  const dt = new Date(d)
  const y = dt.getFullYear()
  const m = dt.getMonth() + 1
  opForm.value.estimated_start_date = `${y}-${String(m).padStart(2,'0')}-01`
  opForm.value.fiscal_year = (m < 4) ? y - 1 : y
}

/* Table */
const months   = ref([])
const ventas   = ref(Array(12).fill(0))
const budget   = ref([])
const forecast = ref([])
const baseBudget   = ref([])
const baseForecast = ref([])
const tableLoading = ref(false)

function fiscalIndexFromCalMonth(calM){ const map = {4:1,5:2,6:3,7:4,8:5,9:6,10:7,11:8,12:9,1:10,2:11,3:12}; return map[calM] || 1 }
function calMonthFromFiscalIndex(idx){ const arr=[4,5,6,7,8,9,10,11,12,1,2,3]; return arr[idx-1] || 4 }
function ym(y,m){ return `${y}-${String(m).padStart(2,'0')}` }
function fiscalMonths(fy){ return Array.from({length:12},(_,i)=>{ const m=calMonthFromFiscalIndex(i+1); const y=(m>=4)?fy:fy+1; return ym(y,m) }) }
function num(v){ return Number(v||0).toLocaleString('de-DE',{ minimumFractionDigits:2, maximumFractionDigits:2 }) }
function near(a,b,eps=1e-9){ return Math.abs(Number(a||0)-Number(b||0))<=eps }

function initBlankTable(){
  const fy = opForm.value.fiscal_year || new Date().getFullYear()
  months.value = fiscalMonths(fy)
  budget.value = Array(12).fill(0)
  forecast.value = Array(12).fill(0)
  baseBudget.value = [...budget.value]
  baseForecast.value = [...forecast.value]
}

const changedBudgetCount = computed(() => budget.value.reduce((n, v, i) => n + (!near(v, baseBudget.value[i]) ? 1 : 0), 0))
const changedForecastCount = computed(() => forecast.value.reduce((n, v, i) => n + (!near(v, baseForecast.value[i]) ? 1 : 0), 0))

/* Seasonality */
const seasonality = ref(Array(12).fill(1))
function remainingIndicesFromStart(estimatedYmd){
  if (!estimatedYmd) return { startIdx:1, indices:[...Array(12).keys()].map(i=>i+1) }
  const [, mS] = estimatedYmd.split('-'); const m = +mS
  const startIdx = fiscalIndexFromCalMonth(m)
  const indices = []; for (let i=startIdx;i<=12;i++) indices.push(i)
  return { startIdx, indices }
}

/* Generate budget */
function onGenerateBudget(){
  const amt = Number(opForm.value.opportunity_ammount || 0)
  const pct = Number(opForm.value.probability_pct || 0)
  if (!canCreateBudget.value) return

  const fy = Number(opForm.value.fiscal_year || new Date().getFullYear())
  months.value = fiscalMonths(fy)

  const expected = amt * (pct / 100)
  const { indices } = remainingIndicesFromStart(opForm.value.estimated_start_date)

  const weights = indices.map(fi => Number(seasonality.value[fi-1] || 1))
  const sumW = weights.reduce((a,b)=>a+b,0) || 1
  const alloc = weights.map(w => expected * (w / sumW))

  const newBudget = Array(12).fill(0)
  indices.forEach((fi, k) => { newBudget[fi-1] = alloc[k] })

  budget.value = newBudget
  forecast.value = newBudget.slice()
  baseBudget.value = newBudget.slice()
  baseForecast.value = newBudget.slice()

  showBudgetTable.value = true
}

/* Backend loads/saves */
const versionOptions = ref([])

async function loadGroupMeta(){
  if (!selectedGroupId.value) return
  await ensureCsrf()
  const { data } = await api.get(`/api/extra-quota/opportunities/${selectedGroupId.value}`)
  const latest = data?.latest || {}
  latestMeta.value = latest
  const vers = Array.isArray(data?.versions) ? data.versions.map(v => Number(v.version)).sort((a,b)=>a-b) : []
  versionOptions.value = vers.map(v => ({ value: v, label: `v${v}` }))
  if (!selectedVersion.value && vers.length) selectedVersion.value = vers[vers.length-1]
  opForm.value = {
    user_id: latest.user_id ?? null,
    fiscal_year: latest.fiscal_year ?? new Date().getFullYear(),
    profit_center_code: latest.profit_center_code ?? '',
    opportunity_ammount: Number(latest.opportunity_ammount ?? 0),
    probability_pct: Number(latest.probability_pct ?? 0),
    estimated_start_date: latest.estimated_start_date ?? null,
    comments: latest.comments ?? '',
    potential_client_name: latest.potential_client_name ?? '',
    client_group_number: latest.client_group_number ?? '',
    status: latest.status || 'open'
  }
  opBaseline.value = cloneDeep(opForm.value)
  opMonthModel.value = opForm.value.estimated_start_date ? new Date(opForm.value.estimated_start_date) : null
  showBudgetTable.value = false
}

async function loadSeries(){
  if (!selectedGroupId.value || !selectedVersion.value){
    initBlankTable(); return
  }
  tableLoading.value = true
  try{
    await ensureCsrf()
    const [bRes, fRes] = await Promise.all([
      api.get(`/api/extra-quota/budget/${selectedGroupId.value}/${selectedVersion.value}`,   { params: { fiscal_year: opForm.value.fiscal_year } }),
      api.get(`/api/extra-quota/forecast/${selectedGroupId.value}/${selectedVersion.value}`, { params: { fiscal_year: opForm.value.fiscal_year } })
    ])
    const b = Array.isArray(bRes.data) ? bRes.data : []
    const f = Array.isArray(fRes.data) ? fRes.data : []
    months.value   = b.length ? b.map(r => ym(r.fiscal_year, r.month)) : fiscalMonths(opForm.value.fiscal_year)
    budget.value   = b.length ? b.map(r => Number(r.volume||0)) : Array(12).fill(0)
    forecast.value = f.length ? f.map(r => Number(r.volume||0)) : Array(12).fill(0)
    baseBudget.value = [...budget.value]
    baseForecast.value = [...forecast.value]
    showBudgetTable.value = true
  } finally { tableLoading.value = false }
}

async function saveNewVersion(){
  if (!selectedGroupId.value) return
  await ensureCsrf()
  const payload = {
    user_id:            opForm.value.user_id,
    fiscal_year:        opForm.value.fiscal_year,
    profit_center_code: opForm.value.profit_center_code,
    opportunity_ammount:opForm.value.opportunity_ammount,
    probability_pct:    opForm.value.probability_pct,
    estimated_start_date:opForm.value.estimated_start_date,
    comments:           opForm.value.comments,
    potential_client_name: opForm.value.potential_client_name,
    client_group_number: opForm.value.client_group_number,
    status:             opForm.value.status || 'open'
  }
  const { data } = await api.post(`/api/extra-quota/opportunities/${selectedGroupId.value}/version`, payload)
  selectedVersion.value = Number(data?.version || selectedVersion.value || 1)
  opBaseline.value = cloneDeep(opForm.value)
  toast.add({ severity:'success', summary:'Gespeichert', detail:'Neue Version gespeichert', life:1400 })
  await loadGroupMeta()
  await loadSeries()
  await loadList()
}

async function saveBudget(){
  if (!selectedGroupId.value || changedBudgetCount.value===0) return
  await ensureCsrf()
  const items = months.value.map((ym,i)=>{ const [y,m]=ym.split('-').map(n=>parseInt(n,10)); return { month:m, fiscal_year:y, volume:Number(budget.value[i]||0) } })
  await api.post(`/api/extra-quota/budget/${selectedGroupId.value}/${selectedVersion.value}/save`, { items })
  baseBudget.value = [...budget.value]
  toast.add({ severity:'success', summary:'Gespeichert', detail:'Budget gespeichert', life:1400 })
}
async function saveForecast(){
  if (!selectedGroupId.value || changedForecastCount.value===0) return
  await ensureCsrf()
  const items = months.value.map((ym,i)=>{ const [y,m]=ym.split('-').map(n=>parseInt(n,10)); return { month:m, fiscal_year:y, volume:Number(forecast.value[i]||0) } })
  await api.post(`/api/extra-quota/forecast/${selectedGroupId.value}/${selectedVersion.value}/save`, { items })
  baseForecast.value = [...forecast.value]
  toast.add({ severity:'success', summary:'Gespeichert', detail:'Forecast gespeichert', life:1400 })
}

/* Guard logic */
function dirtyAny(){
  const active = !!selectedGroupId.value || !!createMode.value
  if (!active) return false
  return opDirty.value || changedBudgetCount.value>0 || changedForecastCount.value>0
}
async function saveAndApply(){
  try{
    if (selectedGroupId.value && opDirty.value) await saveNewVersion()
    if (selectedGroupId.value && changedBudgetCount.value>0) await saveBudget()
    if (selectedGroupId.value && changedForecastCount.value>0) await saveForecast()
  } finally {
    confirmVisible.value=false
    if (pendingChange.value){
      const { kind, value } = pendingChange.value
      applyChange(kind, value)
    }
    pendingChange.value=null
  }
}
function discardAndApply(){
  if (opBaseline.value) opForm.value = cloneDeep(opBaseline.value)
  budget.value = [...baseBudget.value]
  forecast.value = [...baseForecast.value]
  confirmVisible.value=false
  if (pendingChange.value){
    const { kind, value } = pendingChange.value
    applyChange(kind, value)
  }
  pendingChange.value=null
}
function applyChange(kind, value){
  if (kind==='new'){ enterCreateMode(); return }
  if (kind==='group'){
    selectedGroupId.value = Number(value) || null
    selectedVersion.value = null
    createMode.value = false
    showBudgetTable.value = false
    loadGroupMeta().then(loadSeries)
    return
  }
  if (kind==='version'){
    selectedVersion.value = Number(value) || null
    loadSeries()
    return
  }
}

/* Mount */
onMounted(async () => {
  await loadAssignedPcs()
  await loadList()
})
</script>

<style scoped>
.forecast-wrapper{ height:100vh; width:100%; overflow:hidden; }
.no-strip :deep(.card-header), .no-strip :deep(.glass-title), .no-strip :deep(.p-card-header){ display:none !important; }

/* Left list */
.dark-list :deep(.p-listbox-list){ background:transparent; }
.row-item{ display:flex; flex-direction:column; gap:2px; padding:6px; border-radius:8px; }
.row-item:hover{ background: rgba(255,255,255,0.06); }
.row-item .top{ display:flex; justify-content:space-between; color:#cbd5e1; font-size:12px; }
.row-item .mid{ color:#e5e7eb; font-weight:600; }
.row-item .bot{ display:flex; justify-content:space-between; color:#cbd5e1; font-size:12px; }

/* FORM CARD LAYOUT */
.form-card-body{ display:flex; flex-direction:column; gap:10px; height:100%; overflow:hidden; }

/* Top row: two cells; labels above */
.form-toprow{
  display:grid; grid-template-columns: minmax(0,2fr) minmax(180px,1fr);
  gap:10px; align-items:start;
  padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.08);
}
.top-cell{ display:flex; flex-direction:column; gap:4px; }
.inline-input{ display:flex; align-items:center; gap:8px; }

/* Two columns area with compact gaps, no overflow */
.form-two-col{
  display:grid; grid-template-columns: 30% 1fr; gap:12px; flex:1 1 auto; min-height:0;
  padding-top:8px;
}
.left-col{ display:flex; flex-direction:column; gap:8px; overflow:hidden; }
.right-col{ display:grid; grid-template-rows: 1fr auto; gap:8px; min-height:0; }
.right-top{ min-height:0; overflow:hidden; }
.right-bottom{ align-self:end; }

.field{ display:flex; flex-direction:column; gap:4px; }
.lbl{ color:#cbd5e1; font-weight:600; }

/* Comments bigger but contained */
.comment-box{ min-height:160px; }

/* Table/Loader */
.local-loader{ position: fixed; inset: 0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; z-index:50; }
.dots{ display:flex; gap:10px; align-items:center; justify-content:center; }
.dot{ width:10px; height:10px; border-radius:50%; opacity:0.9; animation:bounce 1s infinite ease-in-out; box-shadow:0 2px 6px rgba(0,0,0,0.25); }
.dot.g{ background:#22c55e; animation-delay:0s; } .dot.r{ background:#ef4444; animation-delay:.15s; } .dot.b{ background:#3b82f6; animation-delay:.3s; }
@keyframes bounce{ 0%,80%,100%{ transform:translateY(0) scale(1); opacity:.8 } 40%{ transform:translateY(-8px) scale(1.05); opacity:1 } }
.caption{ font-size:.9rem; color:#e5e7eb; opacity:.9; }

.prob-wrap { display:flex; align-items:center; gap:8px; }
.tickbar { width:85%; height:6px; margin-top:4px; position:relative; }
.tickbar::before {
  content: '';
  position: absolute; inset: 0;
  /* línea finita cada 10% */
  background-image: repeating-linear-gradient(
    to right,
    rgba(255,255,255,0.35) 0,
    rgba(255,255,255,0.35) 1px,
    transparent 1px,
    transparent 10%
  );
  opacity: 0.8;
}

</style>