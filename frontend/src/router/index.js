import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

import RegisterView from '@/views/auth/RegisterView.vue';
import LoginView from '@/views/auth/LoginView.vue';
import VerifyOtpView from '@/views/auth/VerifyOtpView.vue';
import DashboardLayout from '@/components/layout/DashboardLayout.vue';
import HomeDashboard from '@/views/dashboard/HomeDashboard.vue';
import HomeView from '@/views/HomeView.vue';
import ForecastPanel from '@/views/dashboard/ForecastPanel.vue';
import DeviationPanel from '@/views/dashboard/DeviationPanel.vue';
import UserProfile from '@/views/dashboard/UserProfile.vue';

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
            { 
                path: '/forecasts', 
                component: ForecastPanel, 
                meta: { 
                    requiresAuth: true 
                }
            },
            { 
                path: '/deviations', 
                component: DeviationPanel, 
                meta: { 
                    requiresAuth: true 
                }
            },
            { 
                path: '/profile', 
                component: UserProfile, 
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

// Guard: only hit /api/user when a route needs auth
router.beforeEach(async (to, from, next) => {
    if (to.meta?.requiresAuth) {
        const auth = useAuthStore()
        try {
            if (!auth.user) {
                // Try to get the current user (will be 401 if not logged in)
                await auth.fetchUser()
            }
            if (!auth.user) {
                return next({ path: '/login', query: { redirect: to.fullPath } })
            }
        } catch (_) {
            return next({ path: '/login', query: { redirect: to.fullPath } })
        }
    }
    return next()
})

export default router;