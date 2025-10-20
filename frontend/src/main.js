// usa Lara, sin primeflex. Tu theme y main siguen primero.
import './assets/css/theme.css'
import './assets/css/main.css'

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

// asegura tema antes de montar (login y navbar lo heredan)
initTheme()

app.use(pinia)
app.use(router)
app.mount('#app')
