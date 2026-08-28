import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'Dashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: { agentRestricted: true },
      },
      {
        path: 'companies',
        name: 'Companies',
        component: () => import('@/views/CompaniesView.vue'),
        meta: { agentRestricted: true },
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('@/views/UsersView.vue'),
        meta: { agentRestricted: true },
      },
      {
        path: 'clients',
        name: 'Clients',
        component: () => import('@/views/ClientsView.vue'),
        meta: { agentRestricted: true },
      },
      {
        path: 'clients/:id',
        name: 'ClientDetail',
        component: () => import('@/views/ClientDetailView.vue'),
        meta: { agentRestricted: true },
      },
      {
        path: 'import',
        name: 'ExcelImport',
        component: () => import('@/views/ExcelImportView.vue'),
      },
      {
        path: 'scans',
        name: 'Scans',
        component: () => import('@/views/ScansView.vue'),
        meta: { agentRestricted: true },
      },
      {
        path: 'whatsapp',
        name: 'WhatsApp',
        component: () => import('@/views/WhatsAppView.vue'),
      },
      {
        path: 'reports',
        name: 'Reports',
        component: () => import('@/views/ReportsView.vue'),
      },
      {
        path: 'performance',
        name: 'Performance',
        component: () => import('@/views/PerformanceView.vue'),
      },
      {
        path: 'map',
        name: 'Map',
        component: () => import('@/views/MapView.vue'),
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.meta.guest && authStore.isAuthenticated) {
    next('/')
  } else if (to.meta.agentRestricted && authStore.user?.role === 'agent') {
    next('/import')
  } else {
    next()
  }
})

export default router