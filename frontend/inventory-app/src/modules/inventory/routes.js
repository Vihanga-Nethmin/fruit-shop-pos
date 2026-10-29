// Inventory module eke routes okkoma meke. Host app (dashboard) eka meka import karanna.
export default [
  { path: '/products', name: 'inventory-products', component: () => import('./views/ProductsView.vue') },
]