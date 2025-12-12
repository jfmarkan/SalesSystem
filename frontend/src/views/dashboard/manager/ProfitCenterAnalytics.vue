<template>
  <div class="pc-report p-2">
    <!-- HEADER -->
    <Card class="mb-2 header-card">
      <template #content>
        <div class="hdr">
          <div class="hdr-left">
            <div class="hdr-title">Zusatzquoten Analyse</div>
          </div>

          <div class="hdr-right">
            <!-- 🔘 Botones principales -->
            <Button
              label="PDF erzeugen"
              icon="pi pi-file-pdf"
              severity="danger"
              @click="exportPDF"
            />
            <Button
              label="Bericht (alle Profit-Center)"
              icon="pi pi-copy"
              severity="info"
              @click="exportAllReports"
            />

            <!-- Einheit -->
            <SelectButton
              v-model="unit"
              :options="unitOptions"
              optionLabel="label"
              optionValue="value"
            />

            <!-- Geschäftsjahr -->
            <div class="ctrl">
              <Button
                icon="pi pi-angle-left"
                text
                @click="prevFY"
                :disabled="fyStart <= 2024"
              />
              <span class="ctrl-text">
                WJ {{ fyStart }}/{{ String(fyStart + 1).slice(-2) }}
              </span>
              <Button
                icon="pi pi-angle-right"
                text
                @click="nextFY"
                :disabled="fyStart >= currentFYStart"
              />
            </div>

            <!-- Stichtag (Monat) -->
            <div class="ctrl">
              <Button
                icon="pi pi-angle-left"
                text
                @click="prevAsOf"
                :disabled="asOfIdx <= 0"
              />
              <span class="ctrl-text">Stand: {{ asOfLabel }}</span>
              <Button
                icon="pi pi-angle-right"
                text
                @click="nextAsOf"
                :disabled="asOfIdx >= 11"
              />
            </div>

            <!-- Profit-Center -->
            <Dropdown
              v-model="pcCode"
              :options="pcOptions"
              optionLabel="name"
              optionValue="code"
              placeholder="Profit-Center wählen"
              class="w-20rem"
              showClear
              :filter="true"
              filterPlaceholder="Suchen…"
            />
          </div>
        </div>
      </template>
    </Card>

    <!-- CONTENIDO EXPORTABLE -->
    <div
      ref="pdfRef"
      class="pdf-scope"
      :class="{ 'force-light': forceLight }"
    >
      <div class="pdf-header">
        <div class="pdf-title">Vertriebsprognose nach Profit Center</div>
        <div class="pdf-meta">
          Einheit: <strong>{{ unitLabel }}</strong>
          · Ebene:
          <strong>{{ pcLabel }}</strong>
          · WJ:
          <strong>{{ fyStart }}/{{ String(fyStart + 1).slice(-2) }}</strong>
          · Stand: <strong>{{ asOfLabel }}</strong> · Erstellt:
          <strong>{{ createdAt }}</strong>
        </div>
      </div>

      <!-- 🔹 Gesamtunternehmen -->
      <CompanyOverview
        v-if="pcCode === 'company_main'"
        ref="companyOverviewRef"
        :fiscalYearStart="fyStart"
        :asOf="asOfValue"
        :unit="unit"
      />

      <!-- 🔹 Profit-Center einzel -->
      <PcOverview
        v-else-if="pcCode"
        ref="pcOverviewRef"
        :profitCenterCode="pcCode"
        :fiscalYearStart="fyStart"
        :asOf="asOfValue"
        :unit="unit"
      />

      <div v-else class="empty">
        <i class="pi pi-info-circle mr-2"></i>
        Bitte wählen Sie ein Profit-Center.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick, provide } from 'vue'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import SelectButton from 'primevue/selectbutton'
import Card from 'primevue/card'
import PcOverview from '@/components/analytics/PcOverview.vue'
import CompanyOverview from '@/components/analytics/CompanyOverview.vue'
import api from '@/plugins/axios'
import { jsPDF } from 'jspdf'
import html2canvas from 'html2canvas'

/* refs a hijos y al contenedor */
const pcOverviewRef = ref(null)
const companyOverviewRef = ref(null)
const pdfRef = ref(null)

/* 🔒 flag para desactivar animaciones durante export */
const isPdfExporting = ref(false)
provide('isPdfExporting', isPdfExporting)

/* Unidad */
const unit = ref('m3')
const unitOptions = [
  { label: 'm³', value: 'm3' },
  { label: '€', value: 'euro' },
  { label: 'VK-EH', value: 'units' },
]
const unitLabel = computed(
  () => unitOptions.find(o => o.value === unit.value)?.label || '',
)

/* Año fiscal (Abr–März) */
const now = new Date()
const initialFYStart =
  now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
const currentFYStart = initialFYStart
const fyStart = ref(initialFYStart)

/* Profit Centers */
const pcOptions = ref([])
const pcCode = ref('')

const pcLabel = computed(() => {
  if (pcCode.value === 'company_main') return 'Gesamtunternehmen'
  return (
    pcOptions.value.find(p => p.code === pcCode.value)?.name ||
    pcCode.value ||
    '–'
  )
})

/* As-Of (Stand, meses Abril–März) */
const asOfIdx = ref(0)
const monthsInFY = computed(() => {
  const y0 = fyStart.value
  const map = {
    4: 'Apr',
    5: 'Mai',
    6: 'Jun',
    7: 'Jul',
    8: 'Aug',
    9: 'Sep',
    10: 'Okt',
    11: 'Nov',
    12: 'Dez',
    1: 'Jan',
    2: 'Feb',
    3: 'Mär',
  }
  const arr = []
  ;[4, 5, 6, 7, 8, 9, 10, 11, 12].forEach(m =>
    arr.push({ Y: y0, m, label: `${map[m]} ${y0}` }),
  )
  ;[1, 2, 3].forEach(m =>
    arr.push({ Y: y0 + 1, m, label: `${map[m]} ${y0 + 1}` }),
  )
  return arr
})
const asOfLabel = computed(
  () => monthsInFY.value[asOfIdx.value]?.label || '',
)
const asOfValue = computed(() => {
  const obj = monthsInFY.value[asOfIdx.value]
  return obj ? `${obj.Y}-${String(obj.m).padStart(2, '0')}` : null
})

function prevFY() {
  if (fyStart.value > 2024) fyStart.value--
}
function nextFY() {
  if (fyStart.value < currentFYStart) fyStart.value++
}
function prevAsOf() {
  if (asOfIdx.value > 0) asOfIdx.value--
}
function nextAsOf() {
  if (asOfIdx.value < 11) asOfIdx.value++
}

/* Cargar lista de PCs y agregar "Gesamtunternehmen" */
async function loadPcList() {
  const { data } = await api.get('/api/analytics/pc/list')
  const pcs = (data || []).map(r => ({ code: r.code, name: r.name }))

  pcOptions.value = [
    { code: 'company_main', name: 'Gesamtunternehmen' },
    ...pcs,
  ]

  if (!pcCode.value && pcOptions.value.length) {
    pcCode.value = pcOptions.value[0].code
  }
}

/* PDF handling */
const forceLight = ref(false)
const createdAt = computed(() => {
  const d = new Date()
  return `${d.toLocaleDateString('de-DE')} ${d
    .toLocaleTimeString('de-DE')
    .slice(0, 5)}`
})

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms))
}

/* Backup por si el hijo no expone waitUntilReady */
async function waitForFullRender() {
  await nextTick()
  await nextTick()
  for (let i = 0; i < 2; i++) {
    await new Promise(resolve =>
      requestAnimationFrame(() => resolve()),
    )
  }
  await sleep(200)
}

async function exportPDF() {
  if (!pcCode.value) return
  await renderAndSavePDF([pcCode.value], `Ebene_${pcLabel.value}`)
}

async function exportAllReports() {
  if (!pcOptions.value.length) await loadPcList()

  // 👉 Empresa primero, luego todos los demás PCs
  const codes = [
    'company_main',
    ...pcOptions.value
      .filter(p => p.code !== 'company_main')
      .map(p => p.code),
  ]

  if (!codes.length) return

  await renderAndSavePDF(codes, 'Bericht_Alle_ProfitCenter')
}

async function renderAndSavePDF(pcList, filenameBase) {
  const pdf = new jsPDF({ orientation: 'l', unit: 'mm', format: 'a4' })

  const originalPc = pcCode.value
  const originalForceLight = forceLight.value

  // 🧊 Desactivar animaciones en los hijos durante TODO el export
  isPdfExporting.value = true
  await nextTick()

  try {
    for (let i = 0; i < pcList.length; i++) {
      const code = pcList[i]
      pcCode.value = code

      await nextTick()

      // 👉 Elegimos el hijo correcto según el tipo de “Ebene”
      const child =
        pcCode.value === 'company_main'
          ? companyOverviewRef.value
          : pcOverviewRef.value

      // Primero intentamos sincronizarnos con el hijo
      if (child && typeof child.waitUntilReady === 'function') {
        await child.waitUntilReady()
      } else {
        // Si no implementó el hook, usamos el backup genérico
        await waitForFullRender()
      }

      // Forzamos tema claro para la captura
      forceLight.value = true
      await waitForFullRender()

      const el = pdfRef.value
      if (!el) continue

      const canvas = await html2canvas(el, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      })

      forceLight.value = false

      if (i > 0) pdf.addPage('a4', 'l')

      const img = canvas.toDataURL('image/jpeg', 0.95)
      const pageW = pdf.internal.pageSize.getWidth()
      const imgH = (canvas.height * pageW) / canvas.width
      pdf.addImage(img, 'JPEG', 0, 0, pageW, imgH)
    }

    pdf.save(`${filenameBase}_${unit.value}.pdf`)
  } finally {
    pcCode.value = originalPc
    forceLight.value = originalForceLight
    isPdfExporting.value = false
    await nextTick()
  }
}

/* 🔹 Selección automática del último mes completo */
function setLastCompleteMonth() {
  const today = new Date()
  const prevMonth = today.getMonth() === 0 ? 12 : today.getMonth()
  const prevYear =
    today.getMonth() === 0
      ? today.getFullYear() - 1
      : today.getFullYear()

  const idx = monthsInFY.value.findIndex(
    o => o.Y === prevYear && o.m === prevMonth,
  )
  if (idx >= 0) asOfIdx.value = idx
  else asOfIdx.value = monthsInFY.value.length - 1
}

onMounted(async () => {
  await loadPcList()
  setLastCompleteMonth()
})
</script>

<style scoped>
.header-card :deep(.p-card-content) {
  padding: 0.5rem !important;
}
.hdr {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}
.hdr-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.hdr-title {
  font-weight: 700;
  font-size: 1.25rem;
}
.hdr-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.ctrl {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.ctrl-text {
  min-width: 130px;
  text-align: center;
  font-weight: 700;
}
.empty {
  color: #64748b;
  padding: 1rem 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Export PDF área */
.pdf-scope {
  padding: 6px;
  background: transparent;
}
.pdf-scope.force-light {
  background: #ffffff !important;
  color: #111827 !important;
  --surface-card: #ffffff;
  --text-color: #111827;
  --text-color-secondary: #475569;
  --surface-border: #e5e7eb;
}
.pdf-header {
  margin-bottom: 0.5rem;
}
.pdf-title {
  font-weight: 800;
  font-size: 1.1rem;
  margin-bottom: 0.25rem;
  color: var(--text-color);
}
.pdf-meta {
  color: var(--text-color-secondary);
  font-size: 0.9rem;
}
</style>
