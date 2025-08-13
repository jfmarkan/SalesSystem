import api from '@/plugins/axios';
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        roles:[],
    }),
    
actions: {
    async login(credentials) {
            await api.get('/sanctum/csrf-cookie'); // 1. Obtiene cookie CSRF
            const response = await api.post('/login', credentials); // 2. Login por sesión
            await this.fetchUser(); // 3. Carga usuario
            this.roles = response.data.roles; // <- opcional, si lo devolvés
            return response.data
        },

        async fetchUser() {
            const { data } = await api.get('/api/user');
            this.user = data;
        },

        async logout() {
            await api.post('/logout');
            this.user = null;
            this.roles = null;
        }
    }
});
