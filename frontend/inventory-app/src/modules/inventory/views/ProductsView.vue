<script setup>
import { ref, reactive, onMounted } from 'vue'
import { api } from '../api/client'

const products = ref([])
const categories = ref([])
const meta = reactive({ page: 1, last: 1, total: 0 })
const loading = ref(false)
const message = ref('')
const error = ref('')

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
  <section class="page">
    <header class="bar">
      <h2>Products</h2>
      <button class="primary" @click="openForm">+ Add product</button>
    </header>

    <p v-if="message" class="ok">{{ message }}</p>
    <p v-if="error" class="err">{{ error }}</p>
    <p v-if="loading">Loading...</p>

    <table v-else>
      <thead>
        <tr>
          <th>SKU</th><th>Name</th><th>Variety / Grade</th><th>Unit</th>
          <th class="num">Price</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in products" :key="p.id" :class="{ inactive: !p.is_active }">
          <td>{{ p.sku }}</td>
          <td>{{ p.name }}</td>
          <td>{{ [p.variety, p.grade].filter(Boolean).join(' / ') || '-' }}</td>
          <td>{{ unitLabel(p.base_unit) }}</td>
          <td class="num">{{ money(p.selling_price) }} per {{ unitLabel(p.base_unit) }}</td>
          <td>{{ p.is_active ? 'Active' : 'Inactive' }}</td>
          <td class="actions">
            <button @click="openPrice(p)">Edit price</button>
            <button @click="toggleActive(p)">{{ p.is_active ? 'Deactivate' : 'Activate' }}</button>
          </td>
        </tr>
        <tr v-if="!products.length"><td colspan="7">No products yet.</td></tr>
      </tbody>
    </table>

    <footer class="pager" v-if="meta.last > 1">
      <button :disabled="meta.page <= 1" @click="load(meta.page - 1)">Previous</button>
      <span>Page {{ meta.page }} of {{ meta.last }} ({{ meta.total }} products)</span>
      <button :disabled="meta.page >= meta.last" @click="load(meta.page + 1)">Next</button>
    </footer>

    <!-- Add product -->
    <div v-if="showForm" class="overlay" @click.self="showForm = false">
      <form class="dialog" @submit.prevent="createProduct">
        <h3>Add product</h3>
        <p v-if="formErrors.general" class="err">{{ formErrors.general[0] }}</p>

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
          <button type="button" @click="showForm = false">Cancel</button>
          <button class="primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
        </div>
      </form>
    </div>

    <!-- Edit price -->
    <div v-if="editing" class="overlay" @click.self="editing = null">
      <form class="dialog" @submit.prevent="savePrice">
        <h3>Change price: {{ editing.name }}</h3>
        <p v-if="priceErrors.general" class="err">{{ priceErrors.general[0] }}</p>

        <label>New price (LKR per {{ unitLabel(editing.base_unit) }})
          <input v-model="priceForm.selling_price" type="number" step="0.01" min="0" />
          <small class="err">{{ firstError(priceErrors, 'selling_price') }}</small>
        </label>
        <label>Reason (required)
          <input v-model="priceForm.reason" />
          <small class="err">{{ firstError(priceErrors, 'reason') }}</small>
        </label>

        <div class="buttons">
          <button type="button" @click="editing = null">Cancel</button>
          <button class="primary">Save price</button>
        </div>
      </form>
    </div>
  </section>
</template>

<style scoped>
.page { max-width: 1000px; margin: 0 auto; padding: 24px; font-family: sans-serif; }
.bar { display: flex; justify-content: space-between; align-items: center; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
.num { text-align: right; }
.inactive { color: #999; background: #fafafa; }
.actions button { margin-right: 6px; }
button { padding: 6px 12px; cursor: pointer; }
.primary { background: #1f3a5f; color: #fff; border: 0; border-radius: 4px; }
.ok { background: #e6f4ea; color: #1b5e20; padding: 8px 12px; border-radius: 4px; }
.err { color: #b00020; }
.pager { display: flex; gap: 12px; align-items: center; margin-top: 12px; }
.overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, .35); display: grid; place-items: center; }
.dialog { background: #fff; padding: 20px; border-radius: 8px; width: min(560px, 92vw); display: grid; gap: 10px; }
.dialog label { display: grid; gap: 4px; font-size: 14px; }
.dialog input, .dialog select { padding: 6px; font-size: 14px; }
.row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.buttons { display: flex; justify-content: flex-end; gap: 8px; }
</style>