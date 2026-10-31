<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api } from '../api/client'
import { unwrapList, errOf, today, canSeeCost, statusTone, prettyStatus, money, apiMessage } from '../api/lookups'
import StatusBadge from './StatusBadge.vue'
import Modal from './Modal.vue'

const props = defineProps({ lookups: { type: Object, required: true } })

const rows = ref([])
const meta = reactive({ page: 1, last: 1, total: 0 })
const loading = ref(false)
const message = ref('')
const error = ref('')

const showForm = ref(false)
const saving = ref(false)
const errors = ref({})
const viewing = ref(null)

const emptyLine = () => ({ product_id: '', ordered_qty: '', agreed_unit_cost: '', container_count: '' })
const form = reactive({ supplier_id: '', reference: '', order_date: today(), expected_date: '', notes: '', lines: [emptyLine()] })

const activeSuppliers = computed(() => props.lookups.suppliers.filter((s) => s.is_active !== false))
const activeProducts = computed(() => props.lookups.products.filter((p) => p.is_active !== false))
const supplierName = (id) => props.lookups.suppliers.find((s) => s.id === id)?.name || String(id).slice(0, 8)
const productOf = (id) => props.lookups.products.find((p) => p.id === id)
const productName = (id) => productOf(id)?.name || String(id).slice(0, 8)
const unitOf = (l) => (productOf(l.product_id)?.base_unit === 'EACH' ? 'item' : 'kg')
const stepOf = (l) => (unitOf(l) === 'item' ? '1' : '0.001')
const le = (i, f) => errOf(errors.value, 'lines.' + i + '.' + f)

const num = (v) => (v === '' || v === null ? undefined : Number(v))
const str = (v) => (v === '' || v === null ? undefined : v)

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const r = await api('/purchase-orders', { params: { page } })
    if (r.ok) {
      const l = unwrapList(r)
      rows.value = l.items
      Object.assign(meta, { page: l.page, last: l.last, total: l.total })
    } else {
      error.value = apiMessage(r, 'Could not load purchase orders')
    }
  } catch (e) {
    error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    loading.value = false
  }
}

function openForm() {
  Object.assign(form, { supplier_id: '', reference: '', order_date: today(), expected_date: '', notes: '', lines: [emptyLine()] })
  errors.value = {}
  message.value = ''
  showForm.value = true
}

const addLine = () => form.lines.push(emptyLine())
const removeLine = (i) => form.lines.splice(i, 1)

async function createOrder() {
  saving.value = true
  errors.value = {}
  const body = {
    supplier_id: form.supplier_id,
    reference: str(form.reference),
    order_date: form.order_date,
    expected_date: str(form.expected_date),
    notes: str(form.notes),
    lines: form.lines.map((l) => ({
      product_id: l.product_id,
      ordered_qty: num(l.ordered_qty),
      agreed_unit_cost: num(l.agreed_unit_cost),
      container_count: num(l.container_count),
    })),
  }
  const r = await api('/purchase-orders', { method: 'POST', body })
  saving.value = false

  if (r.status === 201) {
    showForm.value = false
    message.value = 'Purchase order created as a draft.'
    await load(1)
  } else if (r.status === 422) {
    errors.value = r.data.errors || {}
  } else {
    errors.value = { general: [apiMessage(r, 'Something went wrong')] }
  }
}

async function view(row) {
  const r = await api('/purchase-orders/' + row.id)
  if (r.ok) viewing.value = r.data.data
  else error.value = apiMessage(r, 'Could not open the order')
}

async function act(row, action, confirmText, doneText) {
  if (!window.confirm(confirmText)) return
  message.value = ''
  error.value = ''
  const r = await api('/purchase-orders/' + row.id + '/' + action, { method: 'POST' })
  if (r.ok) {
    message.value = doneText
    await load(meta.page)
  } else {
    error.value = apiMessage(r, 'Action failed')
  }
}

const approve = (row) => act(row, 'approve', 'Approve this purchase order?', 'Purchase order approved.')
const cancel = (row) => act(row, 'cancel', 'Cancel this order? Outstanding quantity is cancelled.', 'Purchase order updated.')

onMounted(() => load())
</script>

<template>
  <div class="inv-card">
    <div class="inv-toolbar">
      <div><h3>Purchase orders</h3><p class="sub" style="margin-bottom:0">Orders placed with suppliers.</p></div>
      <button class="inv-btn primary" @click="openForm">+ New order</button>
    </div>

    <p v-if="message" class="inv-alert-ok">{{ message }}</p>
    <p v-if="error" class="inv-alert-err">{{ error }}</p>
    <p v-if="loading" class="inv-empty">Loading...</p>

    <div v-else class="inv-table-wrap">
      <table class="inv-table">
        <thead>
          <tr><th>Reference</th><th>Supplier</th><th>Order date</th><th>Expected</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="o in rows" :key="o.id">
            <td>{{ o.reference || '-' }}</td>
            <td>{{ supplierName(o.supplier_id) }}</td>
            <td>{{ o.order_date }}</td>
            <td>{{ o.expected_date || '-' }}</td>
            <td><StatusBadge :tone="statusTone(o.status)">{{ prettyStatus(o.status) }}</StatusBadge></td>
            <td class="inv-actions">
              <button class="inv-btn sm" @click="view(o)">View</button>
              <button v-if="o.status === 'DRAFT'" class="inv-btn sm primary" @click="approve(o)">Approve</button>
              <button v-if="o.status === 'DRAFT' || o.status === 'APPROVED'" class="inv-btn sm" @click="cancel(o)">Cancel</button>
            </td>
          </tr>
          <tr v-if="!rows.length"><td colspan="6" class="inv-empty">No purchase orders yet.</td></tr>
        </tbody>
      </table>
    </div>

    <footer class="inv-pager" v-if="meta.last > 1">
      <button class="inv-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Previous</button>
      <span>Page {{ meta.page }} of {{ meta.last }} ({{ meta.total }} orders)</span>
      <button class="inv-btn" :disabled="meta.page >= meta.last" @click="load(meta.page + 1)">Next</button>
    </footer>
  </div>

  <!-- New order -->
  <Modal v-if="showForm" title="New purchase order" width="720px" @close="showForm = false">
    <form class="inv-form" @submit.prevent="createOrder">
      <p v-if="errors.general" class="inv-alert-err">{{ errors.general[0] }}</p>

      <div class="inv-row">
        <label>Supplier
          <select v-model="form.supplier_id">
            <option value="" disabled>Select a supplier</option>
            <option v-for="s in activeSuppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <small class="inv-err">{{ errOf(errors, 'supplier_id') }}</small>
        </label>
        <label>Reference <input v-model="form.reference" /></label>
      </div>
      <div class="inv-row">
        <label>Order date
          <input v-model="form.order_date" type="date" />
          <small class="inv-err">{{ errOf(errors, 'order_date') }}</small>
        </label>
        <label>Expected date
          <input v-model="form.expected_date" type="date" />
          <small class="inv-err">{{ errOf(errors, 'expected_date') }}</small>
        </label>
      </div>

      <div v-for="(l, i) in form.lines" :key="i" class="inv-line">
        <div class="inv-line-head">
          <strong>Line {{ i + 1 }}</strong>
          <button v-if="form.lines.length > 1" type="button" class="inv-btn sm" @click="removeLine(i)">Remove</button>
        </div>
        <div class="inv-row">
          <label>Product
            <select v-model="l.product_id">
              <option value="" disabled>Select a product</option>
              <option v-for="p in activeProducts" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
            </select>
            <small class="inv-err">{{ le(i, 'product_id') }}</small>
          </label>
        </div>
        <div class="inv-row">
          <label>Ordered ({{ unitOf(l) }})
            <input v-model="l.ordered_qty" type="number" :step="stepOf(l)" min="0" />
            <small class="inv-err">{{ le(i, 'ordered_qty') }}</small>
          </label>
          <label>Agreed cost (LKR per {{ unitOf(l) }})
            <input v-model="l.agreed_unit_cost" type="number" step="0.01" min="0" />
            <small class="inv-err">{{ le(i, 'agreed_unit_cost') }}</small>
          </label>
          <label>Containers (optional)
            <input v-model="l.container_count" type="number" step="1" min="0" />
            <small class="inv-err">{{ le(i, 'container_count') }}</small>
          </label>
        </div>
      </div>

      <button type="button" class="inv-btn" @click="addLine">+ Add line</button>
      <label>Notes <textarea v-model="form.notes" rows="2" /></label>

      <div class="inv-buttons">
        <button type="button" class="inv-btn" @click="showForm = false">Cancel</button>
        <button class="inv-btn primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save draft' }}</button>
      </div>
    </form>
  </Modal>

  <!-- View order -->
  <Modal v-if="viewing" :title="'Purchase order ' + (viewing.reference || '')" width="760px" @close="viewing = null">
    <p class="sub">
      {{ supplierName(viewing.supplier_id) }} · {{ viewing.order_date }} ·
      <StatusBadge :tone="statusTone(viewing.status)">{{ prettyStatus(viewing.status) }}</StatusBadge>
    </p>
    <div class="inv-table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th>Product</th><th class="num">Ordered</th><th class="num">Received</th>
            <th class="num">Cancelled</th><th class="num">Outstanding</th><th v-if="canSeeCost" class="num">Agreed cost</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in viewing.lines" :key="l.id">
            <td>{{ productName(l.product_id) }}</td>
            <td class="num">{{ l.ordered_qty }}</td>
            <td class="num">{{ l.received_qty }}</td>
            <td class="num">{{ l.cancelled_qty }}</td>
            <td class="num">{{ l.outstanding_qty }}</td>
            <td v-if="canSeeCost" class="num">{{ money(l.agreed_unit_cost) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="inv-buttons"><button class="inv-btn" @click="viewing = null">Close</button></div>
  </Modal>
</template>