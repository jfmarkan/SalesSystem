<template>
  <div class="editor-wrapper">
    <!-- Full-width top bar (glass) -->
    <div class="editor-toolbar">
      <Button class="p-button-text" @click="goBack">← Zurück</Button>
      <div class="toolbar-actions">
        <Button
          label="Speichern"
          icon="pi pi-check"
          class="p-button-success"
          @click="save"
          :loading="saving"
        />
        <Button
          label="Abbrechen"
          icon="pi pi-times"
          class="p-button-secondary"
          @click="discard"
          :disabled="saving"
        />
      </div>
    </div>

    <div class="editor-body">
      <!-- LEFT: two separate titled cards -->
      <aside class="left-column">
        <GlassCard class="panel-card" :title="'Hintergründe'">
          <BackgroundPicker v-model="draftBackground" />
        </GlassCard>

        <GlassCard class="panel-card" :title="'Widgets'">
          <WidgetPicker @dragstart-widget="onDragStartWidget" />
        </GlassCard>
      </aside>

      <!-- RIGHT: titled card that contains the drop area -->
      <GlassCard class="grid-shell" :title="'Arbeitsfläche'">
        <div class="grid-area" ref="gridContainer" @dragover.prevent @drop="onDropToGrid">
          <grid-layout
            v-model:layout="draftLayout"
            :col-num="12"
            :row-height="30"
            :is-draggable="true"
            :is-resizable="true"
            :margin="[10, 10]"
            :use-css-transforms="true"
          >
            <grid-item
              v-for="item in draftLayout"
              :key="item.i"
              :x="item.x"
              :y="item.y"
              :w="item.w"
              :h="item.h"
              :i="item.i"
            >
              <GlassCard :title="getTitle(item)">
                <div class="widget-toolbar">
                  <Button
                    :aria-label="'Entfernen'"
                    icon="pi pi-trash"
                    class="p-button-text p-button-danger"
                    @click="removeItem(item.i)"
                  />
                </div>
                <component
                  v-if="getWidgetComponent(item.type)"
                  :is="getWidgetComponent(item.type)"
                  v-bind="getPropsForType(item)"
                  class="grid-widget"
                />
                <div v-else class="grid-placeholder">Widget {{ item.i }}</div>
              </GlassCard>
            </grid-item>
          </grid-layout>
        </div>
      </GlassCard>
    </div>
  </div>
</template>

<script setup>
/* UI in German; functions & comments in English */
import { ref, onMounted } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import Button from 'primevue/button'
import GlassCard from '@/components/ui/GlassCard.vue'
import WidgetPicker from '@/components/dashboard/WidgetPicker.vue'
import BackgroundPicker from '@/components/dashboard/BackgroundPicker.vue'
import { useDashboardStore } from '@/stores/dashboard'
import { useRouter } from 'vue-router'

import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/CalendarCard.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import TaskCard from '@/components/widgets/TaskCard.vue'
import ListCard from '@/components/widgets/ListCard.vue'
import OrderList from '@/components/lists/OrderList.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'

const store = useDashboardStore()
const router = useRouter()

const gridContainer = ref(null)
const draftLayout = ref([])
const draftBackground = ref('white') // persists for the dashboard (not previewed here)
const saving = ref(false)

onMounted(async () => {
  if (!store.dashboard) await store.loadDefault()
  draftBackground.value = store.background || 'white'
  // clone layout so editor doesn't mutate Home until save
  draftLayout.value = store.layout.map((w) => ({ ...structuredClone(w) }))
})

function goBack() {
  router.push({ name: 'home' })
}
function discard() {
  draftBackground.value = store.background || 'white'
  draftLayout.value = store.layout.map((w) => ({ ...structuredClone(w) }))
}
function removeItem(i) {
  draftLayout.value = draftLayout.value.filter((w) => w.i !== i)
}

function save() {
  saving.value = true
  const widgets = draftLayout.value
    .slice()
    .sort((a, b) => a.y - b.y || a.x - b.x)
    .map((w, idx) => ({ ...w, sort: idx }))

  store
    .saveAll({ background: draftBackground.value, widgets })
    .then(() => goBack())
    .finally(() => (saving.value = false))
}

/* Drag & drop from widget picker into grid */
let draggedTemplate = null
function onDragStartWidget(item, ev) {
  draggedTemplate = item
  ev.dataTransfer?.setData('application/json', JSON.stringify(item))
  const img = new Image()
  img.src = 'data:image/png;base64,iVBORw0KGgo='
  ev.dataTransfer?.setDragImage(img, 0, 0)
}
function onDropToGrid(ev) {
  ev.preventDefault()
  const data = ev.dataTransfer?.getData('application/json')
  const tpl = data ? JSON.parse(data) : draggedTemplate
  if (!tpl) return
  const pos = computeGridPositionFromMouse(ev, tpl.w, tpl.h)
  const newItem = {
    x: pos.x,
    y: pos.y,
    w: tpl.w,
    h: tpl.h,
    i: newKey(),
    type: tpl.type,
    title: tpl.title,
    ...(tpl.defaultProps || {}),
  }
  draftLayout.value.push(newItem)
  draggedTemplate = null
}
function computeGridPositionFromMouse(ev, w = 2, h = 4) {
  // map cursor to grid coordinates
  const colNum = 12,
    rowHeight = 30,
    marginX = 10,
    marginY = 10
  const el = gridContainer.value
  const rect = el.getBoundingClientRect()
  const containerWidth = rect.width
  const totalMarginsX = marginX * (colNum + 1)
  const colWidth = (containerWidth - totalMarginsX) / colNum
  const relX = Math.max(0, ev.clientX - rect.left - marginX)
  const relY = Math.max(0, ev.clientY - rect.top - marginY)
  let x = Math.floor(relX / (colWidth + marginX))
  let y = Math.floor(relY / (rowHeight + marginY))
  x = Math.max(0, Math.min(colNum - w, x))
  y = Math.max(0, y)
  return { x, y }
}
function newKey() {
  const nums = draftLayout.value.map((it) => parseInt(it.i, 10)).filter((n) => Number.isFinite(n))
  const max = nums.length ? Math.max(...nums) : -1
  return String(max + 1)
}

/* Widget render helpers */
function getWidgetComponent(type) {
  return (
    {
      kpi: KpiCard,
      calendar: CalendarCard,
      chart: ChartCard,
      task: TaskCard,
      list: ListCard,
      orders: OrderList,
      table: ProfitCentersTable
    }[type] || null
  )
}
function getTitle(item) {
  if (item.title) return item.title
  return (
    {
      kpi: 'KPIs',
      calendar: 'Kalender',
      chart: 'Diagramm',
      task: 'Aufgaben',
      list: 'Liste',
      orders: 'Bestellungen',
      table: 'Tableau'
    }[item.type] || 'Widget'
  )
}
function getPropsForType(item) {
  if (item.type === 'kpi')
    return { modelValue: item.kpiId, 'onUpdate:modelValue': (val) => (item.kpiId = val) }
  return {}
}
</script>

<style scoped>
/* Layout */
.editor-wrapper {
  width: 100%;
  min-height: 100%;
  display: flex;
  flex-direction: column;
}

.editor-body {
  display: flex;
  gap: 16px;
  padding: 16px;
  align-items: flex-start;
}

/* Full-width toolbar */
.editor-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 16px;
    margin: 0 16px;
    position: sticky;
    top: 0;
    background: rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    border-radius: 10px;
}

.toolbar-actions {
  display: flex;
  gap: 8px;
}

/* Left column */
.left-column {
  width: 320px;
  flex: 0 0 320px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Glass + shadow utility (opacity .4, blur 20px) */

/* Cards */
.panel-card {
  border-radius: 10px;
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
}

.grid-shell {
  flex: 1;
  border-radius: 10px;
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
}

/* Inner drop surface */
.grid-area {
  min-height: 78vh;
  padding: 12px;
}

.grid-placeholder {
  height: 100%;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #e5e7eb;
}

.grid-widget {
  height: 100%;
  width: 100%;
  box-sizing: border-box;
  background: transparent;
  border: 0;
  border-radius: 0;
}

.widget-toolbar {
  position: absolute;
  top: 6px;
  right: 6px;
  z-index: 2;
}
</style>
