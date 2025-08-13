<script setup>
/* All comments in English */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const props = defineProps({
  /* Route to full Orders page */
  moreTo: { type: String, default: '/app/orders' }
})

const router = useRouter()

/* Mock data. Replace with Laravel fetch */
const orders = ref([
  { id: 1001, kunde: 'Kunde A', eingang: '2025-08-10', status: 'abierta',
    items: [{ sku:'SKU-001', desc:'Produkt 1', qty:10, price:12.5 }, { sku:'SKU-002', desc:'Produkt 2', qty:4, price:40 }] },
  { id: 1002, kunde: 'Kunde B', eingang: '2025-08-09', status: 'cumplida',
    items: [{ sku:'SKU-010', desc:'Produkt X', qty:2, price:150 }] },
  { id: 1003, kunde: 'Kunde C', eingang: '2025-08-08', status: 'cancelada',
    items: [{ sku:'SKU-123', desc:'Service Y', qty:1, price:300 }] },
  { id: 1004, kunde: 'Kunde A', eingang: '2025-08-07', status: 'demorada',
    items: [{ sku:'SKU-777', desc:'Produkt Z', qty:6, price:22 }] }
])

const visible = ref(false)
const selected = ref(null)

/* Open modal with order details */
function openDetail(order){ selected.value = order; visible.value = true }
/* Navigate to full Orders section */
function goMore(){ router.push(props.moreTo) }

/* Map status to fixed color classes to avoid theme overrides */
function statusClass(s){
  const t = String(s).toLowerCase()
  if (t === 'abierta')   return 'tag-open'   // green
  if (t === 'cumplida')  return 'tag-done'   // gray
  if (t === 'cancelada') return 'tag-cancel' // red
  if (t === 'demorada')  return 'tag-warn'   // yellow
  return 'tag-open'
}
/* German label */
function statusLabelDE(s){
  const t = String(s).toLowerCase()
  if (t === 'abierta')   return 'Offen'
  if (t === 'cumplida')  return 'Erfüllt'
  if (t === 'cancelada') return 'Storniert'
  if (t === 'demorada')  return 'Verzögert'
  return ''
}

/* Totals for modal table */
const totalItems = computed(()=> selected.value ? selected.value.items.reduce((a,it)=>a+it.qty,0) : 0)
const totalImporte = computed(()=> selected.value ? selected.value.items.reduce((a,it)=>a+it.qty*it.price,0) : 0)
</script>

<template>
  <div class="order-list">
    <DataTable
      :value="orders"
      responsiveLayout="scroll"
      class="p-datatable-sm glass-table"
      :rows="10"
      :paginator="false"
    >
      <!-- Kunde -->
      <Column field="kunde" header="Kunde" :style="{ minWidth:'180px', textAlign:'center' }" />

      <!-- Eingangsdatum -->
      <Column field="eingang" header="Eingangsdatum" :style="{ minWidth:'150px', textAlign:'center' }">
        <template #body="{ data }">
          <span>{{ data.eingang }}</span>
        </template>
      </Column>

      <!-- Status (custom pill colors, independent of theme) -->
      <Column header="Status" :style="{ minWidth:'140px', textAlign:'center' }">
        <template #body="{ data }">
          <Tag :value="statusLabelDE(data.status)" :class="['pill', statusClass(data.status)]" />
        </template>
      </Column>

      <!-- Details (icon only) -->
      <Column header="Details" :style="{ minWidth:'120px', textAlign:'center' }">
        <template #body="{ data }">
          <Button icon="pi pi-search" rounded text @click="openDetail(data)" />
        </template>
      </Column>
    </DataTable>

    <!-- Card footer -->
    <div class="footer-actions">
      <Button label="Mehr anzeigen" icon="pi pi-arrow-right" @click="goMore" />
    </div>

    <!-- Modal with blurred backdrop and glass body -->
    <Dialog
      v-model:visible="visible"
      :modal="true"
      :draggable="false"
      :dismissableMask="true"
      :style="{ width: '720px', maxWidth: '95vw' }"
      :breakpoints="{ '960px':'90vw', '640px':'96vw' }"
      :header="selected ? `Bestellung #${selected.id} — ${selected.kunde}` : 'Bestelldetails'"
      class="glass-dialog"
    >
      <div v-if="selected">
        <div class="mb-3 text-500 text-sm">
          Eingangsdatum: <span class="text-900">{{ selected.eingang }}</span>
        </div>

        <div class="modal-table-shell">
          <table class="modal-table">
            <thead>
              <tr>
                <th>Artikelnummer</th>
                <th>Beschreibung</th>
                <th class="num">Menge</th>
                <th class="num">Preis</th>
                <th class="num">Betrag</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(it, i) in selected.items" :key="i">
                <td>{{ it.sku }}</td>
                <td>{{ it.desc }}</td>
                <td class="num">{{ it.qty }}</td>
                <td class="num">{{ it.price.toFixed(2) }}</td>
                <td class="num">{{ (it.qty * it.price).toFixed(2) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2" class="total">Summen</td>
                <td class="num total">{{ totalItems }}</td>
                <td class="num total">—</td>
                <td class="num total">{{ totalImporte.toFixed(2) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="mt-3 flex justify-content-end">
          <Tag :value="statusLabelDE(selected.status)" :class="['pill', statusClass(selected.status)]" />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
/* Transparent table and centered text */
:deep(.glass-table.p-datatable),
:deep(.glass-table .p-datatable-wrapper),
:deep(.glass-table .p-datatable-header){
  background: transparent;
  border: 0;
}
:deep(.glass-table .p-datatable-thead > tr > th),
:deep(.glass-table .p-datatable-tbody > tr > td){
  background: transparent;
  text-align: center;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
:deep(.glass-table .p-datatable-tbody > tr:hover > td){
  background: rgba(255,255,255,0.12);
}

/* Fixed-color pills independent of theme */
:deep(.p-tag.pill){
  border-radius: 9999px;
  padding: 0.15rem 0.5rem;
  font-weight: 600;
  font-size: .75rem;
  border: 1px solid transparent;
}
:deep(.p-tag.pill.tag-open){   /* green */
  background: rgba(22,163,74,.18);
  color: #166534;
  border-color: rgba(22,163,74,.28);
}
:deep(.p-tag.pill.tag-done){   /* gray */
  background: rgba(107,114,128,.18);
  color: #374151;
  border-color: rgba(107,114,128,.28);
}
:deep(.p-tag.pill.tag-cancel){ /* red */
  background: rgba(239,68,68,.18);
  color: #991b1b;
  border-color: rgba(239,68,68,.28);
}
:deep(.p-tag.pill.tag-warn){   /* yellow */
  background: rgba(245,158,11,.22);
  color: #92400e;
  border-color: rgba(245,158,11,.30);
}

/* Footer button */
.footer-actions{
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}

/* Mask blur */
:deep(.p-dialog-mask){
  background: rgba(0,0,0,0.25);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

/* Glass dialog */
:deep(.glass-dialog.p-dialog){
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0,0,0,0.35);
}
:deep(.glass-dialog .p-dialog-header){
  background: rgba(255,255,255,0.55);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
:deep(.glass-dialog .p-dialog-content){
  background: rgba(255,255,255,0.38);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

/* Modal inner table */
.modal-table-shell{ overflow-x: auto; border-radius: 8px; }
.modal-table{
  width: 100%;
  min-width: 560px;
  border-collapse: separate;
  border-spacing: 0;
}
.modal-table thead th{
  position: sticky; top: 0; z-index: 1;
  background: rgba(255,255,255,0.35);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  text-align: left;
  padding: 8px 10px;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.modal-table tbody td{
  background: rgba(255,255,255,0.18);
  padding: 8px 10px;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.modal-table tbody tr:hover td{ background: rgba(255,255,255,0.26); }
.modal-table tfoot td{
  background: rgba(255,255,255,0.28);
  padding: 10px;
  font-weight: 600;
  border-top: 1px solid rgba(0,0,0,0.06);
}
.modal-table .num{ text-align: right; }
.modal-table .total{ font-weight: 600; }
</style>