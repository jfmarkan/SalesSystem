<template>
  <div class="wrap">
    <div
      v-for="u in users"
      :key="u.id"
      class="user-card"
    >
      <div class="left">
        <img
          class="avatar"
          :src="u.profile_picture || fallback(u)"
          :alt="u.name || u.first_name"
          referrerpolicy="no-referrer"
        />
        <div class="meta">
          <div class="name">{{ u.name || u.first_name }}</div>
          <div class="sub">{{ u.first_name }} {{ u.last_name }}</div>
        </div>
      </div>

      <div class="actions">
        <Button icon="pi pi-user" rounded severity="secondary" />
        <Button icon="pi pi-envelope" rounded severity="secondary" />
        <Button icon="pi pi-database" rounded severity="secondary" />
        <Button icon="pi pi-chart-line" rounded severity="secondary" />
        <Button icon="pi pi-sitemap" rounded severity="secondary" />
        <Button icon="pi pi-cog" rounded severity="secondary" />
      </div>
    </div>

    <div v-if="error" class="err">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Button from 'primevue/button'
import api from '@/plugins/axios'

const users = ref([])
const error = ref('')

function fallback(u){
  const n = (u?.name || `${u?.first_name||''} ${u?.last_name||''}`).trim() || 'User'
  // avatar fallback
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(n)}&background=10b981&color=ffffff`
}

onMounted(async () => {
  try {
    const { data } = await api.get('/api/salesforce/users')
    users.value = Array.isArray(data) ? data : []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Fehler beim Laden.'
  }
})
</script>

<style scoped>
.wrap{
  padding: 24px;
  display: grid;
  gap: 14px;
}
.user-card{
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border-radius: 12px;
  background: rgba(255,255,255,.40);
  border: 1px solid rgba(0,0,0,.08);
  backdrop-filter: blur(10px);
}
@media (prefers-color-scheme: dark){
  .user-card{ background: rgba(0,0,0,.40); border-color: rgba(255,255,255,.16); }
}
.left{ display: flex; align-items: center; gap: 14px; min-width: 0; }
.avatar{ width: 6.42rem; height: 6.42rem; border-radius: 999px; object-fit: cover; }
.meta{ display:flex; flex-direction:column; gap: 6px; min-width: 0; }
.name{ font-weight: 700; font-size: 1.35rem; color: #0f172a; }
.sub{ color:#475569; }
@media (prefers-color-scheme: dark){ .name{ color:#f8fafc } .sub{ color:#cbd5e1 } }

.actions{ display: grid; grid-auto-flow: column; grid-auto-columns: min-content; gap: 10px; }
.err{ color:#ef4444; padding: 8px; }
@media (max-width: 768px){
  .user-card{ flex-direction: column; align-items: stretch; }
  .actions{ justify-content: flex-start; }
}
</style>