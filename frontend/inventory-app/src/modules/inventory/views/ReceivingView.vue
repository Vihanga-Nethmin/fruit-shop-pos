<script setup>
import { ref, onMounted } from 'vue'
import '../theme.css'
import { loadLookups } from '../api/lookups'
import PageHeader from '../components/PageHeader.vue'
import GoodsReceiptsPanel from '../components/GoodsReceiptsPanel.vue'
import PurchaseOrdersPanel from '../components/PurchaseOrdersPanel.vue'

const tab = ref('receipts')
const lookups = ref({ suppliers: [], locations: [], products: [] })
const ready = ref(false)
const error = ref('')

onMounted(async () => {
  try {
    lookups.value = await loadLookups()
  } catch (e) {
    error.value = 'Could not load suppliers, products and locations. Is php artisan serve running?'
  } finally {
    ready.value = true
  }
})
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Receiving" />

    <p v-if="error" class="inv-alert-err">{{ error }}</p>

    <div class="inv-chips" style="margin-bottom: 16px">
      <button class="inv-chip" :class="{ on: tab === 'receipts' }" @click="tab = 'receipts'">Goods receipts</button>
      <button class="inv-chip" :class="{ on: tab === 'orders' }" @click="tab = 'orders'">Purchase orders</button>
    </div>

    <template v-if="ready">
      <GoodsReceiptsPanel v-if="tab === 'receipts'" :lookups="lookups" />
      <PurchaseOrdersPanel v-else :lookups="lookups" />
    </template>
  </section>
</template>