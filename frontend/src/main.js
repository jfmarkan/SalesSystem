// src/main.js
import './assets/css/main.css'
import './assets/css/theme.css' // glassmorphism vars (light/dark)

import '@primevue/themes/lara'
import 'primeicons/primeicons.css'
import 'primeflex/primeflex.css'
import '@fortawesome/fontawesome-free/css/all.min.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'

import App from './App.vue'
import router from './router'
import setupPrimeVue from './plugins/primevue'
import { initTheme } from './composables/useTheme'

const app = createApp(App)
const pinia = createPinia()
pinia.use(createPersistedState)

setupPrimeVue(app)

// init theme (auto/light/dark) before mount
initTheme()

app.use(pinia)
app.use(router)
app.mount('#app')
