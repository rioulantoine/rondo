import { createRouter, createWebHistory } from 'vue-router'
import { session, loadSession, can } from './api'

const routes = [
  { path: '/connexion', component: () => import('./views/LoginView.vue'), meta: { public: true } },
  { path: '/qui', component: () => import('./views/IdentityView.vue'), meta: { needsRestaurant: true } },
  { path: '/', component: () => import('./views/HomeView.vue'), meta: { shell: true } },
  { path: '/registres', component: () => import('./views/RegistresView.vue'), meta: { shell: true } },
  { path: '/registres/:sp(cuisine|salle|bar)', component: () => import('./views/EspaceView.vue'), meta: { shell: true, permFromParam: 'sp' } },
  { path: '/pointer', component: () => import('./views/PointerView.vue'), meta: { shell: true } },
  { path: '/planning', component: () => import('./views/PlanningView.vue'), meta: { shell: true, wide: true } },
  { path: '/equipe', component: () => import('./views/EquipeView.vue'), meta: { shell: true, owner: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: () => ({ top: 0 }) })

router.beforeEach(async (to) => {
  if (!session.ready) await loadSession()
  if (to.meta.public) return session.person && to.path === '/connexion' ? '/' : true
  if (!session.restaurant) return '/connexion'
  if (to.meta.needsRestaurant) return true
  if (!session.person) return '/qui'
  if (to.meta.owner && !session.person.owner) return '/'
  if (to.meta.permFromParam && !can(to.params[to.meta.permFromParam])) return '/registres'
  return true
})
