<script setup>
defineProps({
  title: { type: String, required: true },
  width: { type: String, default: '560px' },
})
const emit = defineEmits(['close'])
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="dialog" :style="{ width: 'min(' + width + ', 92vw)' }">
      <header class="top">
        <h3>{{ title }}</h3>
        <button type="button" class="x" @click="emit('close')">✕</button>
      </header>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(11, 40, 28, 0.45); display: grid; place-items: center; z-index: 50; }
.dialog { background: #fff; border-radius: 16px; padding: 22px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2); }
.top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.top h3 { margin: 0; font-size: 18px; }
.x { border: 0; background: #eef1ef; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; }
</style>