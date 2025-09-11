<template>
  <div class="status-avatar" :style="sizeStyle">
    <template v-if="hasPhoto">
      <img :src="user.profile_picture" :alt="fullName" class="img" referrerpolicy="no-referrer" />
      <!-- Online dot (only if user.online === true) -->
      <span v-if="user.online === true" class="presence-dot presence-online" title="Online"></span>
    </template>
    <template v-else>
      <div class="initials" :style="initialsStyle" :title="fullName">
        {{ initials }}
      </div>
    </template>
  </div>
</template>

<script setup>
// Code & comments in English
import { computed } from 'vue'

const props = defineProps({
  user: { type: Object, required: true },
  size: { type: Number, default: 60 } // px
})

const hasPhoto = computed(() => !!props.user?.profile_picture)
const fullName = computed(() => (props.user?.name || `${props.user?.first_name||''} ${props.user?.last_name||''}`).trim())
const initials = computed(() => {
  const n = fullName.value || 'U'
  const parts = n.split(/\s+/).filter(Boolean)
  const a = (parts[0]?.[0]||'').toUpperCase()
  const b = (parts[1]?.[0]||'').toUpperCase()
  return (a + (b||'')).slice(0,2)
})
const isDisabled = computed(() => !!props.user?.disabled)
const sizeStyle = computed(() => ({ width: `${props.size}px`, height: `${props.size}px` }))

// Color: green if enabled, red if disabled
const initialsStyle = computed(() => ({
  background: isDisabled.value ? '#ef4444' : '#22c55e',
  color: '#ffffff'
}))
</script>

<style scoped>
.status-avatar{ position: relative; border-radius: 999px; overflow: hidden; flex: 0 0 auto; }
.img{ width: 100%; height: 100%; object-fit: cover; display: block; }
.initials{
  width: 100%; height: 100%;
  display: grid; place-items: center;
  font-weight: 800; letter-spacing: .5px; user-select: none;
}
.presence-dot{
  position: absolute; right: -2px; top: -2px;
  width: 16px; height: 16px; border-radius: 999px;
  border: 2px solid var(--surface-card, #fff);
  box-shadow: 0 0 0 2px rgba(0,0,0,.04);
}
.presence-online{ background: #22c55e; }
</style>
