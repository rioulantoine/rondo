import { createRouter, createWebHistory } from 'vue-router'
import { session, loadSession, can } from './api'

const routes = [
  { path: '/connexion', component: () => import('./views/LoginView.vue'), meta: { public: true } },
  { path: '/qui', component: () => import('./views/IdentityView.vue'), meta: { needsRestaurant: true } },
  { path: '/', component: () => import('./views/HomeView.vue'), meta: { shell: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: () => ({ top: 0 }) })

router.beforeEach(async (to) => {
  if (!session.ready) await loadSession()
  if (to.meta.public) return session.person && to.path === '/connexion' ? '/' : true
  if (!session.restaurant) return '/connexion'
  if (to.meta.needsRestaurant) return true
  if (!session.person) return '/qui'
  if (to.meta.perm && !can(to.meta.perm)) return '/'
  return true
})
