<template>
  <div class="pctable">
    <div v-if="loading" class="state">Laden…</div>
    <div v-else-if="error" class="state error">Fehler beim Laden</div>
    <table v-else class="table">
      <thead>
        <tr>
          <th>Profit-Center</th>
          <th class="num">Summe</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id || row.key">
          <td>{{ row.name }}</td>
          <td class="num">{{ formatNumber(row.total) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
/* Simple profit centers summary table */
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  endpoint: { type: String, default: '/api/profit-centers/summary' } // adjust to your backend
})

const loading = ref(true)
const error = ref(false)
const rows = ref([])

onMounted(async ()=>{
  loading.value = true; error.value = false
  try {
    const { data } = await axios.get(props.endpoint)
    // Expect array like [{id,name,total}] - normalize
    rows.value = Array.isArray(data) ? data.map(x => ({
      id: x.id ?? x.key, name: x.name ?? x.title ?? '—', total: Number(x.total ?? x.sum ?? 0)
    })) : []
  } catch(e){
    error.value = true
  } finally {
    loading.value = false
  }
})

function formatNumber(n){
  try { return new Intl.NumberFormat('de-DE').format(n) } catch { return n }
}
</script>

<style scoped>
.pctable{ width:100%; height:100%; display:flex; flex-direction:column; }
.state{ opacity:.85; padding:.5rem; }
.state.error{ color:#ef4444; }
.table{ width:100%; border-collapse: collapse; }
th, td{ padding:.5rem .6rem; border-bottom:1px solid rgba(255,255,255,.12); }
th{ text-align:left; font-weight:700; }
.num{ text-align:right; }
</style>