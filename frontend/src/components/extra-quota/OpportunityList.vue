<template>
  <div>
    <h3>Verkaufschancen (letzte Version pro Gruppe)</h3>

    <form class="filter-row" @submit.prevent="fetch">
      <input v-model.number="filters.user_id" type="number" placeholder="User-ID" />
      <input v-model.number="filters.fiscal_year" type="number" placeholder="FY" />
      <input v-model="filters.profit_center_code" placeholder="PC-Code" />
      <select v-model="filters.status">
        <option value="">Status (alle)</option>
        <option value="draft">Entwurf</option>
        <option value="open">Offen</option>
        <option value="won">Gewonnen</option>
        <option value="lost">Verloren</option>
      </select>
      <button class="btn">Filtern</button>
    </form>

    <details class="new-box">
      <summary>Neue Chance erstellen</summary>
      <form class="grid" @submit.prevent="create">
        <input v-model.number="form.user_id" type="number" placeholder="User-ID" required />
        <input v-model.number="form.fiscal_year" type="number" placeholder="FY" required />
        <input v-model="form.profit_center_code" placeholder="PC-Code" required />
        <input v-model.number="form.opportunity_ammount" type="number" step="0.01" placeholder="Volumen" required />
        <input v-model.number="form.probability_pct" type="number" min="0" max="100" placeholder="Wahrscheinlichkeit (%)" required />
        <input v-model="form.estimated_start_date" type="date" />
        <input v-model="form.potential_client_name" placeholder="Potenzieller Kunde" />
        <input v-model="form.client_group_number" placeholder="Kundengruppe Nr." />
        <input v-model="form.comments" placeholder="Kommentare" />
        <button class="btn btn-primary">Anlegen</button>
      </form>
    </details>

    <table class="grid-table">
      <thead>
        <tr>
          <th>Gruppe</th><th>Version</th><th>User</th><th>FY</th><th>PC</th>
          <th>Volumen</th><th>%</th><th>Status</th><th>Start</th><th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in items" :key="row.id">
          <td>{{ row.opportunity_group_id }}</td>
          <td>{{ row.version }}</td>
          <td>{{ row.user_id }}</td>
          <td>{{ row.fiscal_year }}</td>
          <td>{{ row.profit_center_code }}</td>
          <td>{{ row.opportunity_ammount }}</td>
          <td>{{ row.probability_pct }}</td>
          <td>{{ statusLabel(row.status) }}</td>
          <td>{{ row.estimated_start_date || '-' }}</td>
          <td>
            <button class="btn" @click="$emit('select', row.opportunity_group_id)">Öffnen</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="pager" v-if="pagination.last_page > 1">
      <button class="btn" :disabled="pagination.current_page <= 1" @click="loadPage(pagination.current_page - 1)">Zurück</button>
      <span>Seite {{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button class="btn" :disabled="pagination.current_page >= pagination.last_page" @click="loadPage(pagination.current_page + 1)">Weiter</button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { ref, onMounted } from 'vue';

export default {
  name: 'OpportunityList',
  emits: ['select','created'],
  setup(_, { emit }) {
    const filters = ref({ user_id:'', fiscal_year:'', profit_center_code:'', status:'' });
    const form = ref({
      user_id: null, fiscal_year: new Date().getFullYear(), profit_center_code: '',
      opportunity_ammount: 0, probability_pct: 0, estimated_start_date: '',
      comments: '', potential_client_name:'', client_group_number:''
    });

    const items = ref([]);
    const pagination = ref({ current_page:1, last_page:1 });

    const fetch = async (page = 1) => {
      const { data } = await axios.get('/api/extra-quota/opportunities', {
        params: { page, ...filters.value }
      });
      items.value = data.data;
      pagination.value = data;
    };

    const loadPage = (p) => fetch(p);

    const create = async () => {
      const { data } = await axios.post('/api/extra-quota/opportunities', form.value);
      await fetch(1);
      emit('created', data.opportunity_group_id);
    };

    const statusLabel = (s) => ({
      draft: 'Entwurf', open: 'Offen', won: 'Gewonnen', lost: 'Verloren'
    }[s] || s);

    onMounted(fetch);

    return { filters, form, items, pagination, fetch, loadPage, create, statusLabel };
  }
};
</script>

<style scoped>
.filter-row { display:flex; gap:8px; margin-bottom:10px; align-items:center; }
.new-box { border:1px dashed #cbd5e1; padding:10px; border-radius:8px; margin-bottom:10px; }
.grid { display:grid; grid-template-columns: repeat(4, 1fr); gap:8px; }
.grid-table { width:100%; border-collapse: collapse; }
th, td { padding:6px 8px; border-bottom:1px solid #eee; text-align:left; }
.pager { display:flex; gap:8px; align-items:center; margin-top:8px; }
.btn { padding:6px 10px; border:none; border-radius:6px; cursor:pointer; background:#e5e7eb; }
.btn-primary { background:#2563eb; color:#fff; }
</style>