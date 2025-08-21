<template>
  <div class="task-card">
    <ul class="tasks">
      <li v-for="(t, i) in tasksLocal" :key="i" class="task-row">
        <label class="chk">
          <input type="checkbox" v-model="t.done" />
          <span class="ttl">{{ t.title }}</span>
          <span class="dt">{{ t.date }}</span>
        </label>
      </li>
      <li v-if="!tasksLocal.length" class="empty">Keine Aufgaben für heute</li>
    </ul>
  </div>
</template>

<script setup>
// Code in English
import { reactive, watchEffect } from 'vue'

const props = defineProps({
  tasks: { type: Array, default: () => [] } // [{title, done, date(DD-MM-YYYY)}]
})
const tasksLocal = reactive([])

watchEffect(() => {
  tasksLocal.splice(0, tasksLocal.length, ...(props.tasks || []).map(t => ({ ...t })))
})
</script>

<style scoped>
.task-card{ height:100%; width:100%; }
.tasks{ list-style:none; margin:0; padding:0; }
.task-row{ padding:.35rem .25rem; border-bottom:1px dashed rgba(0,0,0,.08); }
.chk{ display:flex; align-items:center; gap:.5rem; }
.ttl{ font-weight:600; }
.dt{ margin-left:auto; font-size:.8rem; opacity:.8; }
.empty{ padding:.5rem; color:#6b7280; font-style:italic; }
</style>