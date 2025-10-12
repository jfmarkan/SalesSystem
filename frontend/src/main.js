import './assets/css/main.css';

import '@primevue/themes/lara'; // 🎨 Tema Lara (por defecto)
import 'primeicons/primeicons.css'; // Iconos
import 'primeflex/primeflex.css'; // Utilidades de grid y spacing
import '@fortawesome/fontawesome-free/css/all.min.css'
import 'primeflex/primeflex.css'



import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createPersistedState } from 'pinia-plugin-persistedstate';

import App from './App.vue';
import router from './router';
import setupPrimeVue from './plugins/primevue';

const app = createApp(App);
const pinia = createPinia();
pinia.use(createPersistedState);

setupPrimeVue(app);
app.use(pinia);
app.use(router);
app.mount('#app');
