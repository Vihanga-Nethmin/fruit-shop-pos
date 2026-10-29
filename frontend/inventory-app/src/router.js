import { createRouter, createWebHistory } from 'vue-router'
import inventoryRoutes from './modules/inventory/routes'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/products' },
    ...inventoryRoutes,
  ],
})