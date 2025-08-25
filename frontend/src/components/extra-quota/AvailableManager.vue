<template>
  <div>
    <h3>Verfügbare Extra-Quote (CEO)</h3>

    <form class="grid" @submit.prevent="upsert">
      <input v-model.number="form.fiscal_year" type="number" placeholder="Geschäftsjahr" required />
      <input v-model="form.profit_center_code" placeholder="Profitcenter-Code" required />
      <input v-model.number="form.volume" type="number" step="0.01" placeholder="Volumen (Einheiten)" required />
      <button class="btn btn-primary" type="submit">Speichern/Upsert</button>
    </form>

    <div class="row">
      <input v-model.number="filters.fiscal_year" type="number" placeholder="FY filtern" @change="fetch" />
      <input v-model="filters.profit_center_code" placeholder="PC-Code filtern" @keyup.enter="fetch" />
      <button class="btn" @click="fetch">Filtern</button>
    </div>

    <table class="grid-table">
      <thead>
        <tr>
          <th>ID</th><th>FY</th><th>PC</th><th>Volumen</th><th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.fiscal_year }}</td>
          <td>{{ row.profit_center_code }}</td>
          <td>{{ row.volume }}</td>
          <td>
            <button class="btn btn-danger" @click="removeRow(row)">Löschen</button>
          </td>
        </tr>
      </tbody>
    </table>

  </div>
</template>

<script>
import axios from 'axios';
import { ref, onMounted } from 'vue';

export default {
  name: 'AvailableManager',
  setup() {
    const form = ref({ fiscal_year: new Date().getFullYear(), profit_center_code: '', volume: 0 });
    const filters = ref({ fiscal_year: '', profit_center_code: '' });
    const rows = ref([]);

    const fetch = async () => {
      const { data } = await axios.get('/api/extra-quota/available', { params: filters.value });
      rows.value = data;
    };

    const upsert = async () => {
      await axios.post('/api/extra-quota/available', form.value);
      await fetch();
    };

    const removeRow = async (row) => {
      if (!confirm('Eintrag löschen?')) return;
      await axios.delete(`/api/extra-quota/available/${row.id}`);
      await fetch();
    };

    onMounted(fetch);
    return { form, filters, rows, fetch, upsert, removeRow };
  }
};
</script>

<style scoped>
.grid { display:grid; grid-template-columns: 160px 1fr 1fr 160px; gap:8px; margin-bottom:10px; }
.row { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
.grid-table { width:100%; border-collapse: collapse; }
th, td { padding:6px 8px; border-bottom:1px solid #eee; text-align:left; }
.btn { padding:6px 10px; border:none; border-radius:6px; cursor:pointer; background:#e5e7eb; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-danger { background:#dc2626; color:#fff; }
</style>
