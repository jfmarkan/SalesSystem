// src/stores/auth.js
import api from '@/plugins/axios'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    roles: [],
  }),

  actions: {
    async login(credentials) {
      await api.get('/sanctum/csrf-cookie')          // CSRF cookie
      const res = await api.post('/login', credentials) // sesión
      const { data } = await api.get('/api/user')        // usuario
      this.user = data
      this.roles = res.data?.roles ?? []
      return res.data
    },

    async fetchUser() {
      const { data } = await api.get('/api/user')
      this.user = data
      return data
    },

    async logout() {
      // evita 419 si el navegador limpió la cookie CSRF
      await api.get('/sanctum/csrf-cookie')
      await api.post('/logout')
      this.user = null
      this.roles = []
    },
  },
})
