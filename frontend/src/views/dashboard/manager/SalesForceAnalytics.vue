<!-- src/views/SalesManagerProDashboardPrime.vue (refactor AWS glow + GSAP + theming + utilities) -->
<template>
  <div class="view-wrap full-bleed" ref="root">
    <div class="layout-row">
      <!-- LEFT 2/12 -->
      <aside class="left-col">
        <div class="glass card-shadow panel aws-glow-card">
          <div class="panel-head">
            <h2>AD-Feclips</h2>
          </div>

          <Listbox
            v-model="selectedSeller"
            :options="sellerItems"
            class="seller-listbox"
          >
            <template #option="{ option }">
              <div class="seller-item">
                <div :class="['avatar-ring', ringClass(option.teamId)]">
                  <Avatar v-if="option.photo" :image="option.photo" class="avatar-img" shape="circle" />
                  <Avatar v-else :label="initials(option.name)" class="avatar-initials" shape="circle" />
                </div>
                <div class="seller-name">{{ option.displayName }}</div>
              </div>
            </template>
            <template #empty>
              <div class="empty">Keine Einträge.</div>
            </template>
          </Listbox>
        </div>
      </aside>

      <!-- RIGHT 10/12 -->
      <section class="right-col">
        <!-- Controls (full width) -->
        <div class="glass card-shadow controls aws-glow-card">
          <div class="controls-left">
            <SelectButton v-model="dataType" :options="typeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
            <SelectButton v-model="selectedPeriod" :options="periodOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
          </div>
          <div class="controls-right">
            <Button icon="pi pi-chevron-left" class="p-button-rounded p-button-text" @click="shiftPeriod(-1)" :disabled="dataType==='forecast' && periodOffset<=-12" />
            <span class="pill"><i class="pi pi-calendar mr-2" />{{ periodLabel }}</span>
            <Button icon="pi pi-chevron-right" class="p-button-rounded p-button-text" @click="shiftPeriod(1)" :disabled="dataType==='forecast' && periodOffset>=12" />
          </div>
        </div>

        <!-- ROW 1: Abweichungen + Profitcenter -->
        <div class="row">
          <div class="col half">
            <div class="glass card-shadow panel aws-glow-card">
              <div class="panel-head">
                <h3>Abweichungsbegründungen</h3>
                <div class="kpis">
                  <Tag severity="success" value="Fristgerecht" rounded class="mr-2" />
                  <strong>{{ kpiJust.inTerm }}</strong>
                  <span class="sep">|</span>
                  <Tag severity="warning" value="Verspätet" rounded class="mx-2" />
                  <strong>{{ kpiJust.outTerm }}</strong>
                </div>
              </div>

              <template v-if="dataType==='ventas'">
                <DataTable
                  :value="visibleJustifications"
                  size="small"
                  responsiveLayout="scroll"
                  :rows="10"
                  :paginator="true"
                  :rowsPerPageOptions="[10,20,50]"
                  paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                  currentPageReportTemplate="{first}–{last} von {totalRecords}"
                  class="no-bg-table"
                >
                  <Column field="date" header="Datum" :body="dateBody" style="width:120px" />
                  <Column field="client" header="Kunde" style="width:160px" />
                  <Column field="reason" header="Grund" :body="reasonBody" />
                  <Column header="Status" :body="statusBody" style="width:140px" />
                  <Column header="Frist" :body="termBody" style="width:140px" />
                  <template #empty><div class="empty">Keine Daten für den Zeitraum.</div></template>
                </DataTable>
              </template>
              <template v-else><div class="empty p-3">Dieser Bereich gilt für <strong>Vertrieb</strong>.</div></template>
            </div>
          </div>

          <div class="col half">
            <div class="glass card-shadow panel aws-glow-card">
              <div class="panel-head">
                <h3>Profitcenter</h3>
                <div class="breadcrumbs" v-if="selectedPC || selectedClient">
                  <Button class="p-button-text p-button-sm" @click="resetPC"><i class="pi pi-home mr-2" />{{ selectedSeller?.displayName || '—' }}</Button>
                  <i class="pi pi-angle-right mx-2" v-if="selectedPC" />
                  <Button v-if="selectedPC" class="p-button-text p-button-sm" @click="resetClient">{{ selectedPC.name }}</Button>
                  <i class="pi pi-angle-right mx-2" v-if="selectedClient" />
                  <span v-if="selectedClient" class="crumb-current">{{ selectedClient.name }}</span>
                </div>
              </div>

              <div v-if="!selectedPC" class="bars">
                <div v-for="pc in pcsAgg" :key="pc.id" class="bar-row" :title="pcTitle(pc)" @click="selectPC(pc)">
                  <div class="bar-label"><span class="dot" :style="{ background: pc.color }" />{{ pc.name }}</div>
                  <div class="bar-track"><div class="bar-fill" :style="{ width: pc.achievedPct+'%', background: pc.color }" /></div>
                  <div class="bar-val">{{ pc.achievedPct }}%</div>
                </div>
                <div v-if="!pcsAgg.length" class="empty">Keine Profitcenter vorhanden.</div>
              </div>

              <div v-else-if="!selectedClient">
                <div class="subhead"><strong>Kunden von {{ selectedPC.name }}</strong></div>
                <DataTable :value="selectedPC.clients" size="small" responsiveLayout="scroll" class="no-bg-table">
                  <Column field="name" header="Kunde" />
                  <Column field="sales" header="Umsatz" :body="currencyBody('sales')" style="width:160px" />
                  <Column field="target" header="Ziel" :body="currencyBody('target')" style="width:160px" />
                  <Column header="Abweichung" :body="deltaBody" style="width:160px" />
                  <Column header="" :body="clientActionBody" style="width:120px" />
                </DataTable>
              </div>

              <div v-else class="client-detail">
                <div class="subhead"><strong>{{ selectedClient.name }}</strong> — Detail</div>
                <ul class="bullets">
                  <li><i class="pi pi-dollar mr-2" />Umsatz: <strong>{{ fmtCurrency(selectedClient.sales) }}</strong></li>
                  <li><i class="pi pi-bullseye mr-2" />Ziel: <strong>{{ fmtCurrency(selectedClient.target) }}</strong></li>
                  <li><i class="pi pi-chart-line mr-2" />Trend (3M): <strong>{{ trendLabel(selectedClient.trend3m) }}</strong></li>
                  <li><i class="pi pi-exclamation-triangle mr-2" />Abweichungen: <strong>{{ selectedClient.deviations }}</strong></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- ROW 2/3: left tall (quota), right stacked (just-summary + plans) -->
        <div class="row">
          <div class="col half">
            <div class="glass card-shadow panel tall aws-glow-card" @click="openExtraQuota" ref="extraQuotaCard">
              <div class="kpi-head">
                <h4>Zusatzquote</h4>
                <Tag :value="extraQuotaPct + '%'"  rounded />
              </div>
              <ProgressBar :value="extraQuotaPct" />
              <div class="kpi-foot">Klicken für Analyse</div>
            </div>
          </div>

          <div class="col half">
            <div class="stack-col">
              <div class="glass card-shadow panel aws-glow-card">
                <div class="kpi-head"><h4>Begründungen</h4></div>
                <div class="pairs">
                  <div class="pair"><span class="label"><i class="pi pi-check-circle text-success mr-2" />Fristgerecht</span><span class="value">{{ kpiJust.inTerm }}</span></div>
                  <div class="pair"><span class="label"><i class="pi pi-clock text-warn mr-2" />Verspätet</span><span class="value">{{ kpiJust.outTerm }}</span></div>
                </div>
              </div>

              <div class="glass card-shadow panel aws-glow-card">
                <div class="kpi-head"><h4>Aktionspläne</h4></div>
                <div class="pairs">
                  <div class="pair"><span class="label"><i class="pi pi-check-circle text-success mr-2" />Erfüllt</span><span class="value">{{ kpiPlans.done }}</span></div>
                  <div class="pair"><span class="label"><i class="pi pi-times-circle text-danger mr-2" />Nicht erfüllt</span><span class="value">{{ kpiPlans.notDone }}</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Dialog -->
        <Dialog v-model:visible="showExtraQuota" modal :style="{ width: 'min(1100px, 95vw)' }" :breakpoints="{ '960px': '95vw', '640px': '100vw' }"
                header="Analyse der Zusatzquote" class="glass-modal">
          <div class="dialog-subtitle">Verkäufer: <strong>{{ selectedSeller?.displayName }}</strong></div>
          <TabView>
            <TabPanel header="Übersicht">
              <div class="modal-row">
                <div class="glass card-shadow inner-card aws-glow-card">
                  <div class="inner-head"><h5>Fortschritt</h5><Tag :value="extraQuotaPct + '%'"  rounded /></div>
                  <ProgressBar :value="extraQuotaPct" class="mb-3" />
                  <ul class="bullets tight">
                    <li><i class="pi pi-bullseye mr-2" />Zusatz-Ziel: <strong>{{ fmtCurrency(extraQuota.meta) }}</strong></li>
                    <li><i class="pi pi-dollar mr-2" />Erreicht: <strong>{{ fmtCurrency(extraQuota.reached) }}</strong></li>
                    <li><i class="pi pi-minus-circle mr-2" />Gap: <strong>{{ fmtCurrency(extraQuota.meta - extraQuota.reached) }}</strong></li>
                  </ul>
                </div>
                <div class="glass card-shadow inner-card aws-glow-card">
                  <div class="inner-head"><h5>KPIs</h5></div>
                  <ul class="bullets tight">
                    <li><i class="pi pi-plus-circle mr-2" />Erstellt: <strong>{{ kpiOpp.created }}</strong></li>
                    <li><i class="pi pi-check-circle mr-2" />Gewonnen: <strong>{{ kpiOpp.closed }}</strong></li>
                    <li><i class="pi pi-refresh mr-2" />In Arbeit: <strong>{{ kpiOpp.inProgress }}</strong></li>
                  </ul>
                </div>
              </div>
            </TabPanel>
            <TabPanel header="Chancen">
              <DataTable :value="opportunities" size="small" responsiveLayout="scroll" :rows="10" :paginator="true" class="no-bg-table">
                <Column field="title" header="Chance" />
                <Column field="client" header="Kunde" />
                <Column field="status" header="Status" :body="oppStatusBody" style="width:160px" />
                <Column field="amount" header="Betrag" :body="currencyBody('amount')" style="width:160px" />
                <Column field="date" header="Datum" :body="dateBody" style="width:140px" />
              </DataTable>
            </TabPanel>
          </TabView>
          <template #footer><Button label="Schließen" icon="pi pi-check" @click="showExtraQuota = false" /></template>
        </Dialog>
      </section>
    </div>
  </div>
</template>

<script setup>
/* English variable/function names; UI strings in German */
import { ref, computed, onMounted, nextTick } from 'vue'
import Button from 'primevue/button'
import Listbox from 'primevue/listbox'
import SelectButton from 'primevue/selectbutton'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Avatar from 'primevue/avatar'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import gsap from 'gsap'

/* ===== generic app state ===== */
const root = ref(null)
const sellers = ref([])
const selectedSeller = ref(null)

const dataType = ref('ventas')
const selectedPeriod = ref('last_month')
const periodOffset = ref(0)

const selectedPC = ref(null)
const selectedClient = ref(null)

const showExtraQuota = ref(false)
const extraQuotaCard = ref(null)

/* options */
const typeOptions = [
  { label: 'Vertrieb', value: 'ventas' },
  { label: 'Forecast', value: 'forecast' },
]

/* list formatting */
function splitName(full = '') {
  const parts = full.trim().split(/\s+/)
  if (parts.length === 1) return { first: parts[0], last: '' }
  const last = parts.pop()
  return { first: parts.join(' '), last }
}
function displayName(full = '') {
  const { first, last } = splitName(full)
  return last ? `${last}, ${first}` : first
}
const sellerItems = computed(() => {
  const arr = (sellers.value || []).map(s => ({
    ...s,
    displayName: displayName(s.name),
  }))
  arr.sort((a, b) => a.displayName.localeCompare(b.displayName, 'de'))
  return arr
})

/* computed */
const pcsAgg = computed(() => selectedSeller.value?.profitCenters ?? [])

const periodOptions = computed(() =>
  dataType.value === 'ventas'
    ? [{ label: 'Letzter Monat', value: 'last_month' }, { label: 'YTD bis letzten Monat', value: 'ytd_until_last' }]
    : [{ label: 'Nächste 6 Monate (ohne aktuellen)', value: 'next_6' }],
)

const justificationsFiltered = computed(() => {
  if (dataType.value !== 'ventas') return []
  const all = selectedSeller.value?.justifications ?? []
  if (selectedPeriod.value === 'last_month') {
    const [s, e] = monthRangeFromOffset(-1 + periodOffset.value)
    return all.filter((a) => inRange(a.date, s, e))
  }
  if (selectedPeriod.value === 'ytd_until_last') {
    const [s, e] = ytdUntilLastWithOffset(periodOffset.value)
    return all.filter((a) => inRange(a.date, s, e))
  }
  return all
})
const visibleJustifications = computed(() => justificationsFiltered.value)

const kpiJust = computed(() => {
  const arr = justificationsFiltered.value
  return { inTerm: arr.filter((a) => a.inTerm).length, outTerm: arr.filter((a) => !a.inTerm).length }
})

const kpiPlans = computed(() => selectedSeller.value?.plans ?? { done: 0, notDone: 0 })

const extraQuota = computed(() => selectedSeller.value?.extraQuota ?? { meta: 0, reached: 0 })
const extraQuotaPct = computed(() => {
  const { meta, reached } = extraQuota.value
  return meta > 0 ? Math.min(100, Math.round((reached / meta) * 100)) : 0
})

const opportunities = computed(() => selectedSeller.value?.opportunities ?? [])
const kpiOpp = computed(() => {
  const arr = opportunities.value
  return {
    created: arr.filter((o) => o.status === 'Erstellt').length,
    closed: arr.filter((o) => o.status === 'Gewonnen').length,
    inProgress: arr.filter((o) => o.status === 'In Arbeit').length,
  }
})

const periodLabel = computed(() => {
  if (dataType.value === 'ventas') {
    if (selectedPeriod.value === 'last_month') {
      const [s] = monthRangeFromOffset(-1 + periodOffset.value)
      return `${labelMonth(s)} ${s.getFullYear()}`
    } else {
      const [s, e] = ytdUntilLastWithOffset(periodOffset.value)
      return `YTD: ${labelMonth(new Date(s.getFullYear(), 0, 1))} ${s.getFullYear()} – ${labelMonth(e)} ${e.getFullYear()}`
    }
  } else {
    const [s, e] = next6MonthsWindow(periodOffset.value)
    return `${labelMonth(s)} ${s.getFullYear()} → ${labelMonth(e)} ${e.getFullYear()}`
  }
})

/* actions */
function selectPC(pc) { selectedPC.value = pc; selectedClient.value = null }
function resetPC() { selectedPC.value = null; selectedClient.value = null }
function resetClient() { selectedClient.value = null }
function openExtraQuota() {
  showExtraQuota.value = true
  // spotlight pulse to dirigir la atención
  if (extraQuotaCard.value) {
    gsap.fromTo(extraQuotaCard.value, { '--glow-opacity': 0.3 }, { '--glow-opacity': 1, duration: 0.12, ease: 'power2.out' })
    gsap.to(extraQuotaCard.value, { '--glow-opacity': 0.55, duration: 0.6, ease: 'power2.out', delay: 0.12 })
  }
}
function shiftPeriod(d) { periodOffset.value += d }

/* avatar helpers */
function ringClass(teamId) { return teamId === 1 ? 'ring-alpha' : teamId === 2 ? 'ring-beta' : 'ring-neutral' }
function initials(name = '') { return name.split(' ').filter(Boolean).map(w => w[0]?.toUpperCase()).slice(0, 2).join('') }

/* table body templates (keep simple for PrimeVue) */
function dateBody(row) { return fmtDate(row.date) }
function reasonBody(row) { return `<span title="${escapeHtml(row.reason)}">${escapeHtml(row.reason)}</span>` }
function statusBody(row) { const sev = row.justified ? 'success' : 'warning'; const txt = row.justified ? 'Begründet' : 'Offen'; return `<span class=\"p-tag p-tag-${sev} p-tag-rounded\">${txt}</span>` }
function termBody(row) { const sev = row.inTerm ? 'success' : 'danger'; const txt = row.inTerm ? 'Fristgerecht' : 'Verspätet'; return `<span class=\"p-tag p-tag-${sev} p-tag-rounded\">${txt}</span>` }
function currencyBody(field) { return (row) => fmtCurrency(row[field]) }
function deltaBody(row) { const d = row.sales - row.target; const cls = d >= 0 ? 'pos' : 'neg'; return `<span class=\"${cls}\">${fmtCurrency(d)}</span>` }
function clientActionBody() { return `<button class=\"p-button p-button-text p-button-sm link-btn\">Ansehen</button>` }
function oppStatusBody(row) { const map = { Gewonnen: 'success', 'In Arbeit': 'warning', Erstellt: 'info' }; const sev = map[row.status] || 'secondary'; return `<span class=\"p-tag p-tag-${sev} p-tag-rounded\">${row.status}</span>` }

/* formatting helpers */
function fmtCurrency(n) { return Number(n || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }) }
function fmtDate(iso) { const d = new Date(iso); return isNaN(d) ? '—' : d.toLocaleDateString('de-DE') }
function trendLabel(v) { return v > 0 ? 'Aufwärts' : v < 0 ? 'Abwärts' : 'Stabil' }
function inRange(iso, s, e) { const d = new Date(iso); return !isNaN(d) && d >= s && d <= e }
function labelMonth(d) { return d.toLocaleString('de-DE', { month: 'long' }).replace(/^./, m => m.toUpperCase()) }
function monthRangeFromOffset(rel) { const now = new Date(); const y = now.getFullYear(); const m = now.getMonth() + rel; const s = new Date(y, m, 1); const e = new Date(y, m + 1, 0, 23, 59, 59, 999); return [s, e] }
function ytdUntilLastWithOffset(off) { const [, e] = monthRangeFromOffset(-1 + off); const s = new Date(e.getFullYear(), 0, 1); const eAdj = new Date(e.getFullYear(), e.getMonth() + 1, 0, 23, 59, 59, 999); return [s, eAdj] }
function next6MonthsWindow(off) { const now = new Date(); const s = new Date(now.getFullYear(), now.getMonth() + 1 + off, 1); const e = new Date(s.getFullYear(), s.getMonth() + 6, 0, 23, 59, 59, 999); return [s, e] }
function pcTitle(pc) { return `${pc.name}: ${pc.achievedPct}%` }
function escapeHtml(s = '') { return String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m])) }

/* ===== AWS-like glow + reveal (GSAP) ===== */
function revealStagger() {
  // generic reveal for any card/control
  const els = root.value?.querySelectorAll('.aws-glow-card, .controls') || []
  if (!els.length) return
  gsap.from(els, {
    opacity: 0,
    y: 18,
    duration: 0.6,
    ease: 'power2.out',
    stagger: 0.06,
  })
  // hover spotlight: animate CSS var --glow-opacity
  els.forEach((el) => {
    el.style.setProperty('--glow-opacity', '0.30')
    el.addEventListener('mouseenter', () => gsap.to(el, { '--glow-opacity': 0.95, duration: 0.25, ease: 'power2.out' }))
    el.addEventListener('mouseleave', () => gsap.to(el, { '--glow-opacity': 0.45, duration: 0.25, ease: 'power2.out' }))
  })
}

/* seed (Team 1/2/3) */
onMounted(() => {
  sellers.value = [
    {
      id: 1,
      name: 'Johann Meier',
      teamId: 1,            // HostPow (verde→azul)
      photo: '',
      profitCenters: [
        { id:'pc-1', name:'PC Nord 1', color:'#4f46e5', achievedPct:78,
          clients:[{id:'c1',name:'Kunde A',sales:120000,target:150000,trend3m:1,deviations:1},{id:'c2',name:'Kunde B',sales:90000,target:80000,trend3m:1,deviations:0}] },
        { id:'pc-2', name:'PC Nord 2', color:'#06b6d4', achievedPct:92,
          clients:[{id:'c3',name:'Kunde C',sales:60000,target:70000,trend3m:-1,deviations:2},{id:'c4',name:'Kunde D',sales:30000,target:40000,trend3m:0,deviations:0}] },
      ],
      justifications:[
        {id:'a1',date:'2025-08-28',client:'Kunde A',reason:'Lieferengpass',justified:true,inTerm:true},
        {id:'a2',date:'2025-08-30',client:'Kunde C',reason:'Logistikverzug',justified:true,inTerm:false},
        {id:'a3',date:'2025-09-05',client:'Kunde B',reason:'Geänderte Konditionen',justified:false,inTerm:true},
      ],
      extraQuota:{meta:200000,reached:154000},
      opportunities:[
        {id:'o1',title:'Upsell Linie X',client:'Kunde A',status:'Erstellt',amount:25000,date:'2025-09-02'},
        {id:'o2',title:'Neukunde',client:'Kunde E',status:'Gewonnen',amount:40000,date:'2025-08-22'},
        {id:'o3',title:'Verlängerung',client:'Kunde B',status:'In Arbeit',amount:30000,date:'2025-09-12'},
      ],
      plans:{done:5,notDone:2}
    },
    {
      id: 2,
      name: 'Lena Fischer',
      teamId: 2,            // House Technic (naranja→rojo)
      photo: '',
      profitCenters: [
        { id:'pc-3', name:'PC Süd 1', color:'#16a34a', achievedPct:88,
          clients:[{id:'c5',name:'Kunde F',sales:110000,target:100000,trend3m:1,deviations:0},{id:'c6',name:'Kunde G',sales:50000,target:80000,trend3m:-1,deviations:1}] },
      ],
      justifications:[{id:'a4',date:'2025-08-18',client:'Kunde F',reason:'Teil-Storno',justified:true,inTerm:true}],
      extraQuota:{meta:150000,reached:145000},
      opportunities:[{id:'o4',title:'Cross-Sell Z',client:'Kunde F',status:'Gewonnen',amount:15000,date:'2025-09-01'}],
      plans:{done:3,notDone:1}
    },
    {
      id: 3,
      name: 'Gerente House Light',
      teamId: 3,            // fallback: neutral
      photo: '',
      profitCenters: [],
      justifications: [],
      extraQuota:{meta:0,reached:0},
      opportunities: [],
      plans:{done:0,notDone:0}
    }
  ]
  selectedSeller.value = sellerItems.value[0] || null

  nextTick(() => revealStagger())
})
</script>

<style>
/* =====================
   GLOBAL THEME TOKENS
   (auto Light/Dark + [data-theme])
   ===================== */
:root{
  /* radii, spacing, shadows */
  --radius-lg: 14px;
  --shadow-card: 0 18px 50px rgba(0,0,0,.20);
  --shadow-card-dark: 0 24px 64px rgba(0,0,0,.66);
  --space-1: 6px; --space-2: 10px; --space-3: 12px; --space-4: 16px;

  /* text & surfaces */
  --text-1: #111827; /* slate-900 */
  --text-2: #6b7280; /* slate-500 */
  --surface: rgba(255,255,255,.55);
  --surface-border: rgba(17,24,39,.10);
  --pill-bg: rgba(17,24,39,.06);

  /* brand gradients (rings & glow) */
  --ring-alpha: linear-gradient(135deg,#22c55e,#3b82f6);      /* verde→azul */
  --ring-beta:  linear-gradient(135deg,#f97316,#ef4444);      /* naranja→rojo */
  --ring-neutral: linear-gradient(135deg,#64748b,#94a3b8);

  /* AWS-like glow layers */
  --gl-1: radial-gradient(220px 160px at 18% 14%, rgba(59,130,246,.35), transparent 60%);
  --gl-2: radial-gradient(260px 190px at 82% 86%, rgba(34,197,94,.35), transparent 60%);
  --gl-3: radial-gradient(280px 210px at 58% 42%, rgba(249,115,22,.33), transparent 60%);
}
@media (prefers-color-scheme:dark){
  :root{
    --text-1:#e5e7eb; --text-2:#9ca3af;
    --surface: rgba(0,0,0,.38);
    --surface-border: rgba(255,255,255,.18);
    --pill-bg: rgba(255,255,255,.06);
  }
}
[data-theme="dark"]{ --text-1:#e5e7eb; --text-2:#9ca3af; --surface: rgba(0,0,0,.38); --surface-border: rgba(255,255,255,.18); --pill-bg: rgba(255,255,255,.06); }
[data-theme="light"]{ --text-1:#111827; --text-2:#6b7280; --surface: rgba(255,255,255,.55); --surface-border: rgba(17,24,39,.10); --pill-bg: rgba(17,24,39,.06); }

/* =====================
   BASE LAYOUT (Flex)
   ===================== */
.full-bleed{ width:100vw; margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw); }
.view-wrap{ color:var(--text-1); min-height:calc(100vh - 80px); padding:12px 24px 28px; box-sizing:border-box; }
.layout-row{ display:flex; gap:16px; width:100%; }
.left-col{ flex:0 0 16.6667%; }
.right-col{ flex:1 1 83.3333%; }
.panel{ padding:10px; border-radius:var(--radius-lg); }

.row{ display:flex; gap:16px; width:100%; }
.col.half{ flex:0 0 50%; display:flex; }
.stack-col{ display:flex; flex-direction:column; gap:16px; height:100%; }
.tall{ min-height:300px; }

/* =====================
   GLASS & SHADOW
   ===================== */
.glass{ background:var(--surface); border:1px solid var(--surface-border); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); }
.card-shadow{ box-shadow:var(--shadow-card); }
@media (prefers-color-scheme:dark){ .card-shadow{ box-shadow:var(--shadow-card-dark); } }

/* =====================
   AWS-LIKE BEHIND-CARD GLOW
   (driven by --glow-opacity; animated via GSAP)
   ===================== */
.aws-glow-card{ position:relative; isolation:isolate; }
.aws-glow-card::after{
  content:''; position:absolute; z-index:-1; inset:-10px; border-radius:calc(var(--radius-lg) + 4px);
  background: var(--gl-1), var(--gl-2), var(--gl-3);
  filter: blur(26px);
  opacity: var(--glow-opacity, .45);
  transition: opacity .25s ease;
  pointer-events:none;
}
.aws-glow-card:hover::after{ opacity: .95; }

/* =====================
   SELLER LISTBOX & AVATAR RINGS
   ===================== */
.panel-head{ display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid rgba(0,0,0,.08); }
.panel-head h2{ margin:0; font-size:1.05rem; font-weight:800; }
.seller-listbox{ border:none; background:transparent; }
.seller-listbox :deep(.p-listbox-list-wrapper){ max-height:calc(100vh - 240px)!important; overflow:auto; }
.seller-item{ display:flex; align-items:center; gap:10px; padding:8px; border-radius:10px; }
.seller-name{ font-weight:800; }

.avatar-ring{ width:40px; height:40px; border-radius:999px; padding:2px; display:flex; align-items:center; justify-content:center; }
.ring-alpha{ background: var(--ring-alpha); }
.ring-beta{ background: var(--ring-beta); }
.ring-neutral{ background: var(--ring-neutral); }
.avatar-img{ width:100%; height:100%; border-radius:999px; background:#111; }
.avatar-initials{ width:100%; height:100%; border-radius:999px; color:#fff; font-weight:900; background:transparent; }

/* =====================
   CONTROLS
   ===================== */
.controls{ display:flex; align-items:center; justify-content:space-between; padding:10px 12px; gap:12px; }
.controls-left,.controls-right{ display:flex; align-items:center; gap:10px; }
.pill{ display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; background:var(--pill-bg); font-weight:800; }
.mr-2{ margin-right:.5rem; } .mx-2{ margin:0 .5rem; } .mb-3{ margin-bottom:1rem; }

/* =====================
   TABLES / BARS
   ===================== */
.no-bg-table :deep(.p-datatable-wrapper), .no-bg-table :deep(.p-datatable-table){ background:transparent; }
.bars{ display:flex; flex-direction:column; gap:12px; padding:10px; }
.bar-row{ display:flex; align-items:center; gap:10px; cursor:pointer; }
.bar-label{ display:inline-flex; align-items:center; gap:8px; font-weight:700; flex:0 0 30%; }
.dot{ width:10px; height:10px; border-radius:50%; }
.bar-track{ flex:1 1 auto; height:12px; border-radius:999px; background:rgba(0,0,0,.06); overflow:hidden; }
.bar-fill{ height:100%; border-radius:999px; transition:width .35s ease; }
.bar-val{ width:56px; text-align:right; font-weight:800; opacity:.8; }
.subhead{ padding:8px 12px; font-weight:700; }

/* =====================
   KPI BLOCKS
   ===================== */
.kpi-head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.kpi-foot{ font-size:.85rem; opacity:.75; margin-top:8px; }
.pairs{ display:flex; flex-direction:column; gap:8px; }
.pair{ display:flex; align-items:center; justify-content:space-between; }
.label{ display:inline-flex; align-items:center; } .value{ font-weight:800; }
.text-success{ color:#059669; } .text-warn{ color:#d97706; } .text-danger{ color:#e11d48; }

/* =====================
   MODAL LAYOUT
   ===================== */
.modal-row{ display:flex; gap:16px; flex-wrap:wrap; }
.inner-card{ flex:1 1 320px; padding:12px; }
.inner-head{ display:flex; align-items:center; justify-content:space-between; }
.glass-modal :deep(.p-dialog-content){ background:transparent; }

/* =====================
   UTILITIES (shared across app)
   ===================== */
.empty{ text-align:center; opacity:.75; padding:12px; }
.pos{ color:#059669; } .neg{ color:#e11d48; }
.link-btn{ cursor:pointer; color:var(--primary-color,#3b82f6); background:transparent; border:none; }
.sep{ opacity:.55; }

/* Skeleton shimmer (plug-and-play) */
.skeleton{ position:relative; overflow:hidden; background:linear-gradient(90deg, rgba(0,0,0,.06) 25%, rgba(0,0,0,.08) 37%, rgba(0,0,0,.06) 63%); background-size:400% 100%; animation:skeleton-shimmer 1.2s ease-in-out infinite; border-radius:8px; }
@keyframes skeleton-shimmer{ 0%{ background-position:100% 0 } 100%{ background-position:-100% 0 } }
</style>
