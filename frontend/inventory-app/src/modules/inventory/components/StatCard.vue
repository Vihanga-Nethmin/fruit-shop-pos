<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  hint: { type: String, default: '' },
  icon: { type: String, default: '📦' },
  tone: { type: String, default: 'green' }, // green | orange | red | blue
})
</script>

<template>
  <div class="inv-card stat">
    <div class="tile" :class="tone">{{ icon }}</div>
    <p class="label">{{ label }}</p>
    <p class="value">{{ value }}</p>
    <p class="hint" v-if="hint">{{ hint }}</p>
  </div>
</template>

<style scoped>
.stat { position: relative; }
.tile { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; margin-bottom: 14px; }
.tile.green { background: var(--inv-green-100); }
.tile.orange { background: var(--inv-orange-bg); }
.tile.red { background: var(--inv-red-bg); }
.tile.blue { background: var(--inv-blue-bg); }
.label { margin: 0; font-size: 13px; color: var(--inv-muted); }
.value { margin: 4px 0 2px; font-size: 26px; font-weight: 700; }
.hint { margin: 0; font-size: 12px; color: var(--inv-muted); }
</style>