<template>
    <div class="dashboard-bg">
        <div class="dashboard-layout">
            <header class="dashboard-header glass-nav">
                <div class="logo-container">
                    <img src="@/assets/img/logos/stb.png" alt="Logo" class="logo" />
                </div>
    
                <div class="nav-buttons">
                    <Button icon="pi pi-th-large" class="nav-icon-button" @click="$router.push('/dashboard')" />
                    
                    <Menu ref="quickMenu" :model="quickActions" popup />
                    <Button icon="pi pi-bolt" class="nav-icon-button" @click="quickMenu.toggle($event)" />
                    <!-- <Button icon="pi pi-user-plus" class="nav-icon-button" @click="inviteModal.show()" />
                    <InviteUserModal ref="inviteModal" /> -->
                    
                    <Menu ref="userMenu" :model="userItems" popup />
                    <Button icon="pi pi-user" class="nav-icon-button" @click="userMenu.toggle($event)" />
                </div>
            </header>
            <main class="dashboard-content">
                <router-view />
            </main>
        </div>
    </div>
    <InviteUserModal :visible="showInviteModal" @close="showInviteModal = false" />
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useModal } from '@/composables/useModal';
import InviteUserModal from '@/components/modals/InviteUserModal.vue';


const router = useRouter();
const auth = useAuthStore();
const modal = useModal();

const userMenu = ref();
const userItems = [
    {
        label: auth.user?.first_name || 'Usuario',
        items: [
            {
                label: (auth.roles && auth.roles.length > 0) ? auth.roles[0] : 'Sin rol',
                disabled: true,
            },
            {
                separator: true
            },
            {
                label: 'Editar perfil',
                icon: 'pi pi-pen-to-square',

            },
            {
                label: 'Modificar suscripcion',
                icon: 'pi pi-id-card',

            },
            {
                separator: true
            },
            {
                label: 'Cerrar sesión',
                icon: 'pi pi-sign-out',
                command: async () => {
                    await auth.logout(); // cerramos sesión
                    router.push('/login'); // redirigimos
                }
            }
        ]
    }
];

const showInviteModal = ref(false);
const quickMenu = ref()

const quickActions = [
  {
    label: 'Usuarios',
    items: [
      {
        label: 'Crear usuario',
        icon: 'pi pi-user-plus',
        command: () => {
            showInviteModal.value = true;
        }
      },
      {
        label: 'Crear Manager',
        icon: 'pi pi-briefcase',
        command: () => {
          // lógica futura para managers
        }
      }
    ]
  },
  {
    label: 'Clases',
    items: [
      {
        label: 'Crear clase',
        icon: 'pi pi-calendar-plus',
        command: () => {
          // abrir modal de clases
        }
      }
    ]
  },
  {
    label: 'Torneos',
    items: [
      {
        label: 'Crear torneo',
        icon: 'pi pi-trophy',
        command: () => {
          // abrir modal de clases
        }
      }
    ]
  }
]


</script>

<style scoped>
.dashboard-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.dashboard-bg {
    background-image: url('@/assets/img/backgrounds/green.jpg');    
    background-repeat: repeat;
    background-position: center;
    background-attachment: fixed;
    min-height: 100vh;
    width: 100%;
}

.dashboard-bg::after {
    content: "";
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.4);
	z-index: 1;
}

.dashboard-header {
    position: fixed;
    width: 100%;
    top: 0;
    z-index: 10;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 2rem;
    background: rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

.logo {
    height: 30px;
}

.nav-buttons {
    display: flex;
    align-items: center;
    gap: .25rem;
}

.nav-icon-button {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  color: #333;
  padding: 0.4rem;
  border-radius: 8px;
  transition: background 0.2s ease, color 0.2s ease;
}

.nav-icon-button:hover {
  background-color: transparent; /* sin fondo */
  color: #fff; /* ícono blanco */
}

/* Fix layout scroll under fixed navbar */
.dashboard-content {
    flex: 1;
    padding: 0 1rem;
    margin-top: 70px;
    z-index: 2;
}

.nav-buttons {
  display: flex;
  align-items: center;
  gap: .25rem;
}

.p-component{
    font-size: 1rem;
    font-weight: 300;
}
</style>
