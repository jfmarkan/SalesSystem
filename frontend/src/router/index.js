import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

import RegisterView from '@/views/auth/RegisterView.vue';
import LoginView from '@/views/auth/LoginView.vue';
import VerifyOtpView from '@/views/auth/VerifyOtpView.vue';
import DashboardLayout from '@/components/layout/DashboardLayout.vue';
import HomeDashboard from '@/views/dashboard/HomeDashboard.vue';
import HomeView from '@/views/HomeView.vue';

const routes = [
    {
        path: '/',
        component: HomeView
    },
    {
        path: '/login',
        component: LoginView
    },
    {
        path: '/register',
        component: RegisterView
    },
    {
        path: '/verify-otp',
        component: VerifyOtpView
    },
    {
        path: '/dashboard',
        component: DashboardLayout,
        children: [
            { 
                path: '', 
                component: HomeDashboard, 
                meta: { 
                    requiresAuth: true 
                }
            },
        ],
        meta: { requiresAuth: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

router.beforeEach(async (to, from, next) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth) {
        if (!auth.user) {
            try {
                await auth.fetchUser();
            } catch (e) {
                return next('/login');
            }
        }
        if (!auth.user) {
            return next('/login');
        }
    }
    return next();
});

export default router;