<template>
  <div>
    <h3>Zuweisungen (Sales-Rep)</h3>

    <form class="grid" @submit.prevent="create">
      <input v-model.number="form.fiscal_year" type="number" placeholder="Geschäftsjahr" required />
      <input v-model="form.profit_center_code" placeholder="Profitcenter-Code" required />
      <input v-model.number="form.user_id" type="number" placeholder="User-ID" required />
      <input v-model.number="form.volume" type="number" step="0.01" placeholder="Volumen" required />
      <input v-model="form.assignment_date" type="date" />
      <button class="btn btn-primary" type="submit">Zuweisen</button>
    </form>

    <div class="row">
      <input v-model.number="filters.fiscal_year" type="number" placeholder="FY filtern" @change="fetch" />
      <input v-model="filters.profit_center_code" placeholder="PC-Code filtern" @keyup.enter="fetch" />
      <input v-model.number="filters.user_id" type="number" placeholder="User-ID filtern" @keyup.enter="fetch" />
      <button class="btn" @click="fetch">Filtern</button>
    </div>

    <table class="grid-table">
      <thead>
        <tr>
          <th>ID</th><th>FY</th><th>PC</th><th>User</th><th>Volumen</th><th>Veröffentlicht</th><th>Datum</th><th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.fiscal_year }}</td>
          <td>{{ row.profit_center_code }}</td>
          <td>{{ row.user_id }}</td>
          <td>
            <template v-if="!row.is_published">
              <input type="number" step="0.01" v-model.number="row.volume" @change="update(row)" />
            </template>
            <template v-else>{{ row.volume }}</template>
          </td>
          <td>
            <span v-if="row.is_published">Ja</span>
            <span v-else>Nein</span>
          </td>
          <td>{{ row.assignment_date || '-' }}</td>
          <td class="actions">
            <button class="btn" :disabled="row.is_published" @click="publish(row)">Veröffentlichen</button>
            <button class="btn btn-danger" :disabled="row.is_published" @click="removeRow(row)">Löschen</button>
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
  name: 'AssignmentManager',
  setup() {
    const form = ref({
      fiscal_year: new Date().getFullYear(),
      profit_center_code: '',
      user_id: null,
      volume: 0,
      assignment_date: '',
    });

    const filters = ref({ fiscal_year: '', profit_center_code: '', user_id: '' });
    const rows = ref([]);

    const fetch = async () => {
      const { data } = await axios.get('/api/extra-quota/assignments', { params: filters.value });
      rows.value = data;
    };

    const create = async () => {
      await axios.post('/api/extra-quota/assignments', form.value);
      Object.assign(form.value, {
        profit_center_code:'', user_id:null, volume:0, assignment_date:''
      });
      await fetch();
    };

    const update = async (row) => {
      await axios.patch(`/api/extra-quota/assignments/${row.id}`, { volume: row.volume });
    };

    const publish = async (row) => {
      if (!confirm('Zuweisung veröffentlichen? Danach ist keine Bearbeitung mehr möglich.')) return;
      await axios.post(`/api/extra-quota/assignments/${row.id}/publish`);
      await fetch();
    };

    const removeRow = async (row) => {
      if (!confirm('Eintrag löschen?')) return;
      await axios.delete(`/api/extra-quota/assignments/${row.id}`);
      await fetch();
    };

    onMounted(fetch);
    return { form, filters, rows, fetch, create, update, publish, removeRow };
  }
};
</script>

<style scoped>
.grid { display:grid; grid-template-columns: 120px 1fr 120px 1fr 160px 140px; gap:8px; margin-bottom:10px; }
.row { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
.grid-table { width:100%; border-collapse: collapse; }
th, td { padding:6px 8px; border-bottom:1px solid #eee; text-align:left; }
.actions { display:flex; gap:6px; }
.btn { padding:6px 10px; border:none; border-radius:6px; cursor:pointer; background:#e5e7eb; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-danger { background:#dc2626; color:#fff; }
</style>
