import { defineStore } from 'pinia'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    roles: [],
  }),
  actions: {
    async login({ email, password }) {
      await ensureCsrf()
      const { data } = await api.post('/login', { email, password }) // <- sin /api
      if (!data?.verify) {
        this.user = data.user || null
        this.roles = Array.isArray(data.roles) ? data.roles : []
      }
      return data
    },
    async logout() {
      await ensureCsrf()
      await api.post('/logout') // <- sin /api
      this.user = null
      this.roles = []
    },
  },
})