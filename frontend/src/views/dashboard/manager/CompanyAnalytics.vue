<template>
  <div class="p-2">
    <div class="grid">
      <!-- Árbol (2 cols) -->
      <div class="col-12 md:col-2">
        <Card>
          <template #content>
            <div class="tree-wrap p-2">
              <Tree
                :value="nodes"
                :expandedKeys="expandedKeys"
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
            </div>
          </template>
        </Card>
      </div>

      <!-- Derecha: Breadcrumb separado + (la tabla/metrics después) -->
      <div class="col-12 md:col-10">
        <Card>
          <template #content>
            <div class="p-2">
              <CompanyBreadcrumb :nodes="nodes" :selectedKey="selectedKey" />
              <!-- acá abajo dejamos espacio para tus tablas/metrics -->
            </div>
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
import api from '@/plugins/axios'
import CompanyBreadcrumb from '@/components/analytics/CompanyBreadcrumb.vue'

const nodes = ref([])
const expandedKeys = ref({})
const treeFilter = ref('')
const selectedKey = ref('company_main')

function toNode(item){
  return {
    key: item.id,
    label: item.label,
    leaf: !item.has_children,
    data: { type: item.type, ...(item.meta || {}) },
    children: Array.isArray(item.children) ? item.children.map(toNode) : undefined
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
    nodes.value = [...nodes.value]
  }
  expandedKeys.value = { ...expandedKeys.value, [node.key]: true }
}

function onNodeSelect(evt){
  const node = evt?.node
  if (!node) return
  selectedKey.value = node.key
}

onMounted(async () => {
  await loadRoot()
  // expand Company + Teams (visible de inicio)
  const company = nodes.value?.[0]
  const ek = {}
  if (company) {
    ek[company.key] = true
    if (Array.isArray(company.children)) {
      for (const t of company.children) ek[t.key] = true
    }
  }
  expandedKeys.value = ek
})
</script>

<style scoped>
/* altura fija para el árbol con scroll Y */
.tree-wrap{
  height: calc(100vh - 70px - 1rem); /* 70px topbar + 0.5rem padding * 2 */
  overflow-y: auto;
}
</style>
