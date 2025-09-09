<template>
  <div class="pc-report p-2">
    <!-- HEADER con Zurück y línea; filtros a la derecha -->
    <Card class="mb-2 header-card">
      <template #content>
        <div class="hdr">
          <div class="hdr-left">
            <a href="#" class="back-link" @click="goBack">&lt;- Zurück</a>
            <span class="vbar" aria-hidden="true"></span>
            <ul class="crumb">
              <li><span class="c1">Anwendung</span></li>
              <li class="sep"><i class="pi pi-angle-right" /></li>
              <li><span class="c2">Zusatzquoten Analyse</span></li>
            </ul>
          </div>
          <div class="hdr-right">
            <!-- Año fiscal -->
            <div class="ctrl">
              <Button icon="pi pi-angle-left" text @click="prevFY" :disabled="fyStart<=2024" />
              <span class="ctrl-text">WJ {{ fyStart }}/{{ String(fyStart+1).slice(-2) }}</span>
              <Button icon="pi pi-angle-right" text @click="nextFY" :disabled="fyStart>=currentFYStart" />
            </div>
            <!-- Mes (as-of) -->
            <div class="ctrl">
              <Button icon="pi pi-angle-left" text @click="prevAsOf" :disabled="asOfIdx<=0" />
              <span class="ctrl-text">Stand: {{ asOfLabel }}</span>
              <Button icon="pi pi-angle-right" text @click="nextAsOf" :disabled="asOfIdx>=11" />
            </div>
            <!-- Unidad -->
            <div class="ctrl">
              <SelectButton
                v-model="unit"
                :options="unitOptions"
                optionLabel="label"
                optionValue="value"
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

    <!-- CONTENIDO EXPORTABLE (sin fondo blanco en pantalla) -->
    <div ref="pdfRef" class="pdf-scope" :class="{ 'force-light': forceLight }">
      <!-- Cabecera visible en PDF -->
      <div class="pdf-header">
        <div class="pdf-title">Vetriebsprognose nach Profit Center</div>
        <div class="pdf-meta">
          Einheit: <strong>{{ unitLabel }}</strong>
          &nbsp;·&nbsp; Profit-Center: <strong>{{ pcLabel }}</strong>
          &nbsp;·&nbsp; WJ: <strong>{{ fyStart }}/{{ String(fyStart+1).slice(-2) }}</strong>
          &nbsp;·&nbsp; Stand: <strong>{{ asOfLabel }}</strong>
          &nbsp;·&nbsp; Erstellt: <strong>{{ createdAt }}</strong>
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

    <!-- Acciones -->
    <div class="actions mt-2">
      <Button label="Als PDF exportieren" icon="pi pi-file-pdf" severity="danger" @click="exportPDF" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import SelectButton from 'primevue/selectbutton'
import Card from 'primevue/card'
import PcOverview from '@/components/analytics/PcOverview.vue'
import api from '@/plugins/axios'

const router = useRouter()

/* Unidad */
const unit = ref('m3')
const unitOptions = [
  { label:'m³', value:'m3' },
  { label:'€',  value:'euro' },
  { label:'VK-EH', value:'units' },
]
const unitLabel = computed(() => unitOptions.find(o=>o.value===unit.value)?.label || '')

/* FY */
const now = new Date()
const initialFYStart = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
const currentFYStart = initialFYStart
const fyStart = ref(initialFYStart)

/* PCs */
const pcOptions = ref([]) // [{code,name}]
const pcCode = ref('')
const pcLabel = computed(() => pcOptions.value.find(p=>p.code===pcCode.value)?.name || pcCode.value || '–')

/* AS-OF (índice 0..11 Apr..Mar) */
const asOfIdx = ref(0)
const monthsInFY = computed(() => {
  const y0 = fyStart.value
  const L = []
  const map = {4:'Apr',5:'Mai',6:'Jun',7:'Jul',8:'Aug',9:'Sep',10:'Okt',11:'Nov',12:'Dez',1:'Jan',2:'Feb',3:'Mär'}
  const push = (Y,m) => L.push({ Y, m, label: `${map[m] || m} ${Y}` })
  ;[4,5,6,7,8,9,10,11,12].forEach(m => push(y0, m))
  ;[1,2,3].forEach(m => push(y0+1, m))
  return L
})
const asOfLabel = computed(() => monthsInFY.value[asOfIdx.value]?.label || '')
const asOfValue = computed(() => {
  const obj = monthsInFY.value[asOfIdx.value]
  return obj ? `${obj.Y}-${String(obj.m).padStart(2,'0')}` : null
})

function prevFY(){ if (fyStart.value > 2024) { fyStart.value--; adjustDefaultAsOf() } }
function nextFY(){ if (fyStart.value < currentFYStart) { fyStart.value++; adjustDefaultAsOf() } }
function prevAsOf(){ if (asOfIdx.value > 0) asOfIdx.value-- }
function nextAsOf(){ if (asOfIdx.value < 11) asOfIdx.value++ }

function adjustDefaultAsOf(){
  const d = new Date()
  const curFY = d.getMonth() >= 3 ? d.getFullYear() : d.getFullYear() - 1
  if (fyStart.value === curFY) {
    const last = new Date(d.getFullYear(), d.getMonth(), 0)
    const y = last.getFullYear(), m = last.getMonth()+1
    const idx = monthsInFY.value.findIndex(o => o.Y===y && o.m===m)
    asOfIdx.value = idx >= 0 ? idx : 11
  } else {
    asOfIdx.value = 11
  }
}

async function loadPcList(){
  const { data } = await api.get('/api/analytics/pc/list')
  pcOptions.value = (data||[]).map(r=>({ code:r.code, name:r.name }))
  if (!pcCode.value && pcOptions.value.length) pcCode.value = pcOptions.value[0].code
}

function goBack(){ router.back() }

/* PDF: SIEMPRE CLARO + APaisado (landscape) */
const forceLight = ref(false)
const createdAt = computed(() => {
  const d = new Date()
  const pad = n => String(n).padStart(2,'0')
  return `${pad(d.getDate())}.${pad(d.getMonth()+1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`
})
async function exportPDF(){
  const { jsPDF } = await import('jspdf')
  const html2canvas = (await import('html2canvas')).default
  const el = document.querySelector('.pdf-scope')
  if (!el) return

  // Forzar tema claro dentro del área exportada
  forceLight.value = true
  await nextTick()

  const canvas = await html2canvas(el, {
    scale: 2,
    useCORS: true,
    backgroundColor: '#ffffff' // fondo blanco SIEMPRE en PDF
  })

  // Volver al tema normal en pantalla
  forceLight.value = false
  await nextTick()

  const pdf = new jsPDF({ orientation:'l', unit:'mm', format:'a4' }) // APaisado
  const pageW = pdf.internal.pageSize.getWidth()
  const pageH = pdf.internal.pageSize.getHeight()

  const imgW = pageW
  const imgH = (canvas.height * imgW) / canvas.width

  let y = 0
  while (y < imgH) {
    const srcY = (y / imgH) * canvas.height
    const srcH = Math.min(((pageH / imgH) * canvas.height), canvas.height - srcY)
    const pageCanvas = document.createElement('canvas')
    pageCanvas.width = canvas.width
    pageCanvas.height = srcH
    const ctx = pageCanvas.getContext('2d')
    ctx.drawImage(canvas, 0, srcY, canvas.width, srcH, 0, 0, canvas.width, srcH)
    const pageImg = pageCanvas.toDataURL('image/jpeg', 0.95)
    if (y > 0) pdf.addPage('a4', 'l')
    pdf.addImage(pageImg, 'JPEG', 0, 0, imgW, (pageCanvas.height * imgW) / pageCanvas.width)
    y += pageH
  }

  const fname = `PC_${pcLabel.value || 'Report'}_WJ_${fyStart.value}-${String(fyStart.value+1).slice(-2)}_asof_${asOfValue.value||''}_${unit.value}_LANDSCAPE.pdf`
  pdf.save(fname)
}

onMounted(async () => {
  const params = new URLSearchParams(location.search)
  const qp = params.get('pc')
  const qfy = Number(params.get('fy'))
  const qa  = params.get('as_of')
  const qu  = params.get('unit')
  if (qp) pcCode.value = qp
  if (qfy && qfy >= 2024) fyStart.value = qfy
  if (qu && ['m3','euro','units'].includes(qu)) unit.value = qu

  await loadPcList()
  adjustDefaultAsOf()
  if (qa && /^\d{4}-\d{2}$/.test(qa)) {
    const [Y,M] = qa.split('-').map(Number)
    const idx = monthsInFY.value.findIndex(o => o.Y===Y && o.m===M)
    if (idx >= 0) asOfIdx.value = idx
  }
})
watch(fyStart, adjustDefaultAsOf)
</script>

<style scoped>
.header-card :deep(.p-card-content){ padding: 0px .25rem !important; }
.hdr{ display:flex; align-items:center; gap:.75rem; }
.hdr-left{ display:flex; align-items:center; gap:.5rem; border-right:1px solid rgba(0,0,0,.08); padding-right:.75rem; }
.back-link{ color:#10b981; font-weight:300; text-decoration:none; letter-spacing:.1px; }
.vbar{ width:2px; height:16px; background:#fff; opacity:.9; border-radius:1px; }
.crumb{ list-style:none; padding:0; margin:0; display:flex; align-items:center; gap:8px; font-weight:700; }
.c1{ color:#64748b; } .c2{ color:#0f172a; } .sep{ color:#64748b; }
@media (prefers-color-scheme: dark){ .c1{ color:#94a3b8; } .c2{ color:#e5e7eb; } .sep{ color:#94a3b8; } }
.hdr-title{ font-weight:700; font-size:1.1rem; }
.hdr-right{ margin-left:auto; display:flex; align-items:center; gap:.75rem; }
.ctrl{ display:flex; align-items:center; gap:.35rem; }
.ctrl-text{ min-width: 130px; text-align:center; font-weight:700; }
.empty{ color:#64748b; padding:1rem 0.5rem; display:flex; align-items:center; }

/* Área exportable: SIN fondo en pantalla */
.pdf-scope{ padding:6px; background: transparent; }

/* Forzar tema claro SOLO al exportar */
.pdf-scope.force-light{
  background: #ffffff !important;
  color: #111827 !important;
  /* Tokens básicos si tu UI usa variables */
  --surface-card: #ffffff;
  --text-color: #111827;
  --text-color-secondary: #475569;
  --surface-border: #e5e7eb;
}

.pdf-header{ margin-bottom: .5rem; }
.pdf-title{ font-weight:800; font-size:1.1rem; margin-bottom:.25rem; color: var(--text-color);}
.pdf-meta{ color: var(--text-color-secondary); font-size:.9rem; }

.actions{ display:flex; justify-content:flex-end; }
</style>