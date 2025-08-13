<script setup>
/* Table with editable forecast cells. Emits edit events. Horizontal scroll only. */
import InputText from 'primevue/inputtext'

const props = defineProps({
  months: { type: Array, required: true },
  ventas: { type: Array, required: true },
  budget: { type: Array, required: true },
  forecast: { type: Array, required: true }
})
const emit = defineEmits(['edit-forecast'])

function pct(num, den){ if(!den) return '0%'; return Math.round((num/den)*100) + '%' }
</script>

<template>
  <div class="table-shell">
    <div class="table-scroll-x">
      <table class="w-full" style="min-width: 1200px; border-collapse: separate; border-spacing: 0;">
        <thead>
          <tr>
            <th class="p-2 text-left sticky left-0 z-2 stick-left">Begriff</th>
            <th v-for="(m,i) in months" :key="'m'+i" class="p-2 text-center stick-head">{{ m }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="p-2 sticky left-0 z-2 stick-left">Verkauf</td>
            <td v-for="(m,i) in months" :key="'v'+i" class="p-2 text-right cell">{{ ventas[i] }}</td>
          </tr>
          <tr>
            <td class="p-2 sticky left-0 z-2 stick-left">Budget</td>
            <td v-for="(m,i) in months" :key="'b'+i" class="p-2 text-right cell">{{ budget[i] }}</td>
          </tr>
          <tr>
            <td class="p-2 sticky left-0 z-2 stick-left">Forecast</td>
            <td v-for="(m,i) in months" :key="'f'+i" class="p-1 cell">
              <InputText class="w-full p-inputtext-sm text-right"
                         :value="forecast[i]"
                         @input="e=>emit('edit-forecast',{ index:i, value:e.target.value })" />
            </td>
          </tr>
          <tr>
            <td class="p-2 sticky left-0 z-2 stick-left">% Verkauf / Budget</td>
            <td v-for="(m,i) in months" :key="'ivb'+i" class="p-2 text-right cell">
              {{ pct(ventas[i], budget[i]) }}
            </td>
          </tr>
          <tr>
            <td class="p-2 sticky left-0 z-2 stick-left">% Forecast / Budget</td>
            <td v-for="(m,i) in months" :key="'ifb'+i" class="p-2 text-right cell">
              {{ pct(forecast[i], budget[i]) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.table-shell{ height: 100%; overflow: hidden; display: flex; flex-direction: column; }
.table-scroll-x{ overflow-x: auto; overflow-y: hidden; height: 100%; }
.stick-head{ position: sticky; top: 0; background: rgba(255,255,255,0.4); backdrop-filter: blur(10px); }
.stick-left{ background: rgba(255,255,255,0.4); backdrop-filter: blur(10px); left: 0; }
.cell{ border-bottom: 1px solid rgba(0,0,0,0.06); }
</style>