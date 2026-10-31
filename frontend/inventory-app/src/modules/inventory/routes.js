// Inventory module eke routes okkoma meke. Host app (dashboard) eka meka import karanna.
export default [
  { path: '/stock', name: 'inventory-stock', component: () => import('./views/StockView.vue') },
  { path: '/receiving', name: 'inventory-receiving', component: () => import('./views/ReceivingView.vue') },
  { path: '/products', name: 'inventory-products', component: () => import('./views/ProductsView.vue') },
]