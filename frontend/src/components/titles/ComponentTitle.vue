<script setup>
const props = defineProps({
  user: { type: String, required: true },
  cliente: { type: String, default: '' },
  pc: { type: String, default: '' }
})
</script>

<template>
  <div class="w-full">
    <div class="text-sm text-500">Viendo</div>
    <div class="text-lg font-medium">
      Usuario: <span class="text-900">{{ user }}</span>
      <span class="mx-2">|</span>
      Cliente: <span class="text-900">{{ cliente || '—' }}</span>
      <span class="mx-2">|</span>
      Profit Center: <span class="text-900">{{ pc || '—' }}</span>
    </div>
  </div>
</template>
