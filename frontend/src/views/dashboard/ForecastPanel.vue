<script setup>
import { computed, watch } from 'vue';
import { useForecastStore } from '@/stores/forecast';

const s = useForecastStore();

const filterItems = [
  { label: 'Kunden', value: 'client' },
  { label: 'Profit Center', value: 'pc' },
];

watch(
  () => s.filterBy,
  () => {
    s.loadSelectorOptions().catch(() => alert('Fehler beim Laden der Optionen.'));
  },
  { immediate: true }
);

// colores estilo Access
const COLORS = {
  ALERT_RED: '#C0504D',
  SOFT_PINK: '#EFD3D2',
  SOFT_GREEN: '#E6EDD7',
  GREEN_OK: '#758C48',
  SOFT_YELLOW: '#F5E2A9',
  BROWN_DARK: '#795F0E',
  GREY_NEUTRAL: '#CCCCCC',
  GREY_BG: '#EBEBEB',
  BORDER: '#8C8C8C',
};

// color de input forecast según comparación con budget
function forecastCellStyle(forecast, budget, emptyBg) {
  if (!forecast || forecast === 0) {
    return {
      backgroundColor: emptyBg === 'white' ? '#FFFFFF' : COLORS.GREY_BG,
      color: '#000000',
      borderColor: COLORS.BORDER,
    };
  }
  if (forecast < (budget || 0)) {
    return { backgroundColor: COLORS.SOFT_PINK, color: COLORS.ALERT_RED, borderColor: COLORS.ALERT_RED };
  }
  if (forecast === (budget || 0)) {
    return { backgroundColor: COLORS.SOFT_GREEN, color: COLORS.GREEN_OK, borderColor: COLORS.GREEN_OK };
  }
  return { backgroundColor: COLORS.SOFT_YELLOW, color: COLORS.BROWN_DARK, borderColor: COLORS.BROWN_DARK };
}

function pctBox(realVal, baseVal) {
  if (!realVal || !baseVal) {
    return { text: '--%', bg: COLORS.GREY_NEUTRAL, fg: '#000000', border: COLORS.GREY_NEUTRAL };
  }
  const pct = Math.round((realVal / baseVal) * 100);
  if (pct < 100) return { text: `${pct}%`, bg: COLORS.SOFT_PINK, fg: COLORS.ALERT_RED, border: COLORS.ALERT_RED };
  if (pct === 100) return { text: `${pct}%`, bg: COLORS.SOFT_GREEN, fg: COLORS.GREEN_OK, border: COLORS.GREEN_OK };
  return { text: `${pct}%`, bg: COLORS.SOFT_YELLOW, fg: COLORS.BROWN_DARK, border: COLORS.BROWN_DARK };
}

const totals = computed(() => (s.summary && s.summary.totals) ? s.summary.totals : {});
</script>

<template>
  <div class="p-4 space-y-4">
    <v-radio-group v-model="s.filterBy" inline>
      <v-radio v-for="i in filterItems" :key="i.value" :label="i.label" :value="i.value" />
    </v-radio-group>

    <div>
      <div class="mb-2">Auswahl:</div>
      <v-combobox
        v-model="s.selectorValue"
        :items="s.selectorOptions"
        item-title="label"
        item-value="value"
        :disabled="!s.selectorOptions.length"
        label="Bitte auswählen"
        @update:model-value="() => s.loadForecastList().catch(() => alert('Fehler beim Laden der Liste.'))"
      />
    </div>

    <div>
      <div class="mb-2">Forecasts:</div>
      <v-list v-if="s.forecasts.length" class="border rounded">
        <v-list-item
          v-for="f in s.forecasts"
          :key="f.id"
          :title="f.label"
          @click="s.selectAssignment(f.id).catch(() => alert('Fehler bei der Auswahl.'))"
        />
      </v-list>
      <div v-else class="text-sm text-gray-500">Keine Einträge</div>
    </div>

    <div v-if="s.selectedAssignmentId" class="space-y-6">
      <!-- Subform: 12 columnas -->
      <div class="overflow-auto">
        <table class="min-w-full border rounded">
          <thead>
            <tr>
              <th class="p-2 border">Monat</th>
              <th class="p-2 border">Ist</th>
              <th class="p-2 border">Budget</th>
              <th class="p-2 border">Forecast</th>
              <th class="p-2 border">Ist/Forecast</th>
              <th class="p-2 border">Ist/Budget</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in s.detail" :key="r.slot">
              <td class="p-1 border whitespace-nowrap">{{ r.display }}</td>
              <td class="p-1 border text-right">{{ (r.actual ?? 0).toLocaleString() }}</td>
              <td class="p-1 border text-right">{{ (r.budget ?? 0).toLocaleString() }}</td>

              <td class="p-1 border">
                <v-text-field
                  v-model.number="r.forecast"
                  type="number"
                  :disabled="!r.editable"
                  density="compact"
                  hide-details
                  variant="outlined"
                  :style="forecastCellStyle(r.forecast, r.budget, r.bgHint)"
                />
              </td>

              <td class="p-1 border">
                <div
                  class="text-center rounded px-2 py-1"
                  :style="(() => { const b=pctBox(r.actual, r.forecast); return { backgroundColor:b.bg, color:b.fg, border:`1px solid ${b.border}` } })()"
                >
                  {{ pctBox(r.actual, r.forecast).text }}
                </div>
              </td>

              <td class="p-1 border">
                <div
                  class="text-center rounded px-2 py-1"
                  :style="(() => { const b=pctBox(r.actual, r.budget); return { backgroundColor:b.bg, color:b.fg, border:`1px solid ${b.border}` } })()"
                >
                  {{ pctBox(r.actual, r.budget).text }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex gap-3">
        <v-btn color="primary" @click="async () => {
          try {
            const res = await s.saveForecasts();
            alert(res.saved > 0 ? 'Forecast gespeichert.' : 'Keine Änderungen.');
          } catch(e) { alert('Fehler beim Speichern.'); }
        }">Speichern</v-btn>
        <v-btn variant="tonal" @click="s.loadDetail()">Aktualisieren</v-btn>
      </div>

      <!-- Totales y KPIs -->
      <div class="grid md:grid-cols-3 gap-4">
        <v-card>
          <v-card-title>Bis letzten Monat</v-card-title>
          <v-card-text class="space-y-2">
            <div>Forecast: {{ totals.forecastToLastMonth?.toLocaleString?.() }}</div>
            <div>Budget: {{ totals.budgetToLastMonth?.toLocaleString?.() }}</div>
            <div>Ist: {{ totals.salesToLastMonth?.toLocaleString?.() }}</div>
          </v-card-text>
        </v-card>
        <v-card>
          <v-card-title>Ganzes Geschäftsjahr</v-card-title>
          <v-card-text class="space-y-2">
            <div>Forecast: {{ totals.forecastFY?.toLocaleString?.() }}</div>
            <div>Budget: {{ totals.budgetFY?.toLocaleString?.() }}</div>
            <div>Ist: {{ totals.salesFY?.toLocaleString?.() }}</div>
          </v-card-text>
        </v-card>
        <v-card>
          <v-card-title>Nächste 6 Monate</v-card-title>
          <v-card-text class="space-y-2">
            <div>Forecast: {{ totals.forecast6Future?.toLocaleString?.() }}</div>
            <div>Budget: {{ totals.budget6Future?.toLocaleString?.() }}</div>
          </v-card-text>
        </v-card>
      </div>

      <!-- Charts (temporal: JSON dump) -->
      <div class="grid md:grid-cols-2 gap-4">
        <div class="p-3 border rounded">
          <div class="font-medium mb-2">Monatliche Entwicklung</div>
          <pre class="text-xs overflow-auto max-h-64">{{ JSON.stringify(s.monthlyEvolution, null, 2) }}</pre>
        </div>
        <div class="p-3 border rounded">
          <div class="font-medium mb-2">Versionsverlauf</div>
          <pre class="text-xs overflow-auto max-h-64">{{ JSON.stringify(s.versionHistory, null, 2) }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>
