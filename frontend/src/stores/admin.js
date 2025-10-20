// Pinia store for Admin Dashboard (JS)
import { defineStore } from 'pinia'
import { adminApi } from '@/services/adminApi'

export const useAdmin = defineStore('admin', {
  state: () => ({
    flags: { maintenance: false, budget_period_active: false },
    clients: [],
    users: [],
    profitCenters: [],
    clientDetail: null,
    logs: [],
    budgetCases: [],
    loaded: false,
  }),
  actions: {
    async loadAll() {
      const [flags, clients, users, pcs] = await Promise.all([
        adminApi.getFlags(),
        adminApi.listClients(),
        adminApi.listUsers(),
        adminApi.listProfitCenters(),
      ])
      this.flags = flags
      this.clients = clients
      this.users = users
      this.profitCenters = pcs
      this.loaded = true
    },

    async refreshClients() { this.clients = await adminApi.listClients() },
    async refreshUsers() { this.users = await adminApi.listUsers() },

    async setMaintenance(v) { this.flags = await adminApi.setFlags({ maintenance: v }) },
    async setBudgetPeriod(v) { this.flags = await adminApi.setFlags({ budget_period_active: v }) },

    async createClient(payload) { await adminApi.createClient(payload); await this.refreshClients() },
    async updateClient(cgn, data) { await adminApi.updateClient(cgn, data); await this.refreshClients() },
    async deleteClient(cgn) { await adminApi.deleteClient(cgn); await this.refreshClients() },

    async createUser(p) { await adminApi.createUser(p); await this.refreshUsers() },
    async updateUser(id, p) { await adminApi.updateUser(id, p); await this.refreshUsers() },
    async deleteUser(id) { await adminApi.deleteUser(id); await this.refreshUsers() },
    async kickUser(id) { await adminApi.kickUser(id); await this.refreshUsers() },

    async linkClientPC(p) { await adminApi.linkClientPC(p) },
    async unlinkClientPC(id) { await adminApi.unlinkClientPC(id) },

    async loadClient(cgn) { this.clientDetail = await adminApi.getClient(cgn) },

    async loadLogs() { this.logs = await adminApi.getLogs() },
    async addLog(p) { await adminApi.addLog(p); await this.loadLogs() },

    async loadBudgetCases(fy) { this.budgetCases = await adminApi.listBudgetCases(fy) },
    async upsertBudgetCase(p) { await adminApi.upsertBudgetCase(p) },
    async createNextYearBudgets(fy) { await adminApi.createNextYearBudgets(fy) },
    async rebuildSales(from) { await adminApi.rebuildSales(from) },

    async clientsBestWorst(fy) { return adminApi.clientsBestWorst(fy) },
  },
})
