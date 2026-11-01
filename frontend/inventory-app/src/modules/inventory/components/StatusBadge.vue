<script setup>
defineProps({
  tone: { type: String, default: 'gray' }, // green | orange | red | blue | gray
})
</script>

<template>
  <span class="badge" :class="tone"><slot /></span>
</template>

<style scoped>
.badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
.green { background: var(--inv-green-100); color: var(--inv-green-900); }
.orange { background: var(--inv-orange-bg); color: var(--inv-orange); }
.red { background: var(--inv-red-bg); color: var(--inv-red); }
.blue { background: var(--inv-blue-bg); color: var(--inv-blue); }
.gray { background: #eef1ef; color: var(--inv-muted); }
</style>