<template>
    <div class="app-title"><slot /></div>
</template>

<script setup>
/* Simple section title to keep app-wide title logic consistent */
</script>

<style scoped>
.app-title{
    font-size:1.1rem;
    font-weight:700;
    padding:.25rem .5rem;
    border-radius:10px;
    background:rgba(255,255,255,0.4);
    color:#e5e7eb;
    backdrop-filter: blur(10px);
}
</style>
