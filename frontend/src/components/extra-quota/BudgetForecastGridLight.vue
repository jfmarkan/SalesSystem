<template>
  <div>
    <div class="toolbar">
      <div class="title">Budget & Forecast</div>
      <div class="fy">
        <label>Geschäftsjahr</label>
        <input type="number" v-model.number="fiscalYear" @change="loadAll" />
      </div>
    </div>

    <div v-if="loading" class="muted">Laden…</div>

    <table v-else class="grid">
      <thead>
        <tr>
          <th>Monat</th><th>Budget</th><th>Forecast</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(r, idx) in rows" :key="idx">
          <td class="month">{{ monthLabel(r.month) }} {{ r.fiscal_year }}</td>
          <td><input type="number" step="0.01" v-model.number="budgetItems[idx].volume" @input="touchBudget(idx)" /></td>
          <td><input type="number" step="0.01" v-model.number="forecastItems[idx].volume" :disabled="isLocked(idx)" :style="forecastStyle(idx)" @input="touchForecast(idx)" /></td>
        </tr>
      </tbody>
    </table>

    <div class="actions">
      <button class="btn" @click="saveBudgets">Budget speichern</button>
      <button class="btn btn-primary" @click="saveForecasts">Forecast speichern</button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { ref, onMounted, watch } from 'vue';

export default {
  name: 'BudgetForecastGridLight',
  props: { groupId: { type: Number, required: true }, version: { type: Number, required: true }, estimatedStartDate: { type: String, default: null } },
  setup(props) {
    const fiscalYear = ref(new Date().getFullYear());
    const rows = ref([]), budgetItems = ref([]), forecastItems = ref([]), loading = ref(false);

    const loadBudget = async () => (await axios.get(`/api/extra-quota/budget/${props.groupId}/${props.version}`, { params: { fiscal_year: fiscalYear.value } })).data;
    const loadForecast = async () => (await axios.get(`/api/extra-quota/forecast/${props.groupId}/${props.version}`, { params: { fiscal_year: fiscalYear.value } })).data;

    const loadAll = async () => {
      loading.value = true;
      try {
        const b = await loadBudget(); const f = await loadForecast();
        rows.value = b.map(x => ({ month: x.month, fiscal_year: x.fiscal_year }));
        budgetItems.value = b.map(x => ({ month: x.month, fiscal_year: x.fiscal_year, volume: Number(x.volume || 0) }));
        forecastItems.value = f.map(x => ({ month: x.month, fiscal_year: x.fiscal_year, volume: Number(x.volume || 0) }));
      } finally { loading.value = false; }
    };

    const saveBudgets = async () => { await axios.post(`/api/extra-quota/budget/${props.groupId}/${props.version}/save`, { items: budgetItems.value }); alert('Budget gespeichert!'); await loadAll(); };
    const saveForecasts = async () => { await axios.post(`/api/extra-quota/forecast/${props.groupId}/${props.version}/save`, { items: forecastItems.value }); alert('Forecast gespeichert!'); await loadAll(); };

    const isLocked = (idx) => {
      if (!props.estimatedStartDate) return false;
      const sd = new Date(props.estimatedStartDate);
      const m = rows.value[idx]?.month, y = rows.value[idx]?.fiscal_year;
      return new Date(y, m - 1, 1) < new Date(sd.getFullYear(), sd.getMonth(), 1);
    };

    const COLORS = { RED_TEXT:'#C0504D', ROSE_BG:'#EFD3D2', GREEN_TEXT:'#2f855a', GREEN_BG:'#E6EDD7', YELLOW_BG:'#F5E2A9', BROWN_TEXT:'#795F0E' };
    const forecastStyle = (idx) => {
      const f = Number(forecastItems.value[idx]?.volume || 0);
      const b = Number(budgetItems.value[idx]?.volume || 0);
      if (f === 0) return {};
      if (f < b) return { backgroundColor: COLORS.ROSE_BG, color: COLORS.RED_TEXT };
      if (f === b) return { backgroundColor: COLORS.GREEN_BG, color: COLORS.GREEN_TEXT };
      return { backgroundColor: COLORS.YELLOW_BG, color: COLORS.BROWN_TEXT };
    };

    const monthLabel = (m) => ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'][m-1];
    const touchBudget = () => {}; const touchForecast = () => {};

    onMounted(loadAll);
    watch(() => [props.groupId, props.version], loadAll);

    return { fiscalYear, rows, budgetItems, forecastItems, loading, loadAll, saveBudgets, saveForecasts, isLocked, forecastStyle, monthLabel, touchBudget, touchForecast };
  }
};
</script>

<style scoped>
.toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.title { font-weight:600; }
.fy { display:flex; gap:8px; align-items:center; }
.grid { width:100%; border-collapse:separate; border-spacing:0 8px; }
thead th { text-align:left; color:#6b7280; font-weight:500; padding:4px 6px; }
tbody td { background:#fff; padding:8px; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; }
tbody td:first-child { border-left:1px solid #e5e7eb; border-radius:8px 0 0 8px; }
tbody td:last-child  { border-right:1px solid #e5e7eb; border-radius:0 8px 8px 0; }
.month { font-weight:500; color:#374151; }
input { width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; }
.actions { display:flex; gap:10px; margin-top:12px; }
.btn { padding:8px 12px; border:none; border-radius:8px; cursor:pointer; background:#e5e7eb; }
.btn-primary { background:#2563eb; color:#fff; }
.muted { color:#6b7280; }
</style>