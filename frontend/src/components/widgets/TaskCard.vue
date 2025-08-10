<template>
  <div class="task-card">
    <h3>📝 Tareas pendientes</h3>
    <ul>
      <li v-for="(task, i) in tasks" :key="i">
        <input type="checkbox" v-model="task.done" />
        <span :class="{ done: task.done }">{{ task.title }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { reactive } from 'vue';

const tasks = reactive([
  { title: 'Enviar reporte', done: false },
  { title: 'Llamar a proveedor', done: false },
  { title: 'Revisar pedidos', done: false }
]);
</script>

<style scoped>
.done {
  text-decoration: line-through;
  color: #888;
}
.task-card {
  padding: 1rem;
}
</style>
