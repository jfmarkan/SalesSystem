// Axios services for Admin Dashboard (JS)
import axios from 'axios'

const api = axios.create({ baseURL: '/api' })

export const adminApi = {
  // system
  getFlags: () => api.get('/admin/system/flags').then(r => r.data),
  setFlags: (payload) => api.put('/admin/system/flags', payload).then(r => r.data),
  getLogs: () => api.get('/admin/logs').then(r => r.data),
  addLog: (payload) => api.post('/admin/logs', payload).then(r => r.data),

  // clients
  listClients: () => api.get('/admin/clients').then(r => r.data),
  getClient: (cgn) => api.get(`/admin/clients/${cgn}`).then(r => r.data),
  createClient: (data) => api.post('/admin/clients', data).then(r => r.data),
  updateClient: (cgn, data) => api.put(`/admin/clients/${cgn}`, data).then(r => r.data),
  deleteClient: (cgn) => api.delete(`/admin/clients/${cgn}`).then(r => r.data),

  // users
  listUsers: () => api.get('/admin/users').then(r => r.data),
  createUser: (data) => api.post('/admin/users', data).then(r => r.data),
  updateUser: (id, data) => api.put(`/admin/users/${id}`, data).then(r => r.data),
  deleteUser: (id) => api.delete(`/admin/users/${id}`).then(r => r.data),
  kickUser: (id) => api.post(`/admin/users/${id}/kick`).then(r => r.data),

  // profit centers + relations
  listProfitCenters: () => api.get('/admin/profit-centers').then(r => r.data),
  linkClientPC: (data) => api.post('/admin/relations/client-profit-center', data).then(r => r.data),
  unlinkClientPC: (relationId) => api.delete(`/admin/relations/client-profit-center/${relationId}`).then(r => r.data),

  // budgets
  listBudgetCases: (fiscal_year) => api.get('/admin/budget-cases', { params: { fiscal_year } }).then(r => r.data),
  upsertBudgetCase: (data) => api.post('/admin/budget-cases', data).then(r => r.data),
  createNextYearBudgets: (fiscal_year) => api.post('/admin/budgets/create-next-year', { fiscal_year }).then(r => r.data),
  clientsBestWorst: (fiscal_year) => api.get('/admin/budgets/clients-best-worst', { params: { fiscal_year } }).then(r => r.data),

  // tools
  rebuildSales: (from_date) => api.post('/admin/tools/rebuild-sales', { from_date }).then(r => r.data),
}
