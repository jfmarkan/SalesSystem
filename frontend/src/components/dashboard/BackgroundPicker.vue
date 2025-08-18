<template>
  <div class="bg-picker">
    <div class="grid">
      <button
        v-for="bg in images"
        :key="bg.key"
        :class="['swatch', modelValue === bg.url ? 'active' : '']"
        @click="$emit('update:modelValue', bg.url)"
        :title="bg.label"
      >
        <img :src="bg.url" :alt="bg.label" />
        <span v-if="modelValue === bg.url" class="check">✓</span>
      </button>
    </div>
  </div>
</template>

<script setup>
/* UI labels in German; functions/comments in English */
import { computed } from 'vue'
defineProps({ modelValue: { type: String, default: '' } })
defineEmits(['update:modelValue'])

/* Load images from src/assets/img/backgrounds */
const modules = import.meta.glob('@/assets/img/backgrounds/*.{png,jpg,jpeg,webp}', {
  eager: true,
  import: 'default',
})
const images = computed(() =>
  Object.entries(modules)
    .map(([path, url]) => {
      const fname = path.split('/').pop() || 'Bild'
      const base = fname.replace(/\.(png|jpg|jpeg|webp)$/i, '')
      return { key: base, url, label: base }
    })
    .sort((a, b) => a.key.localeCompare(b.key, 'de')),
)
</script>

<style scoped>
.bg-picker {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #e5e7eb;
}
.grid {
  display: grid;
  grid-template-columns: repeat(3, 84px);
  gap: 10px;
  padding: 10px 0;
}
.swatch {
  position: relative;
  width: 84px;
  height: 84px;
  border-radius: 12px;
  border: 2px solid transparent;
  padding: 0;
  overflow: hidden;
  cursor: pointer;
  background: transparent;
}
.swatch img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.check {
  position: absolute;
  right: 6px;
  bottom: 6px;
  font-size: 14px;
  font-weight: 800;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  border-radius: 6px;
  padding: 2px 6px;
}
.active {
  outline: 2px solid #3b82f6;
  border-color: #3b82f6;
}
.hint {
  opacity: 0.85;
}
</style>
