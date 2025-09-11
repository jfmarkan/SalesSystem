import { defineStore } from 'pinia'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    roles: [],
  }),

  getters: {
    isAuthenticated: (s) => !!s.user,
  },

  actions: {
    async login({ email, password, remember = false }) {
      await ensureCsrf()
      try {
        const { data } = await api.post('/login', { email, password, remember }) // no /api
        if (!data?.verify) {
          this.user = data.user || null
          this.roles = Array.isArray(data.roles) ? data.roles : []
        }
        return data
      } catch (e) {
        const status = e?.response?.status
        const payload = e?.response?.data || {}

        // Blocked account -> bubble up with flagged error
        if (status === 403 && payload.code === 'USER_BLOCKED') {
          const err = new Error(
            payload.message ||
              'Ihr Benutzer wurde gesperrt. Wenn Sie glauben, dass dies ein Fehler ist, wenden Sie sich bitte an den Systemadministrator.'
          )
          err.blocked = true
          throw err
        }

        // Invalid credentials
        if (status === 422) {
          throw new Error(payload.message || 'Ungültige Anmeldedaten')
        }

        // Fallback
        throw new Error(payload.message || 'Unbekannter Fehler')
      }
    },

    async logout() {
      await ensureCsrf()
      try {
        await api.post('/logout') // no /api
      } finally {
        this.user = null
        this.roles = []
      }
    },

    reset() {
      this.user = null
      this.roles = []
    },
  },
})
