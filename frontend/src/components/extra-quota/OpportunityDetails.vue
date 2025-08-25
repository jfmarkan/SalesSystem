<template>
  <div>
    <h3>Details der Verkaufschance</h3>

    <div v-if="loading">Laden…</div>

    <div v-else-if="latest" class="grid">
      <label>Gruppe</label>
      <div>{{ latest.opportunity_group_id }}</div>

      <label>Version</label>
      <div>{{ latest.version }}</div>

      <label>User-ID</label>
      <input type="number" v-model.number="form.user_id" />

      <label>FY</label>
      <input type="number" v-model.number="form.fiscal_year" />

      <label>PC-Code</label>
      <input v-model="form.profit_center_code" />

      <label>Volumen</label>
      <input type="number" step="0.01" v-model.number="form.opportunity_ammount" />

      <label>Wahrscheinlichkeit (%)</label>
      <input type="number" min="0" max="100" v-model.number="form.probability_pct" />

      <label>Start (Schätzung)</label>
      <input type="date" v-model="form.estimated_start_date" />

      <label>Status</label>
      <select v-model="form.status">
        <option value="draft">Entwurf</option>
        <option value="open">Offen</option>
        <option value="won">Gewonnen</option>
        <option value="lost">Verloren</option>
      </select>

      <label>Pot. Kunde</label>
      <input v-model="form.potential_client_name" />

      <label>Kundengruppe Nr.</label>
      <input v-model="form.client_group_number" />

      <label>Kommentare</label>
      <textarea v-model="form.comments"></textarea>
    </div>

    <div class="row" v-if="latest">
      <button class="btn btn-primary" @click="saveVersion">Neue Version speichern</button>
      <span class="hint">Aktuelle Version: {{ latest.version }}</span>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { ref, watch, onMounted } from 'vue';

export default {
  name: 'OpportunityDetails',
  props: {
    groupId: { type: Number, required: true },
  },
  emits: ['versionSaved'],
  setup(props, { emit }) {
    const loading = ref(false);
    const latest = ref(null);
    const form = ref({
      user_id: null, fiscal_year: null, profit_center_code: '',
      opportunity_ammount: 0, probability_pct: 0,
      estimated_start_date: '', status: 'open',
      potential_client_name: '', client_group_number: '', comments: ''
    });

    const load = async () => {
      loading.value = true;
      try {
        const { data } = await axios.get(`/api/extra-quota/opportunities/${props.groupId}`);
        latest.value = data.latest;
        // sync form from latest
        Object.assign(form.value, {
          user_id: latest.value.user_id,
          fiscal_year: latest.value.fiscal_year,
          profit_center_code: latest.value.profit_center_code,
          opportunity_ammount: Number(latest.value.opportunity_ammount || 0),
          probability_pct: Number(latest.value.probability_pct || 0),
          estimated_start_date: latest.value.estimated_start_date || '',
          status: latest.value.status || 'open',
          potential_client_name: latest.value.potential_client_name || '',
          client_group_number: latest.value.client_group_number || '',
          comments: latest.value.comments || '',
        });
      } finally {
        loading.value = false;
      }
    };

    const saveVersion = async () => {
      await axios.post(`/api/extra-quota/opportunities/${props.groupId}/version`, { ...form.value });
      alert('Neue Version gespeichert!');
      emit('versionSaved', props.groupId);
      await load();
    };

    watch(() => props.groupId, load, { immediate: true });
    onMounted(load);

    return { loading, latest, form, saveVersion };
  }
};
</script>

<style scoped>
.grid { display:grid; grid-template-columns: 160px 1fr; gap:8px 12px; }
.row { display:flex; gap:12px; align-items:center; margin-top:10px; }
.hint { color:#6b7280; }
.btn { padding:6px 10px; border:none; border-radius:6px; cursor:pointer; background:#2563eb; color:#fff; }
</style>