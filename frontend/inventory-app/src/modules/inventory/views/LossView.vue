<script setup>
import { ref, onMounted } from 'vue'
import '../theme.css'
import { api } from '../api/client'
import { loadLookups, unwrapList } from '../api/lookups'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import LossPanel from '../components/LossPanel.vue'

const tab = ref('wastage')
const lookups = ref({ suppliers: [], locations: [], products: [] })
const ready = ref(false)
const error = ref('')
const pending = ref({ wastage: 0, supplier: 0 })

async function loadPending() {
  const [w, s] = await Promise.all([
    api('/wastage-records', { params: { status: 'SUBMITTED' } }),
    api('/supplier-returns', { params: { status: 'SUBMITTED' } }),
  ])
  pending.value = {
    wastage: w.ok ? unwrapList(w).total : 0,
    supplier: s.ok ? unwrapList(s).total : 0,
  }
}

onMounted(async () => {
  try {
    lookups.value = await loadLookups()
    await loadPending()
  } catch (e) {
    error.value = 'Could not load data. Is php artisan serve running?'
  } finally {
    ready.value = true
  }
})
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Loss and returns" />

    <p v-if="error" class="inv-alert-err">{{ error }}</p>

    <div class="inv-grid-4">
      <StatCard label="Wastage awaiting approval" :value="pending.wastage" hint="Owner decision needed" icon="🗑️" tone="orange" />
      <StatCard label="Supplier returns awaiting approval" :value="pending.supplier" hint="Owner decision needed" icon="↩️" tone="blue" />
    </div>

    <div class="inv-chips" style="margin-bottom: 16px">
      <button class="inv-chip" :class="{ on: tab === 'wastage' }" @click="tab = 'wastage'">Wastage</button>
      <button class="inv-chip" :class="{ on: tab === 'supplier' }" @click="tab = 'supplier'">Supplier returns</button>
    </div>

    <LossPanel v-if="ready" :key="tab" :kind="tab" :lookups="lookups" @changed="loadPending" />
  </section>
</template>