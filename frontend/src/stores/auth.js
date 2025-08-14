// src/stores/auth.js
import api from '@/plugins/axios'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
    error: null,
    roles: [],
  }),

  actions: {
    // Initialize auth state on app boot
    async init() {
      this.loading = true;
      try {
        await this.fetchUser();
      } catch (_) {
        // ignore initial 401
      } finally {
        this.loading = false;
      }
    },

    // Get CSRF cookie before any stateful POST (avoids 419)
    async csrf() {
      await api.get('/sanctum/csrf-cookie');
    },

    // Login accepting an object { email, password } to match your component usage
    async login({ email, password }) {
      this.error = null;

      // 1) ensure CSRF cookie (XSRF-TOKEN)
      await this.csrf();

      // 2) perform login (Laravel /login on web routes)
      const { data } = await api.post('/login', { email, password });

      // 3) refresh authenticated user (Sanctum session cookie)
      await this.fetchUser();

      // Return backend payload so caller can handle OTP/verify flows
      return data; // e.g. { verify: true, email: '...' } or { user: {...} }
    },

    // Fetch the current authenticated user
    async fetchUser() {
      try {
        const { data } = await api.get('/api/user');
        // Depending on your API, /api/user might return the user directly or wrapped.
        this.user = data?.user ?? data ?? null;
      } catch (e) {
        if (e?.response?.status === 401) {
          this.user = null;
          return;
        }
        throw e;
      }
    },

    // Logout and clear local state
    async logout() {
      await api.post('/logout');
      this.user = null;
    },
  },
});