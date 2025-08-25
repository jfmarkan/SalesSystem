<template>
    <AuthLayout blurBackground>
        <form @submit.prevent="login" class="login-form">
            <InputText v-model="email" placeholder="E-Mail" class="glass-field" />
            <Password
                v-model="password"
                placeholder="Passwort"
                :feedback="false"
                toggleMask
                class="glass-field"
                inputClass="password-inner"
            />
            <div class="form-footer">
                <Button label="Anmelden" icon="pi pi-sign-in" type="submit" class="login-button" />
                <a href="#" class="forgot-password">Passwort vergessen?</a>
            </div>
        </form>
    </AuthLayout>
</template>

<script setup>
/* Component: Login View (German UI, English code/comments) */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useModal } from '@/composables/useModal'
import AuthLayout from '@/components/layout/AuthLayout.vue'

const router = useRouter()
const auth = useAuthStore()
const modal = useModal()

const email = ref('')
const password = ref('')

// Handles login submit; keeps your OTP verify flow intact
const login = async () => {
  try {
    const response = await auth.login({
      email: email.value,
      password: password.value,
    })

    console.log('📦 Vollständige Login-Antwort:', response)

    if (response?.verify) {
      // Unverified account: show modal and route to OTP with email
      modal.show('Konto nicht verifiziert', 'Gib den Code ein, den wir dir per E-Mail geschickt haben.')
      router.push({ path: '/verify-otp', query: { email: response.email } })
    } else {
      // Normal login: go to dashboard
      console.log('🔁 Weiterleitung zum Dashboard...')
      router.push('/dashboard')
    }
  } catch (err) {
    console.error('❌ Login-Fehler:', err)
    // Generic German UI message; adjust to your backend messages if needed
    modal.show('Zugriffsfehler', 'Ungültige Anmeldedaten oder nicht vorhandenes Konto.')
  }
}
</script>

<style scoped>
.login-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  max-width: 400px;
  margin-top: 1rem;
}

.glass-field {
  width: 100%;
  height: 48px;
  font-size: 15px;
  background-color: rgba(255, 255, 255, 0.5) !important;
  border: 1px solid #000 !important;
  border-radius: 8px;
  backdrop-filter: blur(6px);
  padding: 0 1rem;
  box-sizing: border-box;
  color: #000;
}

.glass-field::placeholder {
  color: rgba(0, 0, 0, 0.8);
}

::v-deep(.password-inner::placeholder) {
  color: rgba(0, 0, 0, 0.8);
}

::v-deep(.password-inner) {
  background-color: transparent !important;
  border: none !important;
  color: #000;
  padding-left: 0;
  padding-right: 2.5rem !important;
  font-size: 15px;
  height: 48px;
  box-sizing: border-box;
}

.form-footer {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  margin-top: 1rem;
}

.login-button {
  background-color: #000;
  border: none;
  color: #fff;
  height: 40px;
  font-size: 14px;
  border-radius: 6px;
  padding: 0 2rem;
}

.login-button:hover {
  background-color: #222;
}

.forgot-password {
  font-size: 13px;
  text-align: center;
  color: #000;
  text-decoration: underline;
}
</style>