<template>
  <div class="list-card">
    <h3>📋 Lista rápida</h3>
    <ul>
      <li v-for="(item, i) in items" :key="i">{{ item }}</li>
    </ul>
  </div>
</template>

<script setup>
const items = ['Cliente A', 'Cliente B', 'Cliente C'];
</script>
