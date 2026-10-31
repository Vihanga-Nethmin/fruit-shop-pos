<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api } from '../api/client'
import '../theme.css'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import Modal from '../components/Modal.vue'

const products = ref([])
const categories = ref([])
const meta = reactive({ page: 1, last: 1, total: 0 })
const loading = ref(false)
const message = ref('')
const error = ref('')

// Search and filter (current page)
const search = ref('')
const statusFilter = ref('ALL')

// Add form
const showForm = ref(false)
const saving = ref(false)
const formErrors = ref({})
const emptyForm = () => ({
  sku: '', name: '', category_id: '', variety: '', grade: '',
  base_unit: 'KG', selling_price: '', tax_rate: 0,
})
const form = reactive(emptyForm())

// Edit price
const editing = ref(null)
const priceForm = reactive({ selling_price: '', reason: '' })
const priceErrors = ref({})

const unitLabel = (u) => (u === 'KG' ? 'kg' : 'item')
const money = (v) => 'LKR ' + Number(v).toFixed(2)

function firstError(errs, field) {
  return errs && errs[field] ? errs[field][0] : ''
}

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return products.value.filter((p) => {
    if (statusFilter.value === 'ACTIVE' && !p.is_active) return false
    if (statusFilter.value === 'INACTIVE' && p.is_active) return false
    if (!q) return true
    return [p.sku, p.name, p.variety, p.grade].filter(Boolean).join(' ').toLowerCase().includes(q)
  })
})

const activeCount = computed(() => products.value.filter((p) => p.is_active).length)

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const r = await api('/products', { params: { page } })
    if (r.ok) {
      products.value = r.data.data.data
      meta.page = r.data.data.current_page
      meta.last = r.data.data.last_page
      meta.total = r.data.data.total
    } else {
      error.value = 'Could not load products (' + r.status + ').'
    }
  } catch (e) {
    error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  const r = await api('/categories')
  if (r.ok) categories.value = r.data.data.filter((c) => c.is_active)
}

function openForm() {
  Object.assign(form, emptyForm())
  formErrors.value = {}
  message.value = ''
  showForm.value = true
}

async function createProduct() {
  saving.value = true
  formErrors.value = {}
  const body = {
    sku: form.sku,
    name: form.name,
    category_id: form.category_id,
    variety: form.variety || null,
    grade: form.grade || null,
    base_unit: form.base_unit,
    sale_unit: form.base_unit,
    selling_price: form.selling_price === '' ? null : Number(form.selling_price),
    tax_rate: Number(form.tax_rate || 0),
  }
  const r = await api('/products', { method: 'POST', body })
  saving.value = false

  if (r.status === 201) {
    showForm.value = false
    message.value = 'Product added: ' + r.data.data.sku
    await load(1)
  } else if (r.status === 422) {
    formErrors.value = r.data.errors || {}
  } else {
    formErrors.value = { general: ['Something went wrong (' + r.status + ').'] }
  }
}

function openPrice(p) {
  editing.value = p
  priceForm.selling_price = p.selling_price
  priceForm.reason = ''
  priceErrors.value = {}
  message.value = ''
}

async function savePrice() {
  priceErrors.value = {}
  const r = await api('/products/' + editing.value.id, {
    method: 'PATCH',
    body: { selling_price: Number(priceForm.selling_price), reason: priceForm.reason },
  })
  if (r.ok) {
    message.value = 'Price updated for ' + editing.value.sku
    editing.value = null
    await load(meta.page)
  } else if (r.status === 422) {
    priceErrors.value = r.data.errors || {}
  } else {
    priceErrors.value = { general: ['Something went wrong (' + r.status + ').'] }
  }
}

async function toggleActive(p) {
  const action = p.is_active ? 'Deactivate' : 'Activate'
  if (!window.confirm(action + ' ' + p.name + '? The record is kept.')) return
  const r = await api('/products/' + p.id, { method: 'PATCH', body: { is_active: !p.is_active } })
  if (r.ok) {
    message.value = p.name + (p.is_active ? ' deactivated.' : ' activated.')
    await load(meta.page)
  } else {
    error.value = 'Could not update the product (' + r.status + ').'
  }
}

onMounted(() => {
  load()
  loadCategories()
})
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Products">
      <button class="inv-btn primary" @click="openForm">+ Add product</button>
    </PageHeader>

    <p v-if="message" class="inv-alert-ok">{{ message }}</p>
    <p v-if="error" class="inv-alert-err">{{ error }}</p>

    <div class="inv-grid-4">
      <StatCard label="Total products" :value="meta.total" hint="In the catalogue" icon="🍎" tone="green" />
      <StatCard label="Active (this page)" :value="activeCount" hint="Available to sell" icon="✅" tone="blue" />
      <StatCard label="Categories" :value="categories.length" hint="Active categories" icon="🗂️" tone="orange" />
    </div>

    <div class="inv-card">
      <div class="toolbar">
        <input v-model="search" class="search" placeholder="Search SKU, name, variety..." />
        <div class="chips">
          <button class="chip" :class="{ on: statusFilter === 'ALL' }" @click="statusFilter = 'ALL'">All</button>
          <button class="chip" :class="{ on: statusFilter === 'ACTIVE' }" @click="statusFilter = 'ACTIVE'">Active</button>
          <button class="chip" :class="{ on: statusFilter === 'INACTIVE' }" @click="statusFilter = 'INACTIVE'">Inactive</button>
        </div>
      </div>

      <p v-if="loading" class="inv-empty">Loading...</p>

      <div v-else class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th>Product</th><th>Variety / Grade</th><th>Unit</th>
              <th class="num">Price</th><th>Status</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in visible" :key="p.id" :class="{ off: !p.is_active }">
              <td>{{ p.name }}<small>{{ p.sku }}</small></td>
              <td>{{ [p.variety, p.grade].filter(Boolean).join(' / ') || '-' }}</td>
              <td>{{ unitLabel(p.base_unit) }}</td>
              <td class="num">{{ money(p.selling_price) }} <small>per {{ unitLabel(p.base_unit) }}</small></td>
              <td><StatusBadge :tone="p.is_active ? 'green' : 'gray'">{{ p.is_active ? 'Active' : 'Inactive' }}</StatusBadge></td>
              <td class="actions">
                <button class="inv-btn sm" @click="openPrice(p)">Edit price</button>
                <button class="inv-btn sm" @click="toggleActive(p)">{{ p.is_active ? 'Deactivate' : 'Activate' }}</button>
              </td>
            </tr>
            <tr v-if="!visible.length"><td colspan="6" class="inv-empty">No products found.</td></tr>
          </tbody>
        </table>
      </div>

      <footer class="pager" v-if="meta.last > 1">
        <button class="inv-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Previous</button>
        <span>Page {{ meta.page }} of {{ meta.last }} ({{ meta.total }} products)</span>
        <button class="inv-btn" :disabled="meta.page >= meta.last" @click="load(meta.page + 1)">Next</button>
      </footer>
    </div>

    <!-- Add product -->
    <Modal v-if="showForm" title="Add product" @close="showForm = false">
      <form class="form" @submit.prevent="createProduct">
        <p v-if="formErrors.general" class="inv-alert-err">{{ formErrors.general[0] }}</p>

        <label>SKU
          <input v-model="form.sku" />
          <small class="err">{{ firstError(formErrors, 'sku') }}</small>
        </label>
        <label>Name
          <input v-model="form.name" />
          <small class="err">{{ firstError(formErrors, 'name') }}</small>
        </label>
        <label>Category
          <select v-model="form.category_id">
            <option value="" disabled>Select a category</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <small class="err">{{ firstError(formErrors, 'category_id') }}</small>
        </label>
        <div class="row">
          <label>Variety <input v-model="form.variety" /></label>
          <label>Grade <input v-model="form.grade" /></label>
        </div>
        <div class="row">
          <label>Sold by
            <select v-model="form.base_unit">
              <option value="KG">Weight (kg)</option>
              <option value="EACH">Count (item)</option>
            </select>
          </label>
          <label>Price (LKR per {{ unitLabel(form.base_unit) }})
            <input v-model="form.selling_price" type="number" step="0.01" min="0" />
            <small class="err">{{ firstError(formErrors, 'selling_price') }}</small>
          </label>
          <label>Tax %
            <input v-model="form.tax_rate" type="number" step="0.01" min="0" max="100" />
          </label>
        </div>

        <div class="buttons">
          <button type="button" class="inv-btn" @click="showForm = false">Cancel</button>
          <button class="inv-btn primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Edit price -->
    <Modal v-if="editing" :title="'Change price: ' + editing.name" width="440px" @close="editing = null">
      <form class="form" @submit.prevent="savePrice">
        <p v-if="priceErrors.general" class="inv-alert-err">{{ priceErrors.general[0] }}</p>

        <label>New price (LKR per {{ unitLabel(editing.base_unit) }})
          <input v-model="priceForm.selling_price" type="number" step="0.01" min="0" />
          <small class="err">{{ firstError(priceErrors, 'selling_price') }}</small>
        </label>
        <label>Reason (required)
          <input v-model="priceForm.reason" />
          <small class="err">{{ firstError(priceErrors, 'reason') }}</small>
        </label>

        <div class="buttons">
          <button type="button" class="inv-btn" @click="editing = null">Cancel</button>
          <button class="inv-btn primary">Save price</button>
        </div>
      </form>
    </Modal>
  </section>
</template>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.search { flex: 1; min-width: 220px; max-width: 360px; padding: 9px 14px; border: 1px solid var(--inv-border); border-radius: 10px; font-size: 13.5px; background: var(--inv-bg); }
.chips { display: flex; gap: 8px; }
.chip { border: 1px solid var(--inv-border); background: #fff; border-radius: 999px; padding: 6px 14px; font-size: 13px; cursor: pointer; color: var(--inv-text); }
.chip.on { background: var(--inv-green-900); color: #fff; border-color: var(--inv-green-900); }
.off td { color: var(--inv-muted); }
.actions { white-space: nowrap; text-align: right; }
.sm { padding: 5px 10px; font-size: 12px; margin-left: 6px; }
.pager { display: flex; justify-content: center; align-items: center; gap: 14px; margin-top: 14px; font-size: 13px; color: var(--inv-muted); }
.form { display: grid; gap: 12px; }
.form label { display: grid; gap: 4px; font-size: 13px; font-weight: 600; }
.form input, .form select { padding: 9px 10px; font-size: 14px; border: 1px solid var(--inv-border); border-radius: 10px; font-weight: 400; }
.form input:focus, .form select:focus, .search:focus { outline: 2px solid var(--inv-green-100); border-color: var(--inv-green-700); }
.row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
.err { color: var(--inv-red); font-weight: 400; }
.buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
</style>