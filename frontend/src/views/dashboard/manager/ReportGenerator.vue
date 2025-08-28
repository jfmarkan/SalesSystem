<template>
  <div class="dash-wrapper">
    <grid-layout
      v-model:layout="layout"
      :col-num="12"
      :row-height="30"
      :is-draggable="isEditable"
      :is-resizable="isEditable"
      :margin="[10, 10]"
      :use-css-transforms="true"
    >
      <!-- LEFT COLUMN (2/12): three stacked selectors -->
      <grid-item
        v-for="item in leftCol"
        :key="item.i"
        :x="item.x"
        :y="item.y"
        :w="item.w"
        :h="item.h"
        :i="item.i"
      >
        <GlassCard :title="item.title">
          <div v-if="item.type === 'org'" class="selector">
            <button
              class="sel-btn"
              :class="{ active: scope === 'company' }"
              @click="selectCompany()"
              title="Unternehmen gesamt"
            >
              <i class="pi pi-building"></i><span>Unternehmen</span>
            </button>
          </div>

          <div v-else-if="item.type === 'teams'" class="selector list">
            <div class="list-head"><i class="pi pi-users"></i><span>Teams</span></div>
            <div class="list-scroll">
              <button
                v-for="t in teams"
                :key="t.id"
                class="row"
                :class="{ active: scope === 'team' && selectedTeamId === t.id }"
                @click="selectTeam(t)"
                :title="t.name"
              >
                <i class="pi pi-sitemap"></i><span class="ellipsis">{{ t.name }}</span>
              </button>
              <div v-if="!teams.length" class="empty">Keine Teams gefunden.</div>
            </div>
          </div>

          <div v-else-if="item.type === 'reps'" class="selector list">
            <div class="list-head"><i class="pi pi-user"></i><span>Mitarbeiter</span></div>
            <div class="search">
              <span class="p-input-icon-left w-full">
                <i class="pi pi-search" />
                <InputText v-model="repQuery" placeholder="Suche" class="w-full" />
              </span>
            </div>
            <div class="list-scroll">
              <button
                v-for="r in filteredReps"
                :key="r.value"
                class="row"
                :class="{ active: scope === 'user' && selectedUserId === r.value }"
                @click="selectUser(r)"
                :title="r.label"
              >
                <i class="pi pi-user"></i><span class="ellipsis">{{ r.label }}</span>
              </button>
              <div v-if="!filteredReps.length" class="empty">Keine Mitarbeiter.</div>
            </div>
          </div>
        </GlassCard>
      </grid-item>

      <!-- RIGHT COLUMN (10/12): report -->
      <grid-item :x="2" :y="0" :w="10" :h="5" i="kpi">
        <GlassCard :title="reportTitle">
          <template #header-extra>
            <div class="unit-toggle">
              <button :class="['u-btn', unit === 'VKEH' && 'active']" @click="changeUnit('VKEH')">
                VK-EH
              </button>
              <button :class="['u-btn', unit === 'M3' && 'active']" @click="changeUnit('M3')">
                m³
              </button>
              <button :class="['u-btn', unit === 'EUR' && 'active']" @click="changeUnit('EUR')">
                €
              </button>
            </div>
          </template>
          <KpiCard class="grid-widget" :kpis="kpisById" :modelValue="'__group__'" :unit="unit" />
        </GlassCard>
      </grid-item>

      <grid-item :x="2" :y="5" :w="10" :h="9" i="chart">
        <GlassCard :title="chartTitle">
          <ChartCard class="grid-widget" :labels="chartLabels" :series="chartSeries" :unit="unit" />
        </GlassCard>
      </grid-item>

      <grid-item :x="2" :y="14" :w="10" :h="10" i="table">
        <GlassCard :title="tableTitle">
          <ProfitCentersTable class="grid-widget" :rows="pcRows" :totals="pcTotals" :unit="unit" />
        </GlassCard>
      </grid-item>
    </grid-layout>

    <div v-if="errorMsg" class="err">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
// UI in German; code/comments in English.
import { ref, computed, onMounted, watch } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import InputText from 'primevue/inputtext'
import GlassCard from '@/components/ui/GlassCard.vue'
import KpiCard from '@/components/widgets/KpiCard.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'
import api from '@/plugins/axios'

/* ---------- endpoints (adjust here if your paths differ) ---------- */
const API = {
  companyOverview: '/api/manager/company/overview',
  companyMembers: '/api/manager/company/members', // assumed; fallback handled
  profitCenters: '/api/manager/profit-centers', // supports ?scope=company|team&team_id=... or ?user_id=...
  teamOverview: '/api/manager/team/overview', // supports ?team_id=...
  teamMembers: '/api/manager/team/members', // supports ?team_id=...
  teams: '/api/manager/teams', // assumed; returns [{id,name}]
  userOverview: '/api/manager/user/overview', // assumed; supports ?user_id=...
}

/* ---------- layout ---------- */
const isEditable = ref(false)
const layout = ref([
  // left column (2/12)
  { i: 'org', x: 0, y: 0, w: 2, h: 5 },
  { i: 'teams', x: 0, y: 5, w: 2, h: 9 },
  { i: 'reps', x: 0, y: 14, w: 2, h: 10 },
  // right column (10/12) defined directly in template for clarity
])
const leftCol = computed(() => [
  { i: 'org', x: 0, y: 0, w: 2, h: 5, type: 'org', title: 'Unternehmen' },
  { i: 'teams', x: 0, y: 5, w: 2, h: 9, type: 'teams', title: 'Teams' },
  { i: 'reps', x: 0, y: 14, w: 2, h: 10, type: 'reps', title: 'Mitarbeiter' },
])

/* ---------- selection state ---------- */
const scope = ref('company') // 'company' | 'team' | 'user'
const selectedTeamId = ref(null)
const selectedUserId = ref(null)
const repQuery = ref('')

/* ---------- lists ---------- */
const teams = ref([]) // [{id,name}]
const reps = ref([]) // [{label,value,team_id?}] depends on scope (company vs team)
const filteredReps = computed(() => {
  const q = repQuery.value.toLowerCase()
  return reps.value.filter((r) => r.label.toLowerCase().includes(q))
})

/* ---------- unit + helpers ---------- */
const unit = ref('EUR')
function changeUnit(next) {
  if (unit.value !== next) unit.value = next
}
const fmtNumber = (n) => new Intl.NumberFormat('de-DE').format(Number(n || 0))
const fmtEuro = (n) =>
  new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(Number(n || 0))
function mapVal(obj) {
  return unit.value === 'EUR'
    ? obj?.euros || 0
    : unit.value === 'M3'
      ? obj?.m3 || 0
      : obj?.units || 0
}

/* ---------- data for report ---------- */
const errorMsg = ref('')
const overview = ref(null) // KPI/Trend payload (company/team/user)
const pcData = ref([]) // PC rows (raw from API)

/* ---------- computed for widgets ---------- */
const reportTitle = computed(() =>
  scope.value === 'company'
    ? 'Unternehmen – Übersicht (YTD)'
    : scope.value === 'team'
      ? `Team – Übersicht (YTD)`
      : `Mitarbeiter – Übersicht (YTD)`,
)
const chartTitle = computed(() =>
  scope.value === 'company'
    ? 'Tendenz Unternehmen (12 Monate)'
    : scope.value === 'team'
      ? 'Tendenz Team (12 Monate)'
      : 'Tendenz (Auswahl)',
)
const tableTitle = computed(() => 'Profit-Center (YTD)')

const kpisById = computed(() => {
  // Build four KPIs compatible with KpiCard
  const k = overview.value || {}
  return {
    __group__: { label: 'Gesamt', value: 0, unit: '' }, // ignored by KpiCard if not used
    umsatz_eur: { label: 'Gesamtumsatz', value: k?.totals?.sales?.euros ?? 0, unit: 'EUR' },
    ist_vs_budget: { label: 'Ist vs Budget', value: k?.achievement?.vs_budget_pct ?? 0, unit: '%' },
    ist_vs_prognose: {
      label: 'Ist vs Forecast',
      value: k?.achievement?.vs_forecast_pct ?? 0,
      unit: '%',
    },
    umsatz_yoy: { label: 'Umsatz YoY', value: k?.yoy?.sales_euros_yoy_pct ?? 0, unit: '%' },
  }
})

const chartLabels = computed(() => (overview.value?.trend || []).map((p) => p.period?.slice(0, 7)))
const chartSeries = computed(() => [
  {
    label: 'Umsatz (€)',
    color: '#2563eb',
    data: (overview.value?.trend || []).map((p) => p.euros || 0),
  },
])

const pcRows = computed(() =>
  (pcData.value || []).map((r) => ({
    pcId: r.pc_id ?? r.pc_code ?? r.pc_name,
    pcCode: r.pc_code,
    pcName: r.pc_name,
    ist: mapVal(r.sales),
    prognose: mapVal(r.forecast),
    budget: mapVal(r.budget),
    ach_budget: r.achievement?.vs_budget_pct ?? 0,
    ach_forecast: r.achievement?.vs_forecast_pct ?? 0,
  })),
)
const pcTotals = computed(() =>
  pcRows.value.reduce(
    (a, r) => ({
      ist: a.ist + (r.ist || 0),
      prognose: a.prognose + (r.prognose || 0),
      budget: a.budget + (r.budget || 0),
    }),
    { ist: 0, prognose: 0, budget: 0 },
  ),
)

/* ---------- selection handlers ---------- */
function selectCompany() {
  scope.value = 'company'
  selectedTeamId.value = null
  selectedUserId.value = null
  loadRepsForCompany()
  loadReport()
}
function selectTeam(team) {
  scope.value = 'team'
  selectedTeamId.value = team?.id ?? null
  selectedUserId.value = null
  loadRepsForTeam(selectedTeamId.value)
  loadReport()
}
function selectUser(rep) {
  scope.value = 'user'
  selectedUserId.value = rep?.value ?? null
  loadReport()
}

/* ---------- loaders ---------- */
async function loadTeams() {
  try {
    const { data } = await api.get(API.teams)
    teams.value = Array.isArray(data) ? data.map((t) => ({ id: t.id, name: t.name })) : []
  } catch {
    teams.value = [] // safe fallback
  }
}
async function loadRepsForCompany() {
  try {
    const { data } = await api.get(API.companyMembers)
    reps.value = (Array.isArray(data) ? data : []).map((u) => ({
      label: u.name ?? `${u.first_name ?? ''} ${u.last_name ?? ''}`.trim(),
      value: String(u.id ?? u.user_id ?? u.email ?? Math.random()),
      team_id: u.team_id ?? null,
    }))
  } catch {
    // Fallback: use manager team members endpoint if company members not available
    try {
      const { data } = await api.get(API.teamMembers)
      reps.value = (Array.isArray(data) ? data : []).map((u) => ({
        label: u.name ?? 'Mitarbeiter',
        value: String(u.user_id ?? u.id ?? Math.random()),
        team_id: u.team_id ?? null,
      }))
    } catch {
      reps.value = []
    }
  }
}
async function loadRepsForTeam(teamId) {
  try {
    const { data } = await api.get(API.teamMembers, { params: { team_id: teamId } })
    reps.value = (Array.isArray(data) ? data : []).map((u) => ({
      label: u.name ?? 'Mitarbeiter',
      value: String(u.user_id ?? u.id ?? Math.random()),
      team_id: teamId,
    }))
  } catch {
    reps.value = []
  }
}

async function loadReport() {
  errorMsg.value = ''
  try {
    if (scope.value === 'company') {
      const [ov, pcs] = await Promise.all([
        api.get(API.companyOverview),
        api.get(API.profitCenters, { params: { scope: 'company' } }),
      ])
      overview.value = ov.data || null
      pcData.value = pcs.data || []
    } else if (scope.value === 'team' && selectedTeamId.value) {
      const [ov, pcs] = await Promise.all([
        api.get(API.teamOverview, { params: { team_id: selectedTeamId.value } }),
        api.get(API.profitCenters, { params: { scope: 'team', team_id: selectedTeamId.value } }),
      ])
      overview.value = ov.data || null
      pcData.value = pcs.data || []
    } else if (scope.value === 'user' && selectedUserId.value) {
      // Try dedicated user overview
      try {
        const [ov, pcs] = await Promise.all([
          api.get(API.userOverview, { params: { user_id: selectedUserId.value } }),
          api.get(API.profitCenters, { params: { user_id: selectedUserId.value } }),
        ])
        overview.value = ov.data || null
        pcData.value = pcs.data || []
      } catch {
        // Fallback: derive from team members row (EUR only for KPIs if needed)
        const tm = await api.get(API.teamMembers, {
          params: { team_id: selectedTeamId.value || undefined },
        })
        const row = (tm.data || []).find(
          (r) => String(r.user_id ?? r.id) === String(selectedUserId.value),
        )
        if (row) {
          overview.value = {
            totals: {
              sales: row.sales ?? {},
              forecast: row.forecast ?? {},
              budget: row.budget ?? {},
            },
            achievement: {
              vs_budget_pct: row.achievement?.vs_budget_pct ?? 0,
              vs_forecast_pct: row.achievement?.vs_forecast_pct ?? 0,
            },
            yoy: { sales_euros_yoy_pct: 0 },
            trend: [],
          }
        } else {
          overview.value = {
            totals: { sales: {}, forecast: {}, budget: {} },
            achievement: { vs_budget_pct: 0, vs_forecast_pct: 0 },
            yoy: { sales_euros_yoy_pct: 0 },
            trend: [],
          }
        }
        pcData.value = [] // no PC breakdown on fallback
      }
    }
  } catch (e) {
    console.error(e)
    errorMsg.value = 'Fehler beim Laden.'
    overview.value = null
    pcData.value = []
  }
}

/* ---------- boot ---------- */
onMounted(async () => {
  await loadTeams()
  await loadRepsForCompany()
  await loadReport()
})

/* ---------- reactive reloads ---------- */
watch(unit, () => {
  /* formatting only; no refetch needed */
})
</script>

<style scoped>
.dash-wrapper {
  width: 100%;
}

/* Left selectors aesthetic */
.selector {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.sel-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  background: rgba(255, 255, 255, 0.5);
  border-radius: 10px;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
}
.sel-btn:hover {
  background: rgba(255, 255, 255, 0.75);
}
.sel-btn.active {
  background: rgba(31, 86, 115, 0.12);
  font-weight: 700;
}

.list {
  height: 100%;
}
.list-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-bottom: 0.25rem;
  color: #334155;
}
.search {
  margin: 0.25rem 0 0.5rem;
}
.list-scroll {
  overflow: auto;
  max-height: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid rgba(0, 0, 0, 0.06);
  background: rgba(255, 255, 255, 0.5);
  border-radius: 8px;
  padding: 0.4rem 0.5rem;
  cursor: pointer;
}
.row:hover {
  background: rgba(255, 255, 255, 0.75);
}
.row.active {
  background: rgba(31, 86, 115, 0.12);
  font-weight: 700;
}
.empty {
  font-size: 0.9rem;
  color: #64748b;
  padding: 0.25rem 0;
}
.ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

/* Right column header extras */
.unit-toggle {
  display: flex;
  gap: 6px;
  background: rgba(255, 255, 255, 0.35);
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 8px;
  padding: 2px;
}
.u-btn {
  border: 0;
  background: transparent;
  padding: 0.25rem 0.5rem;
  font-size: 0.8rem;
  cursor: pointer;
  border-radius: 6px;
}
.u-btn.active {
  background: rgba(31, 86, 115, 0.8);
  color: #fff;
  font-weight: 700;
}

/* Common */
.grid-widget {
  height: 100%;
  width: 100%;
  box-sizing: border-box;
}
.err {
  margin-top: 8px;
  padding: 6px 10px;
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.08);
  color: #7f1d1d;
  border: 1px solid rgba(239, 68, 68, 0.35);
}
</style>