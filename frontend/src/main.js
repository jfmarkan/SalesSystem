/* === Estilos globales (orden) ===
   1) Iconos base
   2) Fuentes
   3) Estilos de la app (variables y overrides)
*/

import './assets/scss/fonts.scss'
import './assets/scss/theme.scss'
import './assets/scss/main.scss'     // deja aquí tus tokens: --p-font-family, etc.

// Iconos extra opcionales
//import '@fortawesome/fontawesome-free/css/all.min.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import App from './App.vue'
import router from './router'

import setupPrimeVue from './plugins/primevue.js'
import { initTheme } from './composables/useTheme'

const app = createApp(App)

const pinia = createPinia()
pinia.use(createPersistedState)

setupPrimeVue(app)    // configura PrimeVue + preset (no importa CSS del tema)
initTheme()           // asegura/ajusta la clase .app-dark antes del mount

app.use(pinia)
app.use(router)
app.mount('#app')
