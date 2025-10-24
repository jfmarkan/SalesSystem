// src/composables/useAdminApi.js
import api from '@/plugins/axios'

const BASE = '/api/settings'

export function useAdminApi() {
  // ===== Users =====
  const listUsers   = (params) => api.get(`${BASE}/users`, { params })
  const createUser  = (payload) => api.post(`${BASE}/users`, payload)
  const blockUser   = (id) => api.post(`${BASE}/users/${id}/block`)
  const unblockUser = (id) => api.post(`${BASE}/users/${id}/unblock`)
  const kickUser    = (id) => api.post(`${BASE}/users/${id}/kick`)
  // alias legacy
  const getUsers = listUsers

  // ===== Sessions =====
  const getOnline            = () => api.get(`${BASE}/sessions/online`)
  const killSessionsByUser   = (id) => api.delete(`${BASE}/sessions/by-user/${id}`)
  // alias legacy
  const getSessionsOnline    = getOnline
  const destroySessionsByUser= killSessionsByUser

  // ===== Clients (settings) =====
  const clientsSummary   = () => api.get(`${BASE}/clients/summary`)
  const clientPcs        = (clientGroup) => api.get(`${BASE}/clients/${clientGroup}/pcs`)
  const setClientPcs     = (clientGroup, profit_center_codes) =>
    api.post(`${BASE}/clients/${clientGroup}/pcs`, { profit_center_codes })
  const updateClient     = (clientGroup, payload) =>
    api.put(`${BASE}/clients/${clientGroup}`, payload)
  const assignClientUser = (clientGroup, user_id) =>
    api.post(`${BASE}/clients/${clientGroup}/assign-user`, { user_id })
  const blockClient      = (clientGroup) => api.post(`${BASE}/clients/${clientGroup}/block`)
  const deleteClient     = (clientGroup) => api.delete(`${BASE}/clients/${clientGroup}`)

  // ===== Global lists (fuera de /settings) =====
  // Soporta paginación/búsqueda si el backend la implementa. Si no, el front hace fallback.
  const listAllClients        = (params = {}) => api.get('/api/extra-quota/clients', { params })
  const listAllProfitCenters  = (params = {}) => api.get('/api/analytics/pc/list', { params })

  // aliases usados por la UI
  const getClients        = listAllClients
  const getProfitCenters  = listAllProfitCenters
  const getClientsSummary = clientsSummary
  const getClientPcs      = clientPcs
  const saveClientPcs     = setClientPcs

  // ===== Profit Centers (settings) =====
  const pcsSummary          = () => api.get(`${BASE}/profit-centers/summary`)
  const updatePc            = (code, payload) => api.put(`${BASE}/profit-centers/${code}`, payload)
  const updatePcConversion  = (code, payload) =>
    api.put(`${BASE}/profit-centers/${code}/conversion`, payload)
  const updatePcSeasonality = (code, seasonality) =>
    api.put(`${BASE}/profit-centers/${code}/seasonality`, seasonality)
  // alias
  const getPcsSummary = pcsSummary

  // ===== KPIs / Progress =====
  const getKpis     = () => api.get(`${BASE}/kpis/summary`)
  const getProgress = (fiscal_year) => api.get(`${BASE}/progress/summary`, { params: { fiscal_year } })

  // ===== Tools / Flags =====
  const rebuildSales     = (start_date) => api.post(`${BASE}/tools/rebuild-sales`, { start_date })
  const generateBudget   = (fiscal_year, confirm = 'Budgeting') =>
    api.post(`${BASE}/tools/generate-budget`, { fiscal_year, confirm })
  const generateForecast = (fiscal_year, confirm = 'Forecasting') =>
    api.post(`${BASE}/tools/generate-forecast`, { fiscal_year, confirm })
  const setBudgetSeason  = (enabled) => api.post(`${BASE}/flags/budget-season`, { enabled })

  return {
    // users
    listUsers, getUsers, createUser, blockUser, unblockUser, kickUser,
    // sessions
    getOnline, getSessionsOnline, killSessionsByUser, destroySessionsByUser,
    // clients
    clientsSummary, getClientsSummary, clientPcs, getClientPcs, setClientPcs, saveClientPcs,
    updateClient, assignClientUser, blockClient, deleteClient,
    listAllClients, getClients,
    // profit centers
    pcsSummary, getPcsSummary, updatePc, updatePcConversion, updatePcSeasonality,
    listAllProfitCenters, getProfitCenters,
    // kpis / progress
    getKpis, getProgress,
    // tools
    rebuildSales, generateBudget, generateForecast, setBudgetSeason,
  }
}