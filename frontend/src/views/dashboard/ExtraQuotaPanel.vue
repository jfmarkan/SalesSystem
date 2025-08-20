<template>
  <div class="container">
    <div class="panel">
      <div class="spread" style="margin-bottom:8px">
        <h1>Extra-Quote – Neue Chance</h1>
        <span class="badge">Status: {{ status }}</span>
      </div>

      <div class="grid grid-3">
        <label>
          <span>Profit Center</span>
          <select v-model.number="pcCode">
            <option :value="null" disabled>Bitte wählen</option>
            <option v-for="pc in profitCenters" :key="pc.code" :value="pc.code">{{ pc.name }} ({{ pc.code }})</option>
          </select>
        </label>

        <label>
          <span>Monat</span>
          <select v-model.number="month">
            <option v-for="m in 12" :key="m" :value="m">{{ monthNames[m-1] }}</option>
          </select>
        </label>

        <label>
          <span>Jahr</span>
          <select v-model.number="year">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </label>
      </div>

      <div class="grid grid-3" style="margin-top:12px">
        <label>
          <span>Kunde (neu)</span>
          <div class="row">
            <input class="input" v-model.trim="clientName" placeholder="Firmenname"/>
            <button class="btn" :disabled="!pcCode" @click="openClientPicker">Kunde wählen</button>
          </div>
        </label>

        <label>
          <span>Betrag (EUR)</span>
          <input class="input" v-model="amountInput" @blur="normalizeAmount" inputmode="decimal" placeholder="z.B. 100000" />
        </label>

        <label>
          <span>Wahrscheinlichkeit (%)</span>
          <input class="input" v-model.number="probability" type="number" min="0" max="100" @input="onProbabilityChange" />
        </label>
      </div>

      <div class="grid" style="margin-top:12px">
        <label>
          <span>Kommentar (optional)</span>
          <textarea rows="2" v-model.trim="comment" placeholder="Bemerkungen..."></textarea>
        </label>
      </div>

      <div class="spread" style="margin-top:12px">
        <div class="row">
          <span class="badge">Verfügbare Extra-Quote: {{ availableQuotaText }}</span>
        </div>
        <div class="row" style="gap:12px">
          <button class="btn ghost" @click="resetForm">Zurücksetzen</button>
          <button class="btn primary" @click="saveOpportunity">Speichern</button>
        </div>
      </div>
    </div>

    <div v-if="showBudget" class="panel" style="margin-top:16px">
      <div class="spread">
        <h2>Budget & Prognose (ab {{ monthNames[month-1] }} {{ year }})</h2>
        <div class="row">
          <span class="badge">Gesamt Budget: {{ fmt(amount) }}</span>
          <span class="badge">Gesamt Prognose: {{ fmt(forecastTotal) }}</span>
        </div>
      </div>

      <hr class="sep" />

      <table class="table">
        <thead>
          <tr>
            <th>Monat</th>
            <th>Budget</th>
            <th>Prognose</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in budgetRows" :key="row.month" :class="{ muted: isBeforeStart(row.month) }">
            <td>{{ row.monthLabel }}</td>
            <td>{{ fmt(row.budget) }}</td>
            <td>{{ fmt(row.forecast) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td>Summe</td>
            <td>{{ fmt(budgetTotal) }}</td>
            <td>{{ fmt(forecastTotal) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <ClientPickerModal
      v-if="clientPickerOpen"
      :clients="clientsWithoutPC"
      @close="clientPickerOpen = false"
      @pick="onClientPicked"
    />
  </div>
</template>

<script setup>
// UI in German; variable names + comments in English.
// Implements SalesRep "Extra Quota" window with month-year, client selection,
// amount, probability, comment, and "Open" status. On save, shows prorated
// budget (Apr..Mar fiscal) and forecast (probability-adjusted).

import { ref, computed, watch, onMounted } from 'vue'
//import ClientPickerModal from './ClientPickerModal.vue'
//import { monthShortDe as monthNames, emptyFiscalRows, prorateBudget, fmtEUR as fmt, fiscalYearFromDate } from '../utils/seasonality'
/* import {
  getProfitCenters,
  getClientsWithoutPC,
  getNextOpportunityGroupID,
  getNextVersionNumber,
  getSeasonality,
  saveOpportunity,
  saveExtraQuotaBudget,
  getAvailableExtraQuotas
} from '../services/api' */

// --- Form state ---
const userID = ref(1)                 // current user id (mock)
const pcCode = ref(null)              // selected profit center code
const month = ref(new Date().getMonth()+1) // selected calendar month 1..12
const year = ref(new Date().getFullYear()) // selected calendar year
const clientName = ref('')
const amount = ref(0)                 // amount numeric (EUR)
const amountInput = ref('')           // raw input for amount
const probability = ref(100)          // percentage 0..100
const comment = ref('')
const status = ref('Offen')           // default when creating

// --- UI state ---
const showBudget = ref(false)
const profitCenters = ref([])
const clientsWithoutPC = ref([])
const clientPickerOpen = ref(false)

// --- Budget table data ---
const budgetRows = ref(emptyFiscalRows()) // {month, monthLabel, budget, forecast}

// Year options for select
const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y-1, y, y+1, y+2]
})

// Format available quota badge
const availableQuotaText = ref('–')

// Normalize amount input to number (integer EUR)
function normalizeAmount() {
  // Accept formats like "100.000,50" or "100000"
  const raw = (amountInput.value || '').toString().trim()
  if (!raw) { amount.value = 0; amountInput.value = ''; return }
  // Replace thousands separators, normalize decimal comma to dot
  const cleaned = raw.replace(/\./g,'').replace(/,/g,'.')
  const num = Math.max(0, Math.round(Number(cleaned) || 0))
  amount.value = num
  amountInput.value = String(num)
}

function onProbabilityChange() {
  // live update forecast rows if budget already shown
  if (showBudget.value) {
    applyForecastFromProbability()
  }
}

function isBeforeStart(rowMonth) {
  // grey-out months before selected start
  return rowMonth < 4
    ? (month.value > 3 && rowMonth < 4) && (rowMonth < month.value) // Jan-Mar vs start > Mar
    : (rowMonth < month.value && month.value >= 4)
}

function resetForm() {
  pcCode.value = null
  month.value = new Date().getMonth()+1
  year.value = new Date().getFullYear()
  clientName.value = ''
  amount.value = 0
  amountInput.value = ''
  probability.value = 100
  comment.value = ''
  status.value = 'Offen'
  showBudget.value = false
  budgetRows.value = emptyFiscalRows()
  availableQuotaText.value = '–'
}

// Load profit centers initially
onMounted(async () => {
  profitCenters.value = await getProfitCenters()
})

// When PC or date changes, refresh available quota and clients list
watch([pcCode, month, year], async () => {
  if (!pcCode.value) { availableQuotaText.value = '–'; return }
  const d = new Date(year.value, month.value - 1, 1)
  const fy = fiscalYearFromDate(d)
  const avail = await getAvailableExtraQuotas(userID.value, pcCode.value, fy)
  availableQuotaText.value = fmt(avail)

  // update client picker source
  clientsWithoutPC.value = await getClientsWithoutPC(pcCode.value)
})

// Open modal to pick existing client without this PC
async function openClientPicker() {
  if (!pcCode.value) { alert('Bitte zuerst ein Profit Center wählen.'); return }
  clientsWithoutPC.value = await getClientsWithoutPC(pcCode.value)
  clientPickerOpen.value = true
}
function onClientPicked(c) {
  clientName.value = c.name
  clientPickerOpen.value = false
}

// Save flow: create group/version, persist opportunity, generate and save budget, display table
async function saveOpportunityHandler(groupID, version) {
  const startDate = new Date(year.value, month.value - 1, 1)
  const fy = fiscalYearFromDate(startDate)
  // 1) opportunity
  await saveOpportunity({
    groupID,
    version,
    clientName: clientName.value,
    date: startDate.toISOString(),
    amount: amount.value,
    probability: probability.value,
    comment: comment.value,
    status: status.value,
    userID: userID.value,
    pcCode: pcCode.value,
    fiscalYear: fy
  })

  // 2) seasonality (fiscal order)
  const seasonality = await getSeasonality(pcCode.value, fy)

  // 3) budget distribution (100% of amount)
  const monthlyBudgetFiscal = prorateBudget(seasonality, startDate, amount.value)

  // 4) forecast distribution (probability-applied)
  const factor = Math.max(0, Math.min(100, probability.value)) / 100
  const monthlyForecastFiscal = monthlyBudgetFiscal.map(v => Math.round(v * factor))

  // 5) map to rows Apr..Mar
  const rows = []
  for (let i = 0; i < 12; i++) {
    const fiscalMonth = [4,5,6,7,8,9,10,11,12,1,2,3][i]
    rows.push({
      month: fiscalMonth,
      monthLabel: monthNames[fiscalMonth-1],
      budget: monthlyBudgetFiscal[i],
      forecast: monthlyForecastFiscal[i]
    })
  }
  budgetRows.value = rows
  showBudget.value = true

  // 6) persist budget items (with year mapping)
  const itemsToSave = rows
    .filter(r => r.budget > 0)
    .map(r => ({
      month: r.month,
      year: r.month >= 4 ? fy : fy + 1,
      amount: r.budget
    }))
  await saveExtraQuotaBudget(groupID, version, itemsToSave)
}

async function saveExtraQuotaOpportunity() {
  normalizeAmount()
  // basic validations
  if (!pcCode.value) return alert('Profit Center ist erforderlich.')
  if (!clientName.value.trim()) return alert('Kunde ist erforderlich.')
  if (!amount.value || amount.value <= 0) return alert('Betrag muss > 0 sein.')
  if (probability.value < 0 || probability.value > 100) return alert('Wahrscheinlichkeit muss zwischen 0 und 100 liegen.')

  try {
    const groupID = await getNextOpportunityGroupID()
    const version = await getNextVersionNumber(groupID)
    await saveOpportunityHandler(groupID, version)
    alert('Chance gespeichert und Budget erstellt ✔️')
  } catch (e) {
    console.error(e)
    alert('Fehler beim Speichern: ' + (e?.message || e))
  }
}

// Recompute forecast when probability changes after save
function applyForecastFromProbability() {
  const factor = Math.max(0, Math.min(100, probability.value)) / 100
  budgetRows.value = budgetRows.value.map(r => ({
    ...r,
    forecast: Math.round(r.budget * factor)
  }))
}

// Totals
const budgetTotal = computed(() => budgetRows.value.reduce((s, r) => s + (r.budget||0), 0))
const forecastTotal = computed(() => budgetRows.value.reduce((s, r) => s + (r.forecast||0), 0))
</script>