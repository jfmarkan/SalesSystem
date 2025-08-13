<script setup>
const props = defineProps({
  title: { type: String, default: '' }
})
</script>

<template>
  <div class="glass-card">
    <header v-if="title" class="card-header">
      <h3 class="title">{{ title }}</h3>
      <slot name="header-extra" />
    </header>
    <div class="card-body">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.glass-card{
  background: rgba(0,0,0,0.4);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow: 0 2px 4px 0 rgba(0,0,0,0.4);
  border-radius: 12px;
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
}
.card-header{
  padding: 10px 14px;
  background: rgba(0,0,0,0.5);
  border-bottom: 1px solid rgba(0,0,0,0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.title{
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #c5c5c5;
}
.card-body{
  padding: 12px;
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto; /* el contenido hace scroll interno si lo necesita */
}
</style>