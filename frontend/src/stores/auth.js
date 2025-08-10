import api from '@/plugins/axios';
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: null,
        roles:[],
    }),
    actions: {
        async login(credentials) {
            await api.get('/sanctum/csrf-cookie'); // 1. Genera la cookie CSRF
            console.log('📤 Enviando al backend:', credentials);
            const response = await api.post('/login', credentials); // 2. Usa la cookie
            this.token = response.data.token; // <== Agregalo
            await this.fetchUser(); // 3. Guarda el user
            this.roles = response.data.roles; // <== Agregalo
            console.log('✅ Respuesta del login:', response.data);
            return response.data;
        },

        async fetchUser() {
            const { data } = await api.get('/api/user');
            this.user = data;
        },

        async logout() {
            await api.post('/logout');
            this.user = null;
            this.token = null;
            this.roles = null;
        }
    }
});
