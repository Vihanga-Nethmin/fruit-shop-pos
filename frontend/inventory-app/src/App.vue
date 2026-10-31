<script setup>
const links = [
  { to: '/stock', label: 'Stock', icon: '📊' },
  { to: '/receiving', label: 'Receiving', icon: '🚚' },
  { to: '/products', label: 'Products', icon: '🍎' },
]
</script>

<template>
  <div class="shell">
    <aside class="side">
      <div class="brand">🍏 <span>Fruit Shop POS</span></div>
      <p class="menu">INVENTORY</p>
      <RouterLink v-for="l in links" :key="l.to" :to="l.to" class="link" active-class="on">
        <span>{{ l.icon }}</span> {{ l.label }}
      </RouterLink>
      <div class="user">
        <div class="avatar">SM</div>
        <div><strong>Store Manager</strong><small>Administrator</small></div>
      </div>
    </aside>
    <main class="content"><RouterView /></main>
  </div>
</template>

<style>
body { margin: 0; }
</style>

<style scoped>
.shell { display: flex; min-height: 100vh; font-family: 'Inter', system-ui, sans-serif; }
.side { width: 230px; background: #0b5a35; color: #fff; padding: 20px 14px; display: flex; flex-direction: column; gap: 4px; position: sticky; top: 0; height: 100vh; box-sizing: border-box; }
.brand { font-weight: 700; font-size: 17px; padding: 6px 10px 20px; }
.menu { margin: 8px 10px; font-size: 11px; letter-spacing: 1px; color: #9bcdb2; }
.link { display: flex; gap: 10px; align-items: center; padding: 10px 12px; border-radius: 10px; color: #dff0e6; text-decoration: none; font-size: 14px; }
.link:hover { background: rgba(255, 255, 255, 0.08); }
.link.on { background: rgba(255, 255, 255, 0.16); color: #fff; }
.user { margin-top: auto; display: flex; gap: 10px; align-items: center; padding: 10px; border-top: 1px solid rgba(255, 255, 255, 0.15); }
.user small { display: block; color: #9bcdb2; font-size: 12px; }
.avatar { width: 34px; height: 34px; border-radius: 50%; background: #14784a; display: grid; place-items: center; font-size: 12px; font-weight: 700; }
.content { flex: 1; min-width: 0; background: #f4f7f5; }
</style>