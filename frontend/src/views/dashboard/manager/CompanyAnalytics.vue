<!-- src/views/CompanyAnalytics.vue -->
<template>
  <div class="p-3">
    <div class="grid">
      <!-- Árbol -->
      <div class="col-12 md:col-4 lg:col-3">
        <Card>
          <template #content>
            <Tree
              :value="nodes"
              :expandedKeys="expandedKeys"
              :lazy="true"
              filter
              filterMode="lenient"
              :filterBy="'label'"
              v-model:filterValue="treeFilter"
              selection-mode="single"
              class="w-full"
              @node-expand="onNodeExpand"
              @node-select="onNodeSelect"
            >
              <template #default="{ node }">
                <div class="flex align-items-center gap-2">
                  <i v-if="node.data?.type==='company'" class="pi pi-home text-primary"></i>
                  <i v-else-if="node.data?.type==='team'" class="pi pi-sitemap text-500"></i>
                  <i v-else-if="node.data?.type==='user'" class="pi pi-user"></i>
                  <i v-else-if="node.data?.type==='pc'" class="pi pi-database text-500"></i>
                  <i v-else-if="node.data?.type==='client'" class="pi pi-building"></i>
                  <span>{{ node.label }}</span>
                </div>
              </template>
            </Tree>
          </template>
        </Card>
      </div>

      <!-- Totales -->
      <div class="col-12 md:col-8 lg:col-9">
        <Card>
          <template #content>
            <template v-if="totals">
              <div class="grid mb-3">
                <div class="col-12 md:col-6">
                  <div class="flex justify-content-between align-items-center border-1 surface-border p-3 border-round surface-card">
                    <span class="text-600">Total m³</span>
                    <span class="font-bold text-xl">{{ fmt(totals.total_absolute.m3) }}</span>
                  </div>
                </div>
                <div class="col-12 md:col-6">
                  <div class="flex justify-content-between align-items-center border-1 surface-border p-3 border-round surface-card">
                    <span class="text-600">Total €</span>
                    <span class="font-bold text-xl">€ {{ fmt(totals.total_absolute.euro) }}</span>
                  </div>
                </div>
              </div>

              <DataTable :value="totals.totals_by_pc" dataKey="profit_center_code" size="small" stripedRows>
                <Column field="profit_center_code" header="Profit Center" />
                <Column header="m³">
                  <template #body="{ data }">{{ fmt(data.total_m3) }}</template>
                </Column>
                <Column header="€">
                  <template #body="{ data }">€ {{ fmt(data.total_euro) }}</template>
                </Column>
              </DataTable>
            </template>

            <template v-else>
              <div class="text-600">Seleccioná un nodo del árbol para ver totales.</div>
            </template>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Card from 'primevue/card'
import Tree from 'primevue/tree'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import api from '@/plugins/axios'

const nodes = ref([])
const expandedKeys = ref({})
const treeFilter = ref('')
const totals = ref(null)

function toNode(item){
  return {
    key: item.id,                 // p.ej.: company_main | team_1 | user_7 | pc_X_u7 | client_123_pcX_u7
    label: item.label,
    leaf: !item.has_children,
    data: { type: item.type, ...(item.meta || {}) }
  }
}

async function loadRoot(){
  const { data } = await api.get('/api/analytics/tree', { params: { node_id: 'root' } })
  nodes.value = (data || []).map(toNode)
}

async function loadChildren(nodeKey){
  const { data } = await api.get('/api/analytics/tree', { params: { node_id: nodeKey } })
  return (data || []).map(toNode)
}

async function onNodeExpand(evt){
  const node = evt?.node
  if (!node) return
  if (!node.children) {
    node.children = await loadChildren(node.key)
  }
  expandedKeys.value = { ...expandedKeys.value, [node.key]: true }
}

async function onNodeSelect(evt){
  const node = evt?.node
  if (!node) return
  totals.value = null // sin loader: limpiamos y luego mostramos
  const { data } = await api.get('/api/analytics/totals', { params: { node_id: node.key } })
  totals.value = data
}

onMounted(async () => {
  await loadRoot()
  // expandir la compañía automáticamente para mostrar Teams
  const company = nodes.value?.[0]
  if (company) {
    company.children = await loadChildren(company.key)
    expandedKeys.value = { [company.key]: true }
  }
})

function fmt(v){ return Number(v||0).toLocaleString(undefined, { maximumFractionDigits: 2 }) }
</script>