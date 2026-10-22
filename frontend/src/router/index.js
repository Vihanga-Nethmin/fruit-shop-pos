import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import CreateSale from '../components/CreateSale.vue';
import SalesList from '../components/SalesList.vue';
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      
    },
    {
      path: '/about',
      name: 'about',
      // route level code-splitting
      // this generates a separate chunk (About.[hash].js) for this route
      // which is lazy-loaded when the route is visited.
      component: () => import('../views/AboutView.vue'),
    },
    {
      path: '/create-sale',
      name: 'CreateSale',
      component: CreateSale
    },
    {
      path: '/sales-list',
      name: 'SalesList',
     component: SalesList
}
    
    
  ],
})

export default router
