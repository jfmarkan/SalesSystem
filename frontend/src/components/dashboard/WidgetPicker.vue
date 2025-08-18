<template>
  <div class="widget-picker">
    <div class="list">
      <div
        v-for="w in items"
        :key="w.type"
        class="item"
        draggable="true"
        @dragstart="(e) => onDragStart(w, e)"
      >
        <span class="emoji">{{ w.icon }}</span>
        <div class="meta">
          <div class="title">{{ w.title }}</div>
          <div class="size">Größe: {{ w.w }}×{{ w.h }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/* UI in German; functions/comments in English */
import { ref } from 'vue'
const emit = defineEmits(['dragstart-widget'])

const items = ref([
  { type: 'kpi', title: 'KPI', w: 2, h: 4, icon: '📊', defaultProps: { kpiId: 'users' } },
  { type: 'chart', title: 'Diagramm', w: 8, h: 16, icon: '📈' },
  { type: 'task', title: 'Aufgaben', w: 3, h: 8, icon: '✅' },
  { type: 'list', title: 'Liste', w: 4, h: 8, icon: '🧾' },
  { type: 'orders', title: 'Bestellungen', w: 5, h: 16, icon: '📦' },
  { type: 'calendar', title: 'Kalender', w: 4, h: 8, icon: '🗓️' },
  { type: 'table', title: 'Tableau', w: 4, h: 8, icon: '🗓️' },
])

function onDragStart(item, ev) {
  ev.dataTransfer?.setData('application/json', JSON.stringify(item))
  emit('dragstart-widget', item, ev)
}
</script>

<style scoped>
.widget-picker {
  border-radius: 10px;
  padding: 10px;
  color: #e5e7eb;
}
.list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.item {
  display: flex;
  gap: 10px;
  align-items: center;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 8px;
  padding: 8px;
  cursor: grab;
}
.item:hover {
  background: rgba(255, 255, 255, 0.06);
}
.emoji {
  font-size: 20px;
}
.meta .title {
  font-weight: 700;
}
.meta .size {
  font-size: 12px;
  opacity: 0.85;
}
</style>
