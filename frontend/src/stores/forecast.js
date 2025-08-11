import { defineStore } from 'pinia';
import axios from 'axios';

export const useForecastStore = defineStore('forecast', {
  state: () => ({
    filterBy: 'client', // 'client' | 'pc'
    selectorOptions: [],
    selectorValue: null,
    forecasts: [],
    selectedAssignmentId: null,
    monthlyEvolution: [],
    versionHistory: [],
    detail: [],
    summary: {
      totals: {
        forecastFY: 0, budgetFY: 0, salesFY: 0,
        forecastToLastMonth: 0, budgetToLastMonth: 0, salesToLastMonth: 0,
        forecast6Future: 0, budget6Future: 0
      }
    }
  }),

  actions: {
    async loadSelectorOptions() {
      this.selectorValue = null;
      this.forecasts = [];
      this.selectedAssignmentId = null;
      this.detail = [];
      this.monthlyEvolution = [];
      this.versionHistory = [];
      const { data } = await axios.get('/api/forecast/selector-options', {
        params: { filterBy: this.filterBy }
      });
      this.selectorOptions = data;
    },

    async loadForecastList() {
      if (this.selectorValue == null) return;
      const { data } = await axios.get('/api/forecast/list', {
        params: { filterBy: this.filterBy, filterId: this.selectorValue }
      });
      this.forecasts = data;
    },

    async selectAssignment(id) {
      this.selectedAssignmentId = id;
      await Promise.all([
        this.loadDetail(),
        this.loadSummary(),
        this.loadMonthlyEvolution(),
        this.loadVersionHistory()
      ]);
    },

    async loadDetail() {
      if (!this.selectedAssignmentId) return;
      const { data } = await axios.get(`/api/forecast/detail/${this.selectedAssignmentId}`);
      this.detail = data;
    },

    async loadSummary() {
      if (!this.selectedAssignmentId) return;
      const { data } = await axios.get(`/api/forecast/summary/${this.selectedAssignmentId}`);
      this.summary = data;
    },

    async loadMonthlyEvolution() {
      if (!this.selectedAssignmentId) return;
      const { data } = await axios.get(`/api/forecast/monthly-evolution/${this.selectedAssignmentId}`);
      this.monthlyEvolution = data;
    },

    async loadVersionHistory() {
      if (!this.selectedAssignmentId) return;
      const { data } = await axios.get(`/api/forecast/version-history/${this.selectedAssignmentId}`);
      this.versionHistory = data;
    },

    async saveForecasts() {
      if (!this.selectedAssignmentId) return { saved: 0 };
      const items = this.detail
        .filter(r => r.editable)
        .map(r => ({ slot: r.slot, value: r.forecast || 0 }));
      const { data } = await axios.post(`/api/forecast/save/${this.selectedAssignmentId}`, { items });
      await Promise.all([this.loadDetail(), this.loadSummary()]);
      return data;
    },
  }
});
