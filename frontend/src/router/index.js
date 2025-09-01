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
import DashboardEditor from '@/views/dashboard/DashboardEditor.vue';
import ExtraQuotaPanel from '@/views/dashboard/ExtraQuotaPanel.vue';
import BudgetCasePanel from '@/views/dashboard/BudgetCasePanel.vue';
import ActionPlanPanel from '@/views/dashboard/ActionPlanPanel.vue';
import SalesForce from '@/views/dashboard/manager/SalesForce.vue';
import ReportGenerator from '@/views/dashboard/manager/ReportGenerator.vue';
import CompanyAnalytics from '@/views/dashboard/manager/CompanyAnalytics.vue';
import ExtraQuotaAnalysis from '@/views/dashboard/ExtraQuotaAnalysis.vue';

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
                path: '/budget-cases', 
                component: BudgetCasePanel, 
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
                path: '/extra-quotas', 
                component: ExtraQuotaPanel, 
                meta: { 
                    requiresAuth: true 
                }
            },
            { 
                path: '/extra-quota/analyse', 
                component: ExtraQuotaAnalysis, 
                meta: { 
                    requiresAuth: true 
                }
            },
            { 
                path: '/action-plans', 
                component: ActionPlanPanel, 
                meta: { 
                    requiresAuth: true 
                }
            },
            { 
                path: '/sales-force', 
                component: SalesForce, 
                meta: { 
                    requiresAuth: true 
                }
            },
            { 
                path: '/report-generator', 
                component: ReportGenerator, 
                meta: { 
                    requiresAuth: true 
                }
            },
            { 
                path: '/company-analytics', 
                component: CompanyAnalytics, 
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
            { 
                path: '/edit', 
                component: DashboardEditor, 
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