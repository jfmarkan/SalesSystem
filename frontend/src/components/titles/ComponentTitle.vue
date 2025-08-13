<script setup>
const props = defineProps({
  cliente: { type: String, default: '' },
  pc: { type: String, default: '' }
})
</script>

<template>
  <div class="w-full">
    <div class="text-lg font-medium">
      Kunde: <span class="text-900">{{ cliente || '—' }}</span>
      <span class="mx-2">|</span>
      Profit Center: <span class="text-900">{{ pc || '—' }}</span>
    </div>
  </div>
</template>
