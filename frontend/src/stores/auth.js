import { defineStore } from 'pinia'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    roles: [],
  }),
  actions: {
    async fetchUser() {
      try {
        const { data } = await api.get('/user')
        this.user = data?.user || null
        this.roles = Array.isArray(data?.roles) ? data.roles : []
        return data
      } catch (error) {
        this.user = null
        this.roles = []
        if (error.response?.status === 401) {
          return null
        }
        throw error
      }
    },
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