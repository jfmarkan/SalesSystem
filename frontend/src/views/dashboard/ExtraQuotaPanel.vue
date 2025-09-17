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
      :style="{ width: '520px' }"
    >
      <p class="mb-3">Es gibt nicht gespeicherte Änderungen. Möchtest du sie speichern?</p>
      <div class="flex justify-content-end gap-2">
        <Button label="Abbrechen" severity="secondary" @click="confirmVisible = false, pendingChange = null" />
        <Button label="Verwerfen" severity="danger" @click="discardAndApply" />
        <Button label="Speichern" icon="pi pi-save" @click="saveAndApply" />
      </div>
    </Dialog>

    <!-- Won flow -->
    <Dialog
      v-model:visible="wonDialogVisible"
      :modal="true"
      :draggable="false"
      header="Chance gewonnen"
      :style="{ width: '420px' }"
    >
      <div class="field mb-2">
        <label class="lbl">Client Group Number</label>
        <InputText v-model="clientGroupInput" class="w-full" placeholder="z.B. 12345" />
      </div>
      <div class="field">
        <label class="lbl">Client Name</label>
        <InputText v-model="clientNameInput" class="w-full" placeholder="Firmenname" />
      </div>
      <div class="mt-3 flex justify-content-end gap-2">
        <Button label="Abbrechen" severity="secondary" :disabled="finalizing" @click="cancelWonFlow" />
        <Button label="Übernehmen" icon="pi pi-check" :loading="finalizing" :disabled="!clientGroupInput || !clientNameInput" @click="finalizeWon" />
      </div>
    </Dialog>

    <GridLayout :layout="layout" :col-num="12" :row-height="8" :is-draggable="false" :is-resizable="false" :margin="[10, 10]" :use-css-transforms="true">
      <GridItem v-for="item in layout" :key="item.i" :i="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h">
        <GlassCard :class="{ 'no-strip': item.type === 'title' }" :title="getTitle(item)">

          <!-- TITLE -->
          <div v-if="item.type === 'title'" class="h-full p-3 flex align-items-center justify-content-between">
            <h2 class="m-0">Zusätzliche Quoten</h2>
            <Button icon="pi pi-plus" label="neue Chance" class="p-button" @click="startCreateMode" />
          </div>

          <!-- LEFT: LIST -->
          <div v-else-if="item.type === 'list'" class="h-full p-3">
            <div class="status-filter mb-2">
              <Button label="Öffnen" size="small" :severity="statusFilter==='open' ? 'primary':'secondary'" @click="setStatusFilter('open')" />
              <Button label="Gewonnen" size="small" :severity="statusFilter==='won' ? 'primary':'secondary'" @click="setStatusFilter('won')" />
              <Button label="Verloren" size="small" :severity="statusFilter==='lost' ? 'primary':'secondary'" @click="setStatusFilter('lost')" />
            </div>

            <div v-if="listLoading" class="local-loader">
              <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
              <div class="caption">Wird geladen…</div>
            </div>
            <template v-else>
              <Listbox
                v-if="listOptions.length"
                v-model="selectedGroupId"
                :options="listOptions"
                optionLabel="label"
                optionValue="value"
                listStyle="max-height: 58vh"
                class="dark-list"
                @change="(e) => onSelectGroup(e.value)"
              >
                <template #option="slotProps">
                  <div class="row-item">
                    <div class="top">
                      <span class="pc">{{ slotProps.option.pc }}</span>
                      <span class="meta">{{ slotProps.option.statusLabel }}</span>
                    </div>
                    <div class="mid">{{ slotProps.option.name || '—' }}</div>
                    <div class="bot">
                      <span class="amt">{{ fmtInt(slotProps.option.amount) }}</span>
                      <span class="pct">{{ slotProps.option.pct }}%</span>
                    </div>
                  </div>
                </template>
              </Listbox>
              <div v-else class="text-500">Keine Chancen vorhanden.</div>
            </template>
          </div>

          <!-- CENTER: FORM -->
          <div v-else-if="item.type === 'form'" class="h-full p-3">
            <template v-if="createMode || selectedGroupId">
              <div class="form-card-body">
                <div class="form-toprow">
                  <div class="top-cell">
                    <label class="lbl">Potenzieller Kunde</label>
                    <div class="inline-input">
                      <InputText v-model="opForm.potential_client_name" class="flex-1" :disabled="isLocked" />
                      <Button label="Kunde wählen" class="p-button-text p-button-sm" :disabled="isLocked" @click="pickExistingClient" />
                    </div>
                  </div>
                  <div class="top-cell">
                    <label class="lbl">Status</label>
                    <Dropdown v-model="opForm.status" :options="statusOpts" optionLabel="label" optionValue="value" class="w-full" :disabled="isLocked" />
                  </div>
                </div>

                <div class="form-two-col">
                  <div class="left-col">
                    <div class="mt-1">
                      <label class="lbl">Profitcenter</label>
                      <Dropdown
                        v-model="opForm.profit_center_code"
                        :options="assignedPcOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Profitcenter…"
                        class="w-full"
                        @change="updateAvailabilityForPc"
                        :disabled="isLocked"
                      />
                    </div>

                    <div class="mt-1">
                      <label class="lbl">Volume</label>
                      <div class="vol-inline">
                        <InputNumber
  v-model="opForm.volume"
  :min="0"
  :step="1"
  :useGrouping="true"
  locale="de-DE"
  :minFractionDigits="0"
  :maxFractionDigits="0"
  inputClass="w-full"
  :disabled="isLocked"
/>

                        <span class="assigned">/ {{ fmtInt(availableForSelected) }}</span>
                      </div>
                    </div>

                    <div class="mt-1">
                      <label class="lbl">Start (Monat/Jahr)</label>
                      <Calendar v-model="opMonthModel" view="month" dateFormat="mm/yy" :manualInput="false" showIcon class="w-full" @update:modelValue="syncMonthYear" :disabled="isLocked" />
                    </div>

                    <div class="mt-1">
                      <label class="lbl">Wahrscheinlichkeit</label>
                      <div class="prob-wrap">
                        <Slider v-model="opForm.probability_pct" :min="0" :max="100" :step="10" class="flex-1" @slideend="snapProb" @change="snapProb" :disabled="isLocked" />
                        <span class="pct">{{ opForm.probability_pct }}%</span>
                      </div>
                      <div class="tickbar" aria-hidden="true"></div>
                    </div>
                  </div>

                  <div class="right-col">
                    <div class="right-top">
                      <label class="lbl">Kommentare</label>
                      <Textarea v-model="opForm.comments" rows="8" autoResize class="w-full comment-box" :disabled="isLocked" />
                    </div>
                    <div class="right-bottom">
                      <div class="flex gap-2 justify-content-end">
                        <Button
                          v-if="createMode"
                          label="Budget erstellen"
                          icon="pi pi-table"
                          class="p-button-outlined"
                          :disabled="isLocked || !canCreateBudget"
                          @click="onGenerateBudget"
                        />
                        <Button
                          v-else
                          label="Aktualisieren"
                          icon="pi pi-save"
                          class="p-button-outlined"
                          :disabled="isLocked || !opDirty"
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
          <div v-else-if="item.type === 'extras'" class="h-full p-3 extras-wrap">
            <div v-if="!selectedGroupId" class="text-500">Keine Auswahl.</div>
            <template v-else>
              <div>
                <Listbox
                  v-if="versionOptions.length > 1"
                  v-model="selectedVersion"
                  :options="versionOptions"
                  optionLabel="label"
                  optionValue="value"
                  class="w-full dark-list"
                  @change="(e) => onSelectVersion(e.value)"
                />
                <div v-else class="text-500">—</div>
              </div>
              <div class="extras-bottom text-500 text-sm">
                <div>Aktuelle Version: <b>v{{ selectedVersion || '—' }}</b></div>
                <div>Letztes Update: {{ latestMeta.updated_at || '—' }}</div>
              </div>
            </template>
          </div>

          <!-- BOTTOM: TABLE -->
          <div v-else-if="item.type === 'table'" class="h-full p-2">
            <template v-if="createMode || selectedGroupId">
              <div v-if="tableLoading" class="local-loader">
                <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
                <div class="caption">Wird geladen…</div>
              </div>
              <template v-else>
                <div class="ctbl-wrap" :class="{ locked: isLocked }">
                  <ComponentTable
                    :months="months"
                    :ventas="sales"
                    :budget="budget"
                    :forecast="forecast"
                    @edit-forecast="onEditForecastInt"
                  />
                </div>
                <div class="mt-2 flex gap-2 justify-content-end" v-if="selectedGroupId">
                  <Button label="Forecast speichern" icon="pi pi-check" :disabled="isLocked || changedForecastCount === 0" @click="saveForecast()" />
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
import { ref, computed, onMounted, watch, nextTick } from 'vue'
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
  { i: 'filters', x: 0, y: 0, w: 2, h: 47, static: true, type: 'list' },
  { i: 'title', x: 2, y: 0, w: 10, h: 4, static: true, type: 'title' },
  { i: 'chart', x: 2, y: 4, w: 7, h: 26, static: true, type: 'form' },
  { i: 'chart2', x: 9, y: 4, w: 3, h: 26, static: true, type: 'extras' },
  { i: 'table', x: 2, y: 30, w: 10, h: 17, static: true, type: 'table' },
])
function getTitle(item) {
  if (item.type === 'title') return ''
  if (item.type === 'list') return 'Chancen'
  if (item.type === 'form') return 'Chance bearbeiten / erstellen'
  if (item.type === 'extras') return 'Versionen'
  if (item.type === 'table') return 'Tabelle'
  return ''
}

/* Guards */
const confirmVisible = ref(false)
const pendingChange = ref(null)
const cloneDeep = (v) => JSON.parse(JSON.stringify(v))

/* List + filter */
const listLoading = ref(false)
const allRows = ref([])
const listOptions = ref([])
const statusFilter = ref('open')
const selectedGroupId = ref(null)
const selectedVersion = ref(null)
const latestMeta = ref({})
function fmtInt(v) { return Number(v || 0).toLocaleString('de-DE', { maximumFractionDigits: 0 }) }

function normStatus(s){
  const x = String(s||'').toLowerCase()
  if (x==='draft') return 'open'
  if (x.includes('won')) return 'won'
  if (x.includes('lost')) return 'lost'
  return x
}
function applyListFilter(){
  const want = statusFilter.value
  const rows = Array.isArray(allRows.value) ? allRows.value : []
  listOptions.value = rows
    .filter(r => normStatus(r.status) === want)
    .map((r) => {
      const codeNum = Number(r.profit_center_code || 0)
      return {
        value: Number(r.opportunity_group_id),
        label: r.potential_client_name || `Gruppe ${r.opportunity_group_id}`,
        pc: String(codeNum),
        version: Number(r.version || 1),
        name: r.potential_client_name || '',
        amount: Number(r.volume || 0),
        pct: Number(r.probability_pct || 0),
        statusLabel: normStatus(r.status) === 'won' ? 'Gewonnen' : (normStatus(r.status) === 'lost' ? 'Verloren' : 'Offen'),
      }
    })
}
function setStatusFilter(s){
  statusFilter.value = s
  selectedGroupId.value = null
  selectedVersion.value = null
  createMode.value = false
  showBudgetTable.value = false
  loadList()
}
watch(statusFilter, () => { loadList() })

async function loadList() {
  listLoading.value = true
  try {
    await ensureCsrf()
    const { data } = await api.get('/api/extra-quota/opportunities', { params: { status: statusFilter.value } })
    allRows.value = Array.isArray(data) ? data : []
    applyListFilter()
  } finally { listLoading.value = false }
}

function onSelectGroup(gid) {
  if (dirtyAny()) { confirmVisible.value = true; pendingChange.value = { kind: 'group', value: gid }; return }
  applyChange('group', gid)
}
function onSelectVersion(v) {
  if (dirtyAny()) { confirmVisible.value = true; pendingChange.value = { kind: 'version', value: v }; return }
  applyChange('version', v)
}

/* Create mode */
const createMode = ref(false)
const showBudgetTable = ref(false)

function startCreateMode(){
  if (dirtyAny()){ confirmVisible.value = true; pendingChange.value = { kind: 'new' }; return }
  enterCreateMode()
}
async function enterCreateMode(){
  createMode.value = true
  selectedGroupId.value = null
  selectedVersion.value = null
  versionOptions.value = []
  latestMeta.value = {}
  showBudgetTable.value = false

  opForm.value = {
    user_id: null,
    fiscal_year: new Date().getFullYear(),
    profit_center_code: null,
    volume: 0,
    probability_pct: 0,
    estimated_start_date: null,
    comments: '',
    potential_client_name: '',
    client_group_number: '',
    status: 'open'
  }
  opBaseline.value = cloneDeep(opForm.value)
  opMonthModel.value = null
  availableForSelected.value = 0

  await loadAssignedPcs()
  initBlankTable()
}

/* PCs del usuario + disponible */
const assignedPcOptions = ref([])
const availableForSelected = ref(0)
async function loadAssignedPcs() {
  await ensureCsrf()
  const fy = opForm.value?.fiscal_year || new Date().getFullYear()
  const { data } = await api.get('/api/extra-quota/assignments/my-profit-centers', { params: { fiscal_year: fy } })
  const rows = Array.isArray(data) ? data : []
  const out = []
  for (const r of rows) {
    const code = Number(r.profit_center_code ?? 0)
    if (!code) continue
    out.push({ label: String(code), value: code })
  }
  assignedPcOptions.value = out
}
async function updateAvailabilityForPc() {
  const code = Number(opForm.value.profit_center_code)
  const fy = opForm.value.fiscal_year
  availableForSelected.value = 0
  if (!code || !fy) return
  await ensureCsrf()
  const { data } = await api.get('/api/extra-quota/assignments/my-availability', { params: { profit_center_code: code, fiscal_year: fy } })
  availableForSelected.value = Number(data?.available || 0)
}

/* Form */
const statusOpts = ref([
  { label: 'Entwurf', value: 'draft' },
  { label: 'Offen', value: 'open' },
  { label: 'Gewonnen', value: 'won' },
  { label: 'Verloren', value: 'lost' },
])
const wonDialogVisible = ref(false)
const clientGroupInput = ref('')
const clientNameInput = ref('')
const finalizing = ref(false)

const creating = ref(false)
const opMonthModel = ref(null)

/* suprime el watch cuando cargamos */
const suppressStatusWatch = ref(false)

const opForm = ref({
  user_id: null,
  fiscal_year: new Date().getFullYear(),
  profit_center_code: null,
  volume: 0,
  probability_pct: 0,
  estimated_start_date: null,
  comments: '',
  potential_client_name: '',
  client_group_number: '',
  status: 'draft',
})
const opBaseline = ref(cloneDeep(opForm.value))
const opDirty = computed(() => {
  const active = !!selectedGroupId.value || !!createMode.value
  if (!active || !opBaseline.value) return false
  return JSON.stringify(opForm.value) !== JSON.stringify(opBaseline.value)
})
const canCreateBudget = computed(() =>
  !!opForm.value.potential_client_name &&
  Number(opForm.value.profit_center_code) > 0 &&
  Number(opForm.value.volume) > 0 &&
  !!opForm.value.estimated_start_date &&
  Number(opForm.value.probability_pct) > 0
)
function pickExistingClient() {}
function syncMonthYear(d) {
  if (!d) { opForm.value.estimated_start_date = null; return }
  const dt = new Date(d)
  const y = dt.getFullYear(), m = dt.getMonth() + 1
  opForm.value.estimated_start_date = `${y}-${String(m).padStart(2, '0')}-01`
  opForm.value.fiscal_year = (m < 4) ? y - 1 : y
  loadAssignedPcs().then(updateAvailabilityForPc)
}
function snapProb(){
  const v = Number(opForm.value.probability_pct || 0)
  opForm.value.probability_pct = Math.min(100, Math.max(0, Math.round(v/10)*10))
}

/* Locking */
const statusNormalized = computed(() => normStatus(opForm.value.status))
const isLocked = computed(() => statusNormalized.value === 'won' || statusNormalized.value === 'lost')

/* Status change */
watch(() => opForm.value.status, async (st, prev) => {
  if (suppressStatusWatch.value) return
  if (!st || st === prev) return
  if (!selectedGroupId.value) return
  if (st === 'won') {
    opForm.value.probability_pct = 100
    await rebuildBudgetFromForm()
    wonDialogVisible.value = true
    return
  }
  if (st === 'lost') {
    const ok = window.confirm('Diese Chance als "verloren" schließen? (Menge wird freigegeben)')
    if (!ok) { opForm.value.status = prev; return }
    await finalizeLost()
  }
})
function cancelWonFlow(){
  wonDialogVisible.value = false
  opForm.value.status = 'open'
}

/* Table */
const months = ref([])
const sales = ref(Array(12).fill(0))
const budget = ref([])
const forecast = ref([])
const baseBudget = ref([])
const baseForecast = ref([])
const tableLoading = ref(false)

function fiscalIndexFromCalMonth(calM){ const map = {4:1,5:2,6:3,7:4,8:5,9:6,10:7,11:8,12:9,1:10,2:11,3:12}; return map[calM] || 1 }
function calMonthFromFiscalIndex(idx){ const arr=[4,5,6,7,8,9,10,11,12,1,2,3]; return arr[idx-1] || 4 }
function ym(y,m){ return `${y}-${String(m).padStart(2,'0')}` }
function fiscalMonths(fy){ return Array.from({length:12},(_,i)=>{ const m=calMonthFromFiscalIndex(i+1); const y=(m>=4)?fy:fy+1; return ym(y,m) }) }
function num0(v){ return Number(v||0) }
function isPastYM(ymStr){
  if (!ymStr) return false
  const [yS, mS] = ymStr.split('-'); const y=+yS; const m=+mS
  const now = new Date(); const cur = new Date(now.getFullYear(), now.getMonth(), 1)
  const tgt = new Date(y, m-1, 1)
  return tgt < cur
}

function initBlankTable(){
  const fy = opForm.value.fiscal_year || new Date().getFullYear()
  months.value = fiscalMonths(fy)
  budget.value = Array(12).fill(0)
  forecast.value = Array(12).fill(0)
  baseBudget.value = [...budget.value]
  baseForecast.value = [...forecast.value]
}
const changedBudgetCount = computed(() => budget.value.reduce((n, v, i) => n + (v !== baseBudget.value[i] ? 1 : 0), 0))
const changedForecastCount = computed(() => forecast.value.reduce((n, v, i) => n + (v !== baseForecast.value[i] ? 1 : 0), 0))

/* Seasonality */
async function getSeasonalityForPc(code, fy){
  const toNum = (x)=>{ if(x==null) return 0; const s=String(x).trim().replace(',','.'); const n=Number(s); return isNaN(n)?0:n }
  const parsePayload = (p)=>{
    if (Array.isArray(p) && p.length===12) return p.map(toNum)
    if (p && Array.isArray(p.weights) && p.weights.length===12) return p.weights.map(toNum)
    if (Array.isArray(p)){ const out=Array(12).fill(0); for(const r of p){ const m=Number(r?.month ?? r?.m ?? 0); const v=toNum(r?.weight ?? r?.value ?? r?.v ?? 0); if(m>=1&&m<=12) out[m-1]=v } return out }
    if (p && typeof p==='object'){ const out=Array(12).fill(0); const map={apr:1,may:2,jun:3,jul:4,aug:5,sep:6,oct:7,nov:8,dec:9,jan:10,feb:11,mar:12}
      for (const [k,idx] of Object.entries(map)) out[idx-1]=toNum(p[k])
      return out
    }
    return Array(12).fill(1)
  }
  try{
    await ensureCsrf()
    const { data } = await api.get('/api/extra-quota/profit-centers/seasonality', { params:{ profit_center_code: Number(code), fiscal_year: fy } })
    const arr = parsePayload(data)
    if (arr.some(v=>v>0)) return arr
  }catch(_e){}
  return Array(12).fill(1)
}

/* Budget recompute helper */
async function rebuildBudgetFromForm(){
  const amt = Math.max(0, Math.round(num0(opForm.value.volume)))
  const pct = Math.max(0, Math.round(num0(opForm.value.probability_pct)))
  const expected = Math.round(amt * (pct/100))

  const fy = Number(opForm.value.fiscal_year || new Date().getFullYear())
  months.value = fiscalMonths(fy)

  const seasonal12 = await getSeasonalityForPc(Number(opForm.value.profit_center_code), fy)

  const [, mS] = String(opForm.value.estimated_start_date||'').split('-')
  const calStart = Number(mS||'0')
  const startIdx = fiscalIndexFromCalMonth(calStart)
  const indices = []; for (let i=startIdx;i<=12;i++) indices.push(i)

  const w = indices.map(fi => Math.max(0, Number(seasonal12[fi-1]||0)))
  const sumW = w.reduce((a,b)=>a+b,0)

  const newBudget = Array(12).fill(0)
  if (!sumW){
    const base = indices.length ? Math.floor(expected/indices.length) : 0
    let rest = expected - base*indices.length
    indices.forEach(fi => { newBudget[fi-1]=base })
    for (let k=0;k<indices.length && rest>0;k++,rest--) newBudget[indices[k]-1] += 1
  } else {
    const raw  = w.map(val => expected * (val / sumW))
    const base = raw.map(Math.floor)
    let rest   = expected - base.reduce((a,b)=>a+b,0)
    const order = raw.map((v,i)=>({i, frac: v - base[i]})).sort((a,b)=>b.frac - a.frac)
    for (let k=0;k<order.length && rest>0;k++,rest--) base[order[k].i] += 1
    indices.forEach((fi, k) => { newBudget[fi-1] = base[k] })
  }

  budget.value = newBudget
  baseBudget.value = newBudget.slice()
}

/* Scale forecast */
function scaleForecastByRatio(){
  const oldAmt = Math.max(0, Math.round(num0(opBaseline.value.volume)))
  const oldPct = Math.max(0, Math.round(num0(opBaseline.value.probability_pct)))
  const newAmt = Math.max(0, Math.round(num0(opForm.value.volume)))
  const newPct = Math.max(0, Math.round(num0(opForm.value.probability_pct)))
  const oldExp = oldAmt * (oldPct/100)
  const newExp = newAmt * (newPct/100)
  if (oldExp <= 0){
    forecast.value = forecast.value.map((v,i)=> isPastYM(months.value[i]) ? v : budget.value[i])
    return
  }
  const r = newExp / oldExp
  const raw = forecast.value.map(v => v * r)
  const base = raw.map(Math.floor)
  let rest = Math.round(raw.reduce((a,b)=>a+b,0)) - base.reduce((a,b)=>a+b,0)
  const order = raw.map((v,i)=>({i, frac: v - base[i]})).sort((a,b)=>b.frac - a.frac)
  for (let k=0;k<order.length && rest>0;k++,rest--) base[order[k].i] += 1
  forecast.value = base
}

/* Create */
async function onGenerateBudget(){
  if (!canCreateBudget.value) return
  await rebuildBudgetFromForm()
  forecast.value = Array(12).fill(0)
  baseForecast.value = forecast.value.slice()
  showBudgetTable.value = true

  if (createMode.value && !selectedGroupId.value) {
    await ensureCsrf()
    const payload = {
      fiscal_year:          opForm.value.fiscal_year,
      profit_center_code:   Number(opForm.value.profit_center_code),
      volume:               Math.max(0, Math.round(num0(opForm.value.volume))),
      probability_pct:      Math.max(0, Math.round(num0(opForm.value.probability_pct))),
      estimated_start_date: opForm.value.estimated_start_date,
      comments:             opForm.value.comments,
      potential_client_name:opForm.value.potential_client_name,
      client_group_number:  opForm.value.client_group_number,
      status:               opForm.value.status || 'open',
    }
    const { data } = await api.post('/api/extra-quota/opportunities', payload)
    selectedGroupId.value = Number(data?.opportunity_group_id)
    selectedVersion.value = Number(data?.version || 1)
    createMode.value = false
    await saveBudget({ silent:true })
    await loadList()
    toast.add({ severity:'success', summary:'Gespeichert', detail:'Chance erstellt (v1)', life:1400 })
    opBaseline.value = cloneDeep(opForm.value)
  }
}

/* Backend meta/series */
const versionOptions = ref([])
async function loadGroupMeta() {
  if (!selectedGroupId.value) return
  await ensureCsrf()

  suppressStatusWatch.value = true
  try {
    const { data } = await api.get(`/api/extra-quota/opportunities/${selectedGroupId.value}`)
    const latest = data?.latest || {}
    latestMeta.value = latest

    const vers = Array.isArray(data?.versions) ? data.versions.map(v => Number(v.version)).sort((a,b)=>a-b) : []
    versionOptions.value = vers.map(v => ({ value: v, label: `v${v}` }))
    selectedVersion.value = vers.length ? vers[vers.length - 1] : 1

    opForm.value = {
      user_id: latest.user_id ?? null,
      fiscal_year: latest.fiscal_year ?? new Date().getFullYear(),
      profit_center_code: latest.profit_center_code != null ? Number(latest.profit_center_code) : null,
      volume: Math.round(num0(latest.volume)),
      probability_pct: Math.round(num0(latest.probability_pct)),
      estimated_start_date: latest.estimated_start_date ?? null,
      comments: latest.comments ?? '',
      potential_client_name: latest.potential_client_name ?? '',
      client_group_number: latest.client_group_number ?? '',
      status: latest.status || 'open',
    }
    opBaseline.value = cloneDeep(opForm.value)
    opMonthModel.value = opForm.value.estimated_start_date ? new Date(opForm.value.estimated_start_date) : null

    await loadAssignedPcs()
    await updateAvailabilityForPc()
    await loadSeries()
  } finally {
    await nextTick()
    suppressStatusWatch.value = false
  }
}
async function loadSeries() {
  if (!selectedGroupId.value || !selectedVersion.value) { initBlankTable(); return }
  tableLoading.value = true
  try {
    await ensureCsrf()
    const [bRes, fRes] = await Promise.all([
      api.get(`/api/extra-quota/budget/${selectedGroupId.value}/${selectedVersion.value}`,   { params: { fiscal_year: opForm.value.fiscal_year } }),
      api.get(`/api/extra-quota/forecast/${selectedGroupId.value}/${selectedVersion.value}`, { params: { fiscal_year: opForm.value.fiscal_year } })
    ])
    const b = Array.isArray(bRes.data) ? bRes.data : []
    const f = Array.isArray(fRes.data) ? fRes.data : []
    months.value   = b.length ? b.map(r => ym(r.fiscal_year, r.month)) : fiscalMonths(opForm.value.fiscal_year)
    budget.value   = b.length ? b.map(r => Math.round(num0(r.volume))) : Array(12).fill(0)
    forecast.value = f.length ? f.map(r => Math.round(num0(r.volume))) : Array(12).fill(0)
    baseBudget.value = [...budget.value]
    baseForecast.value = [...forecast.value]
  } finally { tableLoading.value = false }
}

/* Edits */
function onEditForecastInt({ index, value }) {
  if (isLocked.value) return
  const n = Math.max(0, Math.round(Number(value) || 0))
  forecast.value[index] = n
}

/* Save flows */
async function saveNewVersion() {
  if (!selectedGroupId.value) return

  const mustRebuild =
    opBaseline.value.volume !== opForm.value.volume ||
    opBaseline.value.probability_pct !== opForm.value.probability_pct ||
    opBaseline.value.estimated_start_date !== opForm.value.estimated_start_date

  if (mustRebuild) {
    await rebuildBudgetFromForm()
    scaleForecastByRatio()
  }

  await ensureCsrf()
  const payload = {
    fiscal_year:           opForm.value.fiscal_year,
    profit_center_code:    Number(opForm.value.profit_center_code),
    volume:                Math.max(0, Math.round(num0(opForm.value.volume))),
    probability_pct:       Math.max(0, Math.round(num0(opForm.value.probability_pct))),
    estimated_start_date:  opForm.value.estimated_start_date,
    comments:              opForm.value.comments,
    potential_client_name: opForm.value.potential_client_name,
    client_group_number:   opForm.value.client_group_number,
    status:                opForm.value.status || 'open'
  }
  const { data } = await api.post(`/api/extra-quota/opportunities/${selectedGroupId.value}/version`, payload)
  selectedVersion.value = Number(data?.version || selectedVersion.value || 1)
  opBaseline.value = cloneDeep(opForm.value)

  await saveBudget({ silent: true })
  if (mustRebuild) await saveForecast({ silent: true })

  toast.add({ severity:'success', summary:'Gespeichert', detail:'Aktualisiert', life:1400 })
  await loadGroupMeta()
}

async function saveBudget(opts = {}) {
  if (!selectedGroupId.value) return
  await ensureCsrf()
  const items = months.value.map((ymStr,i)=>{ const [y,m]=ymStr.split('-').map(n=>parseInt(n,10)); return { month:m, fiscal_year:y, volume: Number(budget.value[i]||0) } })
  await api.post(`/api/extra-quota/budget/${selectedGroupId.value}/${selectedVersion.value}/save`, { items })
  baseBudget.value = [...budget.value]
  if (!opts.silent) toast.add({ severity:'success', summary:'Gespeichert', detail:'Budget gespeichert', life:1400 })
}
async function saveForecast(opts = {}) {
  if (!selectedGroupId.value) return
  await ensureCsrf()
  const items = months.value.map((ymStr,i)=>{ const [y,m]=ymStr.split('-').map(n=>parseInt(n,10)); return { month:m, fiscal_year:y, volume: Number(forecast.value[i]||0) } })
  await api.post(`/api/extra-quota/forecast/${selectedGroupId.value}/${selectedVersion.value}/save`, { items })
  baseForecast.value = [...forecast.value]
  if (!opts.silent) toast.add({ severity:'success', summary:'Gespeichert', detail:'Forecast gespeichert', life:1400 })
}

/* Finalize */
async function finalizeWon() {
  if (!selectedGroupId.value || !selectedVersion.value) return
  finalizing.value = true
  try {
    scaleForecastForWinning()

    const mustVersion =
      opBaseline.value.volume !== opForm.value.volume ||
      100 !== opBaseline.value.probability_pct ||
      opBaseline.value.estimated_start_date !== opForm.value.estimated_start_date

    if (mustVersion) {
      await ensureCsrf()
      const payload = {
        fiscal_year:          opForm.value.fiscal_year,
        profit_center_code:   Number(opForm.value.profit_center_code),
        volume:               Math.max(0, Math.round(Number(opForm.value.volume)||0)),
        probability_pct:      100,
        estimated_start_date: opForm.value.estimated_start_date,
        comments:             opForm.value.comments,
        potential_client_name:opForm.value.potential_client_name,
        client_group_number:  opForm.value.client_group_number,
        status:               opBaseline.value.status || 'open',
      }
      const { data } = await api.post(`/api/extra-quota/opportunities/${selectedGroupId.value}/version`, payload)
      selectedVersion.value = Number(data?.version || selectedVersion.value || 1)
      await saveBudget({ silent:true })
      await saveForecast({ silent:true })
      opBaseline.value = JSON.parse(JSON.stringify({ ...opForm.value, probability_pct: 100 }))
    }

    const cg = String(clientGroupInput.value || '').trim()
    const cn = String(clientNameInput.value  || '').trim()
    if (!cg || !cn) throw new Error('Client Group Number und Name sind erforderlich')

    await ensureCsrf()
    await api.post(
      `/api/extra-quota/opportunities/${selectedGroupId.value}/${selectedVersion.value}/finalize`,
      { status: 'won', client_group_number: cg, client_name: cn }
    )

    suppressStatusWatch.value = true
    opForm.value.status = 'won'
    await nextTick()
    suppressStatusWatch.value = false

    wonDialogVisible.value = false
    clientGroupInput.value = ''
    clientNameInput.value = ''
    opBaseline.value = cloneDeep(opForm.value)
    baseBudget.value = [...budget.value]
    baseForecast.value = [...forecast.value]

    toast.add({ severity:'success', summary:'Überführt', detail:'In Stamm-Budget übernommen', life:1600 })
    await loadList()
    selectedGroupId.value = null
    selectedVersion.value = null
    enterCreateMode()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Fehler beim Finalisieren (won)'
    toast.add({ severity:'error', summary:'Fehler', detail: msg, life:2200 })
  } finally {
    finalizing.value = false
  }
}
function scaleForecastForWinning(){
  const oldAmt = Math.max(0, Math.round(num0(opBaseline.value?.volume ?? opForm.value.volume)))
  const oldPct = Math.max(0, Math.round(num0(opBaseline.value?.probability_pct ?? 0)))
  const newAmt = Math.max(0, Math.round(num0(opForm.value.volume)))
  const newPct = 100

  const oldExp = oldAmt * (oldPct/100)
  const newExp = newAmt * (newPct/100)

  if (oldExp <= 0) {
    forecast.value = forecast.value.map((v,i)=> isPastYM(months.value[i]) ? v : budget.value[i])
    return
  }

  const r = newExp / oldExp
  const raw = forecast.value.map((v,i)=> isPastYM(months.value[i]) ? v : v * r)
  const base = raw.map(v => Math.floor(v))
  let rest = Math.round(raw.reduce((a,b)=>a+b,0)) - base.reduce((a,b)=>a+b,0)
  const order = raw.map((v,i)=>({i, frac: v - base[i]}))
                   .filter(o => !isPastYM(months.value[o.i]))
                   .sort((a,b)=> b.frac - a.frac)
  for (let k=0; k<order.length && rest>0; k++, rest--) {
    base[order[k].i] += 1
  }
  forecast.value = base
}

async function finalizeLost() {
  if (!selectedGroupId.value || !selectedVersion.value) return
  try {
    const mustVersion =
      opBaseline.value.volume !== opForm.value.volume ||
      opBaseline.value.probability_pct !== opForm.value.probability_pct ||
      opBaseline.value.estimated_start_date !== opForm.value.estimated_start_date

    if (mustVersion) {
      await rebuildBudgetFromForm()
      await ensureCsrf()
      const payload = {
        fiscal_year:          opForm.value.fiscal_year,
        profit_center_code:   Number(opForm.value.profit_center_code),
        volume:               Math.max(0, Math.round(Number(opForm.value.volume)||0)),
        probability_pct:      Math.max(0, Math.round(Number(opForm.value.probability_pct)||0)),
        estimated_start_date: opForm.value.estimated_start_date,
        comments:             opForm.value.comments,
        potential_client_name: opForm.value.potential_client_name,
        client_group_number:  opForm.value.client_group_number,
        status:               opBaseline.value.status || 'open',
      }
      const { data } = await api.post(`/api/extra-quota/opportunities/${selectedGroupId.value}/version`, payload)
      selectedVersion.value = Number(data?.version || selectedVersion.value || 1)
      await saveBudget({ silent:true })
      if (changedForecastCount.value>0) await saveForecast({ silent:true })
      opBaseline.value = JSON.parse(JSON.stringify(opForm.value))
    }

    await ensureCsrf()
    await api.post(
      `/api/extra-quota/opportunities/${selectedGroupId.value}/${selectedVersion.value}/finalize`,
      { status: 'lost' }
    )

    suppressStatusWatch.value = true
    opForm.value.status = 'lost'
    await nextTick()
    suppressStatusWatch.value = false

    opBaseline.value = cloneDeep(opForm.value)
    baseBudget.value = [...budget.value]
    baseForecast.value = [...forecast.value]

    toast.add({ severity:'success', summary:'Geschlossen', detail:'Menge freigegeben', life:1400 })
    await loadList()
    selectedGroupId.value = null
    selectedVersion.value = null
    enterCreateMode()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Fehler beim Finalisieren (lost)'
    toast.add({ severity:'error', summary:'Fehler', detail: msg, life:2200 })
  }
}

/* Guards */
function dirtyAny(){
  const active = !!selectedGroupId.value || !!createMode.value
  if (!active) return false
  if (isLocked.value) return false
  return opDirty.value || changedBudgetCount.value>0 || changedForecastCount.value>0
}
async function saveAndApply(){
  try{
    if (selectedGroupId.value && opDirty.value) await saveNewVersion()
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
    loadGroupMeta()
    return
  }
  if (kind==='version'){
    selectedVersion.value = Number(value) || null
    loadSeries().then(()=>{ showBudgetTable.value = true })
    return
  }
}

/* Reacciones */
watch(() => opForm.value.profit_center_code, () => { updateAvailabilityForPc() })

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
.status-filter{ display:flex; gap:6px; }
.dark-list :deep(.p-listbox-list){ background:transparent; }
.row-item{ display:flex; flex-direction:column; gap:2px; padding:6px; border-radius:8px; width :100%; cursor:pointer; }
.row-item:hover{ background: rgba(255,255,255,0.06); }
.row-item .top{ display:flex; justify-content:space-between; color:#cbd5e1; font-size:12px; }
.row-item .mid{ color:#e5e7eb; font-weight:600; }
.row-item .bot{ display:flex; justify-content:space-between; color:#cbd5e1; font-size:12px; }
.dark-list :deep(.meta){ color:#a3a3a3; }

/* FORM */
.form-card-body{ display:flex; flex-direction:column; gap:10px; height:100%; overflow:hidden; }
.form-toprow{
  display:grid; grid-template-columns: minmax(0,2fr) minmax(180px,1fr);
  gap:10px; align-items:start;
  padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.08);
}
.top-cell{ display:flex; flex-direction:column; gap:6px; }
.inline-input{ display:flex; align-items:center; gap:8px; }
:deep(.p-dropdown){ height: 2.5rem; }

.form-two-col{
  display:grid; grid-template-columns: 30% 1fr; gap:12px; flex:1 1 auto; min-height:0;
  padding-top:8px;
}
.left-col{ display:flex; flex-direction:column; gap:8px; overflow:hidden; }
.right-col{ display:grid; grid-template-rows: 1fr auto; gap:8px; min-height:0; }
.right-top{ min-height:0; overflow:hidden; }
.right-bottom{ align-self:end; }
.lbl{ color:#cbd5e1; font-weight:600; }
.comment-box{ min-height:180px; }

/* volume inline */
.vol-inline{ display:flex; align-items:center; gap:8px; }
.assigned{ color:#cbd5e1; white-space:nowrap; }

/* Probability ticks */
.prob-wrap{ display:flex; align-items:center; gap:8px; }
.tickbar{ width:100%; height:6px; margin-top:4px; position:relative; }
.tickbar::before{
  content:''; position:absolute; inset:0;
  background-image:repeating-linear-gradient(to right,
    rgba(255,255,255,0.35) 0, rgba(255,255,255,0.35) 1px,
    transparent 1px, transparent 10%);
  opacity:.8;
}

/* EXTRAS */
.extras-wrap{ display:flex; flex-direction:column; height:100%; }
.extras-wrap > *:first-child{ flex:1 1 auto; }
.extras-bottom{ display:flex; justify-content:space-between; gap:10px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.08); }

/* TABLE */
.ctbl-wrap :deep(table){ table-layout: fixed; width: 100%; }
.ctbl-wrap :deep(th), .ctbl-wrap :deep(td){ width:auto; }
/* ocultar filas de IST e IST/Budget (1ra y 4ta) */
.ctbl-wrap :deep(tbody > tr:nth-child(1)),
.ctbl-wrap :deep(tbody > tr:nth-child(4)){ display:none !important; }
/* bloqueo cuando cerrada */
.ctbl-wrap.locked{ pointer-events:none; opacity:.6; }

/* Loader */
.local-loader{ position: fixed; inset: 0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; z-index:50; }
.dots{ display:flex; gap:10px; align-items:center; justify-content:center; }
.dot{ width:10px; height:10px; border-radius:50%; opacity:0.9; animation:bounce 1s infinite ease-in-out; box-shadow:0 2px 6px rgba(0,0,0,0.25); }
.dot.g{ background:#22c55e; animation-delay:0s; } .dot.r{ background:#ef4444; animation-delay:.15s; } .dot.b{ background:#3b82f6; animation-delay:.3s; }
@keyframes bounce{ 0%,80%,100%{ transform:translateY(0) scale(1); opacity:.8 } 40%{ transform:translateY(-8px) scale(1.05); opacity:1 } }
.caption{ font-size:.9rem; color:#e5e7eb; opacity:.9; }
</style>
