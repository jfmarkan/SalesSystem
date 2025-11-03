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
              label="Generar PDF"
              icon="pi pi-file-pdf"
              severity="danger"
              @click="exportPDF"
            />
            <Button
              label="Generar Informe"
              icon="pi pi-copy"
              severity="info"
              @click="exportAllReports"
            />

            <!-- Unidad -->
            <SelectButton
              v-model="unit"
              :options="unitOptions"
              optionLabel="label"
              optionValue="value"
            />

            <!-- Año fiscal -->
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

            <!-- Mes -->
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
    <div ref="pdfRef" class="pdf-scope" :class="{ 'force-light': forceLight }">
      <div class="pdf-header">
        <div class="pdf-title">Vertriebsprognose nach Profit Center</div>
        <div class="pdf-meta">
          Einheit: <strong>{{ unitLabel }}</strong> · Profit-Center:
          <strong>{{ pcLabel }}</strong> · WJ:
          <strong>{{ fyStart }}/{{ String(fyStart + 1).slice(-2) }}</strong> ·
          Stand: <strong>{{ asOfLabel }}</strong> · Erstellt:
          <strong>{{ createdAt }}</strong>
        </div>
      </div>

      <PcOverview
        v-if="pcCode"
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
import { ref, onMounted, watch, computed, nextTick } from 'vue'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import SelectButton from 'primevue/selectbutton'
import Card from 'primevue/card'
import PcOverview from '@/components/analytics/PcOverview.vue'
import api from '@/plugins/axios'
import { jsPDF } from 'jspdf'
import html2canvas from 'html2canvas'

/* Unidad */
const unit = ref('m3')
const unitOptions = [
  { label: 'm³', value: 'm3' },
  { label: '€', value: 'euro' },
  { label: 'VK-EH', value: 'units' },
]
const unitLabel = computed(() => unitOptions.find(o => o.value === unit.value)?.label || '')

/* Año fiscal */
const now = new Date()
const initialFYStart = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
const currentFYStart = initialFYStart
const fyStart = ref(initialFYStart)

/* Profit Centers */
const pcOptions = ref([])
const pcCode = ref('')
const pcLabel = computed(() =>
  pcOptions.value.find(p => p.code === pcCode.value)?.name || pcCode.value || '–'
)

/* As-Of (Stand) */
const asOfIdx = ref(0)
const monthsInFY = computed(() => {
  const y0 = fyStart.value
  const map = {
    4: 'Apr', 5: 'Mai', 6: 'Jun', 7: 'Jul', 8: 'Aug',
    9: 'Sep', 10: 'Okt', 11: 'Nov', 12: 'Dez', 1: 'Jan', 2: 'Feb', 3: 'Mär'
  }
  const arr = []
  ;[4,5,6,7,8,9,10,11,12].forEach(m => arr.push({ Y: y0, m, label: `${map[m]} ${y0}` }))
  ;[1,2,3].forEach(m => arr.push({ Y: y0 + 1, m, label: `${map[m]} ${y0 + 1}` }))
  return arr
})
const asOfLabel = computed(() => monthsInFY.value[asOfIdx.value]?.label || '')
const asOfValue = computed(() => {
  const obj = monthsInFY.value[asOfIdx.value]
  return obj ? `${obj.Y}-${String(obj.m).padStart(2, '0')}` : null
})

/* Ajuste de FY y Mes */
function prevFY() { if (fyStart.value > 2024) fyStart.value-- }
function nextFY() { if (fyStart.value < currentFYStart) fyStart.value++ }
function prevAsOf() { if (asOfIdx.value > 0) asOfIdx.value-- }
function nextAsOf() { if (asOfIdx.value < 11) asOfIdx.value++ }

/* Cargar lista de PCs */
async function loadPcList() {
  const { data } = await api.get('/api/analytics/pc/list')
  pcOptions.value = (data || []).map(r => ({ code: r.code, name: r.name }))
  if (!pcCode.value && pcOptions.value.length) pcCode.value = pcOptions.value[0].code
}

/* PDF handling */
const forceLight = ref(false)
const createdAt = computed(() => {
  const d = new Date()
  return `${d.toLocaleDateString('de-DE')} ${d.toLocaleTimeString().slice(0,5)}`
})
async function exportPDF() {
  if (!pcCode.value) return
  await renderAndSavePDF([pcCode.value], `PC_${pcLabel.value}`)
}
async function exportAllReports() {
  if (!pcOptions.value.length) await loadPcList()
  const codes = pcOptions.value.map(p => p.code)
  await renderAndSavePDF(codes, `Reporte_TodosPCs`)
}

async function renderAndSavePDF(pcList, filename) {
  const pdf = new jsPDF({ orientation: 'l', unit: 'mm', format: 'a4' })
  for (let i = 0; i < pcList.length; i++) {
    const code = pcList[i]
    pcCode.value = code
    await nextTick()
    forceLight.value = true
    await nextTick()
    const el = document.querySelector('.pdf-scope')
    const canvas = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' })
    forceLight.value = false
    if (i > 0) pdf.addPage('a4', 'l')
    const img = canvas.toDataURL('image/jpeg', 0.95)
    const pageW = pdf.internal.pageSize.getWidth()
    const imgH = (canvas.height * pageW) / canvas.width
    pdf.addImage(img, 'JPEG', 0, 0, pageW, imgH)
  }
  pdf.save(`${filename}_${unit.value}.pdf`)
}

/* 🔹 Selección automática del último mes completo */
function setLastCompleteMonth() {
  const today = new Date()
  // mes anterior al actual
  const prevMonth = today.getMonth() === 0 ? 12 : today.getMonth()
  const prevYear = today.getMonth() === 0 ? today.getFullYear() - 1 : today.getFullYear()

  // buscar ese mes en monthsInFY
  const idx = monthsInFY.value.findIndex(o => o.Y === prevYear && o.m === prevMonth)
  if (idx >= 0) asOfIdx.value = idx
  else asOfIdx.value = monthsInFY.value.length - 1 // fallback último
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
