<script setup>
defineProps({
  crumb: { type: String, default: 'Inventory' },
  title: { type: String, required: true },
})
</script>

<template>
  <header class="head">
    <div>
      <p class="crumb">Pages / {{ crumb }}</p>
      <h1>{{ title }}</h1>
    </div>
    <div class="actions"><slot /></div>
  </header>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.crumb { margin: 0 0 4px; font-size: 12.5px; color: var(--inv-muted); }
h1 { margin: 0; font-size: 28px; }
.actions { display: flex; align-items: center; gap: 10px; }
</style>