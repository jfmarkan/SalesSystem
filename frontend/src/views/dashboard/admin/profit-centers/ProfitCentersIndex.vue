<template>
  <div class="users-page">
    <!-- Dialog: Profit Center anlegen / bearbeiten -->
    <Dialog
      v-model:visible="showPcDialog"
      :modal="true"
      :closable="true"
      :dismissable-mask="true"
      :header="pcDialogTitle"
      :style="{ width: '420px' }"
    >
      <form @submit.prevent="saveProfitCenter">
        <div class="field">
          <label for="pc-code">Code</label>
          <InputText
            id="pc-code"
            v-model="pcForm.code"
            class="text-input"
            :disabled="pcDialogMode === 'edit'"
            required
          />
        </div>
        <div class="field">
          <label for="pc-name">Name</label>
          <InputText
            id="pc-name"
            v-model="pcForm.name"
            class="text-input"
            required
          />
        </div>

        <div class="dialog-actions">
          <Button
            type="button"
            class="btn secondary"
            label="Abbrechen"
            @click="showPcDialog = false"
          />
          <Button
            type="submit"
            class="btn primary"
            :label="pcDialogMode === 'edit' ? 'Speichern' : 'Anlegen'"
          />
        </div>
      </form>
    </Dialog>

    <div class="grid2-10">
      <!-- ASIDE (LISTE PROFIT CENTERS) -->
      <aside class="pane left">
        <div class="pane-head">
          <Button icon="pi pi-plus" class="btn-add" @click="openCreateProfitCenter" />
        </div>

        <div class="list-wrap">
          <Listbox
            v-model="selectedCode"
            :options="filteredList"
            optionLabel="__label"
            optionValue="profit_center_code"
            class="w-full h-full"
            :listStyle="{ height: '100%' }"
          >
            <template #option="{ option }">
              <div class="lb-row">
                <Tag class="state-dot" icon="pi pi-database" severity="info" />
                <span class="lb-name">{{ option.__label }}</span>
              </div>
            </template>
          </Listbox>
        </div>
      </aside>

      <!-- RECHTES PANEL -->
      <section class="right" v-if="selected">
        <!-- HEADER: PC + WJ + acciones -->
        <Card class="title-card">
          <template #content>
            <div class="title-head">
              <div class="th-left">
                <Avatar
                  :label="selected.profit_center_code"
                  shape="circle"
                  size="large"
                  class="avt"
                />
                <div class="id-block">
                  <div class="name-row">
                    <span class="name">{{ selected.profit_center_name }}</span>
                    <Tag class="state-dot name-dot" icon="pi pi-database" severity="info" />
                    <Button
                      icon="pi pi-pencil"
                      text
                      rounded
                      size="small"
                      class="pc-edit-btn"
                      @click="openEditProfitCenter"
                      :title="'Profit Center bearbeiten'"
                    />
                  </div>
                </div>
              </div>

              <div class="th-right">
                <!-- Navegar FY -->
                <Button
                  icon="pi pi-chevron-left"
                  text
                  rounded
                  size="small"
                  @click="prevFy"
                />
                <span class="fy-label">{{ headerWjLabel }}</span>
                <Button
                  icon="pi pi-chevron-right"
                  text
                  rounded
                  size="small"
                  @click="nextFy"
                />

                <!-- NUEVO AÑO (Seasonality + Conversion) -->
                <Button
                  icon="pi pi-plus"
                  text
                  rounded
                  size="small"
                  class="ml-2"
                  @click="createNextYearBoth"
                  :title="'Neues Geschäftsjahr anlegen (Seasonality + Conversion)'"
                />

                <!-- GUARDAR AÑO ACTUAL (Seasonality + Conversion) -->
                <Button
                  icon="pi pi-save"
                  text
                  rounded
                  size="small"
                  @click="saveBoth"
                  :loading="savingSeasonality || savingConversion"
                  :title="'Seasonality + Conversion speichern'"
                />
              </div>
            </div>
          </template>
        </Card>

        <!-- OBERER BEREICH: SEASONALITY + CONVERSION -->
        <div class="grid12">
          <!-- SEASONALITY CARD (8 Spalten) -->
          <Card class="col-8">
            <template #header>
              <div class="card-header">
                <div class="card-header-main">
                  <div class="card-title">Seasonality</div>
                  <div class="card-subtitle">
                    {{ seasonalityFyLabel || 'Ohne Jahr' }}
                  </div>
                </div>
              </div>
            </template>
            <template #content>
              <div class="month-row">
                <FloatLabel
                  v-for="(label, i) in seasonalityMonthLabels"
                  :key="i"
                  class="month-field"
                >
                  <InputNumber
                    v-model="editableSeasonality[i]"
                    :useGrouping="false"
                    :minFractionDigits="0"
                    :maxFractionDigits="2"
                    :min="0"
                    :max="99.99"
                    :step="0.01"
                    :inputId="'month-' + i"
                    class="month-input"
                  />
                  <label :for="'month-' + i">{{ label }}</label>
                </FloatLabel>
              </div>
            </template>
          </Card>

          <!-- CONVERSION CARD (4 Spalten) -->
          <Card class="col-4">
            <template #header>
              <div class="card-header">
                <div class="card-header-main">
                  <div class="card-title">Conversion</div>
                  <div class="card-subtitle">
                    {{ conversionFyLabel || 'Ohne Jahr' }}
                  </div>
                </div>
              </div>
            </template>
            <template #content>
              <div class="conv-row">
                <FloatLabel class="conv-field conv-field-wide">
                  <InputNumber
                    v-model="editableConversion.factor_m3"
                    inputId="m3"
                    :useGrouping="false"
                    :minFractionDigits="0"
                    :maxFractionDigits="4"
                  />
                  <label for="m3">Factor m³</label>
                </FloatLabel>

                <FloatLabel class="conv-field conv-field-wide">
                  <InputNumber
                    v-model="editableConversion.factor_eur"
                    inputId="eur"
                    :useGrouping="false"
                    :minFractionDigits="0"
                    :maxFractionDigits="4"
                  />
                  <label for="eur">Factor €</label>
                </FloatLabel>

                <FloatLabel class="conv-field conv-field-unit">
                  <Select
                    v-model="editableConversion.unit"
                    :options="unitOptions"
                    optionLabel="label"
                    optionValue="value"
                    inputId="unit"
                  />
                  <label for="unit">Einheit</label>
                </FloatLabel>
              </div>
            </template>
          </Card>
        </div>

        <!-- CHART-BEREICH -->
        <div class="grid12 charts-row">
          <!-- CHART: SEASONALITY -->
          <Card class="col-8">
            <template #header>
              <div class="chart-header">
                <span>Seasonality Trends</span>
                <div class="chart-range">
                  <span class="chart-range-label">WJ</span>
                  <MultiSelect
                    v-model="seasonalityYearsSelected"
                    :options="seasonalityYearOptions"
                    optionLabel="label"
                    optionValue="value"
                    display="chip"
                    class="chart-year-ms"
                    :placeholder="seasonalityYearOptions.length ? 'Auswählen' : 'Keine Daten'"
                    :disabled="!seasonalityYearOptions.length"
                  />
                </div>
              </div>
            </template>
            <template #content>
              <div class="chart-container">
                <Chart
                  type="line"
                  :data="seasonalityChartData"
                  :options="seasonalityChartOptions"
                />
              </div>
            </template>
          </Card>

          <!-- CHART: CONVERSION -->
          <Card class="col-4">
            <template #header>Conversion Factors Over Time</template>
            <template #content>
              <div class="chart-container">
                <Chart
                  type="line"
                  :data="conversionChartData"
                  :options="conversionChartOptions"
                />
              </div>
            </template>
          </Card>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/plugins/axios'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'

/* ---------- STATE: Profit Centers ---------- */

const selectedCode = ref(null)
const list = ref([])

const showPcDialog = ref(false)
const pcDialogMode = ref('create')
const pcForm = ref({
  id: null,
  code: '',
  name: '',
})

/* ---------- Seasonality & Conversion ---------- */

const seasonalityRows = ref([])
const conversionRows = ref([])

const editableSeasonality = ref(new Array(12).fill(null))
const editableConversion = ref({ factor_m3: null, factor_eur: null, unit: '' })

const savingSeasonality = ref(false)
const savingConversion = ref(false)

const unitOptions = [
  { label: 'm³', value: 'm3' },
  { label: 'm²', value: 'm2' },
  { label: 'LFM', value: 'lfm' },
]

// FY actual
function fyOf(d = new Date()) {
  const y = d.getFullYear()
  const m = d.getMonth() + 1
  return m >= 4 ? y : y - 1
}
const fy = ref(fyOf())

const fiscalMonths = [
  { field: 'apr', label: 'Apr' },
  { field: 'may', label: 'Mai' },
  { field: 'jun', label: 'Jun' },
  { field: 'jul', label: 'Jul' },
  { field: 'aug', label: 'Aug' },
  { field: 'sep', label: 'Sep' },
  { field: 'oct', label: 'Okt' },
  { field: 'nov', label: 'Nov' },
  { field: 'dec', label: 'Dez' },
  { field: 'jan', label: 'Jan' },
  { field: 'feb', label: 'Feb' },
  { field: 'mar', label: 'Mär' },
]

/* ---------- LISTA IZQUIERDA ---------- */

const filteredList = computed(() =>
  list.value.map(p => ({
    ...p,
    __label: `${p.profit_center_name}`,
  })),
)

const selected = computed(
  () => list.value.find(p => p.profit_center_code === selectedCode.value) || null,
)

/* ---------- LABELS ---------- */

const headerWjLabel = computed(() => {
  const year = fy.value
  const y2 = (year + 1).toString().slice(-2)
  return `WJ${year}/${y2}`
})

function wjLabel(year) {
  if (!year) return ''
  const y2 = (year + 1).toString().slice(-2)
  return `WJ${year}/${y2}`
}

/* ---------- Carry-forward logic ---------- */

function pickSeasonalityForFy(fyVal) {
  const rows = seasonalityRows.value
  if (!rows.length) return null

  const numeric = rows
    .map(r => ({ ...r, _fyNum: Number(r.fiscal_year) }))
    .filter(r => Number.isFinite(r._fyNum))

  if (!numeric.length) return rows[rows.length - 1]

  const fyNum = Number(fyVal)
  const le = numeric.filter(r => r._fyNum <= fyNum)
  if (le.length) {
    return le.reduce((a, b) => (a._fyNum > b._fyNum ? a : b))
  }
  return numeric.reduce((a, b) => (a._fyNum < b._fyNum ? a : b))
}

function pickConversionForFy(fyVal) {
  const rows = conversionRows.value
  if (!rows.length) return null

  const numeric = rows
    .map(r => ({ ...r, _fyNum: Number(r.fiscal_year) }))
    .filter(r => Number.isFinite(r._fyNum))

  if (!numeric.length) return rows[rows.length - 1]

  const fyNum = Number(fyVal)
  const le = numeric.filter(r => r._fyNum <= fyNum)
  if (le.length) {
    return le.reduce((a, b) => (a._fyNum > b._fyNum ? a : b))
  }
  return numeric.reduce((a, b) => (a._fyNum < b._fyNum ? a : b))
}

const currentSeasonality = computed(() => pickSeasonalityForFy(fy.value))
const currentConversion = computed(() => pickConversionForFy(fy.value))

const seasonalityFyLabel = computed(() => wjLabel(fy.value))
const conversionFyLabel = computed(() => wjLabel(fy.value))

/* ---------- Labels meses ---------- */

const seasonalityMonthLabels = computed(() => {
  const baseYear = fy.value
  return fiscalMonths.map((m, idx) => {
    const y = idx <= 8 ? baseYear : baseYear + 1
    const y2 = String(y).slice(-2)
    return `${m.label} ${y2}`
  })
})

const seasonalityChartLabels = computed(() => fiscalMonths.map(m => m.label))

/* ---------- Helpers merge ---------- */

function toArray(raw) {
  if (Array.isArray(raw)) return raw
  if (raw == null) return []
  return [raw]
}

function mergeByFiscalYear(current, incoming) {
  if (!incoming.length) return current
  const out = [...current]
  incoming.forEach(row => {
    const fyNum = Number(row.fiscal_year ?? row.year)
    if (!Number.isFinite(fyNum)) return
    const idx = out.findIndex(r => Number(r.fiscal_year ?? r.year) === fyNum)
    if (idx === -1) {
      out.push({ ...row, fiscal_year: fyNum })
    } else {
      out[idx] = { ...out[idx], ...row, fiscal_year: fyNum }
    }
  })
  out.sort((a, b) => Number(a.fiscal_year ?? 0) - Number(b.fiscal_year ?? 0))
  return out
}

/* ---------- Load PCs ---------- */

async function loadProfitCenters() {
  const { data } = await api.get('/api/settings/profit-centers')
  list.value = Array.isArray(data) ? data : []

  if (list.value.length && !selectedCode.value) {
    selectedCode.value = list.value[0].profit_center_code
  } else if (selectedCode.value) {
    const exists = list.value.some(p => p.profit_center_code === selectedCode.value)
    if (!exists && list.value.length) {
      selectedCode.value = list.value[0].profit_center_code
    }
  }
}

onMounted(async () => {
  await loadProfitCenters()
})

/* ---------- PC dialog ---------- */

const pcDialogTitle = computed(() =>
  pcDialogMode.value === 'edit'
    ? 'Profit Center bearbeiten'
    : 'Neuen Profit Center anlegen',
)

function openCreateProfitCenter() {
  pcDialogMode.value = 'create'
  pcForm.value = {
    id: null,
    code: '',
    name: '',
  }
  showPcDialog.value = true
}

function openEditProfitCenter() {
  if (!selected.value) return
  pcDialogMode.value = 'edit'
  pcForm.value = {
    id: selected.value.id ?? null,
    code: selected.value.profit_center_code,
    name: selected.value.profit_center_name,
  }
  showPcDialog.value = true
}

async function saveProfitCenter() {
  const code = pcForm.value.code?.trim()
  const name = pcForm.value.name?.trim()
  if (!code || !name) return

  try {
    if (pcDialogMode.value === 'create') {
      const payload = {
        profit_center_code: code,
        profit_center_name: name,
      }
      const { data } = await api.post('/api/settings/profit-centers', payload)
      await loadProfitCenters()
      selectedCode.value = data?.profit_center_code ?? code
    } else {
      const payload = {
        profit_center_name: name,
      }
      await api.put(`/api/settings/profit-centers/${pcForm.value.code}`, payload)
      await loadProfitCenters()
      selectedCode.value = pcForm.value.code
    }

    showPcDialog.value = false
  } catch (err) {
    console.error('Error saving profit center', err)
  }
}

/* ---------- Load datos PC (todos los años) ---------- */

async function loadPcData() {
  const code = selectedCode.value
  if (!code) return
  try {
    const [sRes, cRes] = await Promise.all([
      api.get(`/api/settings/profit-centers/${code}/seasonality`),
      api.get(`/api/settings/profit-centers/${code}/conversion`),
    ])

    const sArr = toArray(sRes.data)
    const cArr = toArray(cRes.data)

    seasonalityRows.value = mergeByFiscalYear([], sArr)
    conversionRows.value = mergeByFiscalYear([], cArr)

    const years = availableSeasonalityYears.value
    if (years.length) {
      const currentFy = fyOf()
      let target = currentFy
      if (currentFy < years[0]) target = years[0]
      else if (currentFy > years[years.length - 1]) target = years[years.length - 1]
      fy.value = target
    } else {
      fy.value = fyOf()
    }

    applyCurrentToEditable()
  } catch (err) {
    console.error('Error loading PC data', err)
    applyCurrentToEditable()
  }
}

/* ---------- apply current -> editable ---------- */

function applyCurrentToEditable() {
  const curS = currentSeasonality.value
  const curC = currentConversion.value

  editableSeasonality.value = fiscalMonths.map(m => {
    if (!curS) return null
    const v = curS[m.field]
    return v != null ? Number(v) : null
  })

  editableConversion.value = curC
    ? {
        factor_m3:
          curC.factor_to_m3 != null
            ? Number(curC.factor_to_m3)
            : curC.factor_m3 != null
              ? Number(curC.factor_m3)
              : null,
        factor_eur:
          curC.factor_to_euro != null
            ? Number(curC.factor_to_euro)
            : curC.factor_eur != null
              ? Number(curC.factor_eur)
              : null,
        unit: curC.from_unit ?? curC.unit ?? curC.base_unit ?? curC.sales_unit ?? '',
      }
    : { factor_m3: null, factor_eur: null, unit: '' }
}

/* ---------- sync editable -> rows ---------- */

watch(
  editableSeasonality,
  vals => {
    if (!selectedCode.value) return
    const fyNum = Number(fy.value)
    if (!Number.isFinite(fyNum)) return

    const idx = seasonalityRows.value.findIndex(
      r => Number(r.fiscal_year) === fyNum,
    )

    const draft = { fiscal_year: fyNum }
    fiscalMonths.forEach((m, i) => {
      draft[m.field] = vals[i] != null ? Number(vals[i]) : null
    })

    if (idx === -1) {
      seasonalityRows.value = mergeByFiscalYear(seasonalityRows.value, [draft])
    } else {
      const updated = { ...seasonalityRows.value[idx], ...draft }
      seasonalityRows.value.splice(idx, 1, updated)
    }
  },
  { deep: true },
)

watch(
  editableConversion,
  val => {
    if (!selectedCode.value) return
    const fyNum = Number(fy.value)
    if (!Number.isFinite(fyNum)) return

    const idx = conversionRows.value.findIndex(
      r => Number(r.fiscal_year) === fyNum,
    )

    const updatedRow = {
      fiscal_year: fyNum,
      factor_to_m3:
        val.factor_m3 != null && val.factor_m3 !== ''
          ? Number(val.factor_m3)
          : null,
      factor_to_euro:
        val.factor_eur != null && val.factor_eur !== ''
          ? Number(val.factor_eur)
          : null,
      from_unit: val.unit || null,
    }

    if (idx === -1) {
      conversionRows.value = mergeByFiscalYear(conversionRows.value, [updatedRow])
    } else {
      const merged = { ...conversionRows.value[idx], ...updatedRow }
      conversionRows.value.splice(idx, 1, merged)
    }
  },
  { deep: true },
)

/* ---------- Años disponibles ---------- */

const availableSeasonalityYears = computed(() => {
  const years = (seasonalityRows.value || [])
    .map(s => Number(s.fiscal_year))
    .filter(y => Number.isFinite(y))
  return Array.from(new Set(years)).sort((a, b) => a - b)
})

/* ---------- Watches: PC / FY ---------- */

watch(selectedCode, code => {
  seasonalityRows.value = []
  conversionRows.value = []
  if (code) loadPcData()
})

watch(fy, () => {
  applyCurrentToEditable()
})

/* ---------- NUEVO AÑO (Seasonality + Conversion) ---------- */

function startNewSeasonality() {
  const base = fy.value
  const nextFy = Number(base) + 1

  const prev = pickSeasonalityForFy(base)
  const draft = { fiscal_year: nextFy }

  fiscalMonths.forEach(m => {
    const v = prev ? prev[m.field] : null
    draft[m.field] = v != null ? Number(v) : null
  })

  seasonalityRows.value = mergeByFiscalYear(seasonalityRows.value, [draft])
  fy.value = nextFy
}

function startNewConversion() {
  const base = fy.value
  const nextFy = Number(base) + 1

  const prev = pickConversionForFy(base)
  const draft = {
    fiscal_year: nextFy,
    factor_to_m3:
      prev && (prev.factor_to_m3 ?? prev.factor_m3) != null
        ? Number(prev.factor_to_m3 ?? prev.factor_m3)
        : null,
    factor_to_euro:
      prev && (prev.factor_to_euro ?? prev.factor_eur) != null
        ? Number(prev.factor_to_euro ?? prev.factor_eur)
        : null,
    from_unit: prev?.from_unit ?? prev?.unit ?? prev?.base_unit ?? prev?.sales_unit ?? '',
  }

  conversionRows.value = mergeByFiscalYear(conversionRows.value, [draft])
  fy.value = nextFy
}

// botón del header
function createNextYearBoth() {
  startNewSeasonality()
  startNewConversion()
}

/* ---------- GUARDAR (Seasonality + Conversion) ---------- */

async function saveSeasonality() {
  if (!selectedCode.value) return
  const fyNum = Number(fy.value)
  if (!Number.isFinite(fyNum)) return

  const row = {}
  fiscalMonths.forEach((m, i) => {
    const v = editableSeasonality.value[i]
    row[m.field] = v != null && v !== '' ? Number(v) : null
  })

  const payload = {
    fiscal_year: fyNum,
    rows: [row],
  }

  try {
    savingSeasonality.value = true
    await api.put(
      `/api/settings/profit-centers/${selectedCode.value}/seasonality`,
      payload,
    )

    seasonalityRows.value = mergeByFiscalYear(seasonalityRows.value, [
      { ...row, fiscal_year: fyNum },
    ])
  } catch (err) {
    console.error('Error saving seasonality', err)
  } finally {
    savingSeasonality.value = false
  }
}

async function saveConversion() {
  if (!selectedCode.value) return
  const fyNum = Number(fy.value)
  if (!Number.isFinite(fyNum)) return

  const row = {
    factor_to_m3:
      editableConversion.value.factor_m3 != null &&
      editableConversion.value.factor_m3 !== ''
        ? Number(editableConversion.value.factor_m3)
        : null,
    factor_to_euro:
      editableConversion.value.factor_eur != null &&
      editableConversion.value.factor_eur !== ''
        ? Number(editableConversion.value.factor_eur)
        : null,
    from_unit: editableConversion.value.unit || null,
  }

  const payload = {
    fiscal_year: fyNum,
    rows: [row],
  }

  try {
    savingConversion.value = true
    await api.put(
      `/api/settings/profit-centers/${selectedCode.value}/conversion`,
      payload,
    )

    conversionRows.value = mergeByFiscalYear(conversionRows.value, [
      { ...row, fiscal_year: fyNum },
    ])
  } catch (err) {
    console.error('Error saving conversion', err)
  } finally {
    savingConversion.value = false
  }
}

// botón del header
async function saveBoth() {
  await Promise.all([saveSeasonality(), saveConversion()])
}

/* ---------- Navegación FY (flechas) ---------- */

function prevFy() {
  const years = availableSeasonalityYears.value
  if (!years.length) return

  const minY = years[0]
  if (fy.value > minY) {
    fy.value = fy.value - 1
  }
}

function nextFy() {
  const years = availableSeasonalityYears.value
  if (!years.length) return

  const maxY = years[years.length - 1]
  if (fy.value < maxY) {
    fy.value = fy.value + 1
  }
}

/* ---------- Seasonality Trends ---------- */

const seasonalityYearOptions = computed(() =>
  availableSeasonalityYears.value.map(y => ({
    label: `WJ${y}/${String(y + 1).slice(-2)}`,
    value: y,
  })),
)

const seasonalityYearsSelected = ref([])

watch(
  [availableSeasonalityYears],
  ([years]) => {
    if (!years.length) {
      seasonalityYearsSelected.value = []
      return
    }

    seasonalityYearsSelected.value =
      (seasonalityYearsSelected.value || []).filter(y => years.includes(y))

    if (!seasonalityYearsSelected.value.length) {
      seasonalityYearsSelected.value = [years[years.length - 1]]
    }
  },
  { immediate: true },
)

/* ---------- Charts ---------- */

const seasonalityChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top' },
  },
  scales: {
    y: {
      min: 0,
      max: 15,
      ticks: {
        stepSize: 1,
        autoSkip: false,
      },
    },
  },
}

const conversionChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top' },
  },
}

const seasonalityChartData = computed(() => {
  const labels = seasonalityChartLabels.value
  const years = availableSeasonalityYears.value
  if (!years.length) {
    return { labels, datasets: [] }
  }

  const selectedYears =
    seasonalityYearsSelected.value && seasonalityYearsSelected.value.length
      ? seasonalityYearsSelected.value
      : [years[years.length - 1]]

  const palette = ['#2563EB', '#16A34A', '#DC2626', '#F59E0B', '#7C3AED', '#0891B2', '#4B5563']
  let pi = 0

  const datasets = selectedYears
    .filter(y => years.includes(y))
    .map(y => {
      const row = seasonalityRows.value.find(
        r => Number(r.fiscal_year) === Number(y),
      )
      if (!row) return null

      const data = fiscalMonths.map(m => {
        const v = row[m.field]
        return v != null ? Number(v) : 0
      })
      const color = palette[pi++ % palette.length]
      return {
        label: `WJ-${y}-${String(y + 1).slice(-2)}`,
        data,
        borderColor: color,
        backgroundColor: color,
        fill: false,
        tension: 0.3,
      }
    })
    .filter(Boolean)

  return { labels, datasets }
})

const conversionChartData = computed(() => {
  if (!conversionRows.value.length) {
    return { labels: [], datasets: [] }
  }

  const sorted = [...conversionRows.value]
    .map(r => ({ ...r, _fyNum: Number(r.fiscal_year) }))
    .filter(r => Number.isFinite(r._fyNum))
    .sort((a, b) => a._fyNum - b._fyNum)

  const labels = sorted.map(
    r => `WJ${r._fyNum}/${String(r._fyNum + 1).slice(-2)}`,
  )
  const dataM3 = sorted.map(r => {
    const v = r.factor_to_m3 ?? r.factor_m3
    return v != null ? Number(v) : 0
  })
  const dataEur = sorted.map(r => {
    const v = r.factor_to_euro ?? r.factor_eur
    return v != null ? Number(v) : 0
  })

  return {
    labels,
    datasets: [
      {
        label: 'Factor m³',
        data: dataM3,
        borderColor: '#42A5F5',
        fill: false,
        tension: 0.3,
      },
      {
        label: 'Factor €',
        data: dataEur,
        borderColor: '#FFA726',
        fill: false,
        tension: 0.3,
      },
    ],
  }
})
</script>

<style scoped>
.users-page {
  height: 100%;
}

.grid2-10 {
  display: grid;
  grid-template-columns: 2fr 10fr;
  gap: 16px;
  height: 100%;
  min-height: 0;
}

.grid12 {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 8px;
  margin-top: 0.25rem;
}

.col-8 {
  grid-column: span 8;
}

.col-4 {
  grid-column: span 4;
}

/* Pane base */
.pane {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 1px 8px rgba(0, 0, 0, .06);
  padding: 10px;
  overflow: hidden;
  box-sizing: border-box;
}

/* LEFT */
.pane.left {
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
}

.pane-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

/* Botón + links */
.btn-add {
  background-color: #111827;
  border: none;
  color: #fff;
  width: 34px;
  height: 34px;
  padding: 0;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.btn-add:focus {
  box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.4);
}

/* Lista */
.list-wrap {
  flex: 1 1 auto;
  min-height: 0;
  height: 100%;
  width: 100%;
  display: flex;
  overflow: hidden;
}

.list-wrap :deep(.p-listbox) {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
  height: 100%;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.list-wrap :deep(.p-listbox-list-container) {
  flex: 1 1 auto;
  min-height: 0;
  height: 100%;
  max-height: none !important;
  overflow-y: auto;
  overflow-x: hidden;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.list-wrap :deep(.p-listbox-list) {
  min-height: 100%;
  width: 100%;
  max-width: 100%;
}

/* Item lista */
.lb-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.lb-name {
  font-weight: 600;
  color: #111827;
  flex: 1 1 auto;
  min-width: 0;
  white-space: normal;
  word-break: break-word;
}

/* Estado */
.state-dot {
  width: 22px;
  height: 22px;
  padding: 0 !important;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.state-dot .p-tag-icon {
  font-size: .9rem;
  line-height: 1;
}

.state-dot.p-tag {
  padding: 0;
  min-width: 22px;
  min-height: 22px;
}

/* RIGHT */
.right {
  display: flex;
  flex-direction: column;
  gap: 8px;
  height: 100%;
  min-height: 0;
}

.title-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.th-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.th-right {
  display: flex;
  align-items: center;
  gap: 6px;
}

.fy-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #111827;
}

.avt {
  width: 56px;
  height: 56px;
  font-weight: 700;
}

.id-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.name-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.name {
  font-weight: 800;
  color: #111827;
  font-size: .98rem;
  line-height: 1.05;
}

.name-dot {
  width: 16px;
  height: 16px;
  min-width: 16px;
  min-height: 16px;
  padding: 0 !important;
}

.name-dot .p-tag-icon {
  font-size: .7rem;
}

.pc-edit-btn {
  margin-left: 4px;
}

/* Cards oben */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 4px 0 4px;
}

.card-header-main {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.card-title {
  font-weight: 600;
  font-size: 0.95rem;
  color: #111827;
}

.card-subtitle {
  font-size: 0.8rem;
  color: #64748b;
}

/* Seasonality */
.month-row {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 6px;
  margin-top: 0.25rem;
}

.month-field {
  width: 100%;
}

.month-input {
  width: 100%;
}

.month-input :deep(.p-inputtext) {
  width: 100%;
  text-align: center;
}

/* Conversion */
.conv-row {
  display: flex;
  flex-wrap: nowrap;
  gap: 8px;
  align-items: flex-end;
  margin-top: 0.25rem;
}

.conv-field {
  flex: 0 0 auto;
}

.conv-field-wide {
  flex: 1 1 0;
}

.conv-field-unit {
  flex: 0 0 110px;
}

.conv-field :deep(.p-inputtext),
.conv-field :deep(.p-inputnumber-input),
.conv-field :deep(.p-dropdown),
.conv-field :deep(.p-select) {
  width: 100%;
}

/* Charts */
.charts-row {
  flex: 1 1 auto;
  min-height: 0;
  grid-auto-rows: 1fr;
}

.charts-row :deep(.p-card) {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.charts-row :deep(.p-card-body),
.charts-row :deep(.p-card-content) {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
}

.chart-container {
  flex: 1 1 auto;
  min-height: 220px;
}

.chart-container :deep(canvas) {
  width: 100% !important;
  height: 100% !important;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .5rem 1rem;
}

.chart-range {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
}

.chart-range-label {
  color: #64748b;
}

.chart-year-ms {
  min-width: 200px;
}

.chart-year-ms :deep(.p-multiselect-label) {
  padding: 0.35rem 0.75rem;
  min-height: 2.2rem;
  font-size: 0.85rem;
}

.chart-year-ms :deep(.p-multiselect-token) {
  font-size: 0.8rem;
  padding: 0.15rem 0.4rem;
}

/* Dialog */
.field {
  margin-bottom: 0.9rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.text-input {
  border-radius: 4px;
  border: 1px solid #d1d5db;
  padding: 0.4rem 0.5rem;
  font-size: 0.9rem;
  outline: none;
}

.text-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.25);
}

.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}

.btn {
  border-radius: 4px;
  border: none;
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
  cursor: pointer;
}
.btn.primary {
  background: #2563eb;
  color: #fff;
}
.btn.primary:hover {
  background: #1d4ed8;
}
.btn.secondary {
  background: #e5e7eb;
  color: #111827;
}
.btn.secondary:hover {
  background: #d1d5db;
}

.ml-2 {
  margin-left: .5rem;
}
</style>
