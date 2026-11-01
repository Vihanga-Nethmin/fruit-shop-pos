<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api } from '../api/client'
import { unwrapList, errOf, today, canSeeCost, statusTone, prettyStatus, money, apiMessage } from '../api/lookups'
import { loadStockLines } from '../api/positions'
import StatusBadge from './StatusBadge.vue'
import Modal from './Modal.vue'

const props = defineProps({
  kind: { type: String, required: true }, // 'wastage' | 'supplier'
  lookups: { type: Object, required: true },
})
const emit = defineEmits(['changed'])

// Temporary until the gateway token: OM = owner, SC = stock clerk
const isOwner = (import.meta.env.VITE_ROLE || 'OM') === 'OM'
const isWaste = props.kind === 'wastage'
const base = isWaste ? '/wastage-records' : '/supplier-returns'
const REASONS = ['SPOILAGE', 'BRUISING', 'SHRINKAGE', 'THEFT', 'OTHER']

const STATUSES = [
  { value: 'ALL', label: 'All' },
  { value: 'SUBMITTED', label: 'Awaiting approval' },
  { value: 'POSTED', label: 'Posted' },
  { value: 'REJECTED', label: 'Rejected' },
  { value: 'CANCELLED', label: 'Cancelled' },
]

const rows = ref([])
const meta = reactive({ page: 1, last: 1, total: 0 })
const loading = ref(false)
const message = ref('')
const error = ref('')
const statusFilter = ref('ALL')

const showForm = ref(false)
const saving = ref(false)
const errors = ref({})
const lines = ref([])
const loadingLines = ref(false)
const form = reactive({ line_key: '', quantity: '', reason: '', notes: '', return_date: today(), supplier_credit_reference: '' })

const rejecting = ref(null)
const rejectReason = ref('')
const rejectErrors = ref({})

const selectedLine = computed(() => lines.value.find((l) => l.key === form.line_key))
const unit = computed(() => selectedLine.value?.unit || 'kg')
const step = computed(() => (unit.value === 'item' ? '1' : '0.001'))

const productOf = (id) => props.lookups.products.find((p) => p.id === id)
const productName = (id) => productOf(id)?.name || String(id).slice(0, 8)
const unitOf = (r) => (productOf(r.product_id)?.base_unit === 'EACH' ? 'item' : 'kg')
const locationCode = (id) => props.lookups.locations.find((l) => l.id === id)?.code || String(id).slice(0, 8)
const supplierName = (id) => props.lookups.suppliers.find((s) => s.id === id)?.name || '-'
const fmtDate = (v) => (v ? new Date(v).toLocaleDateString('en-CA', { timeZone: 'Asia/Colombo' }) : '-')

const num = (v) => (v === '' || v === null ? undefined : Number(v))
const str = (v) => (v === '' || v === null ? undefined : v)

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = { page, status: statusFilter.value === 'ALL' ? undefined : statusFilter.value }
    const r = await api(base, { params })
    if (r.ok) {
      const l = unwrapList(r)
      rows.value = l.items
      Object.assign(meta, { page: l.page, last: l.last, total: l.total })
    } else {
      error.value = apiMessage(r, 'Could not load the list')
    }
  } catch (e) {
    error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    loading.value = false
  }
}

function setStatus(v) {
  statusFilter.value = v
  load(1)
}

async function openForm() {
  Object.assign(form, {
    line_key: '', quantity: '', reason: isWaste ? 'SPOILAGE' : '', notes: '',
    return_date: today(), supplier_credit_reference: '',
  })
  errors.value = {}
  message.value = ''
  showForm.value = true
  loadingLines.value = true
  lines.value = await loadStockLines({ onlyReceipt: !isWaste })
  loadingLines.value = false
}

async function createRequest() {
  saving.value = true
  errors.value = {}
  const line = selectedLine.value
  const body = {
    batch_id: line?.batch_id,
    location_id: line?.location_id,
    quantity: num(form.quantity),
    reason: str(form.reason),
  }
  if (isWaste) {
    body.notes = str(form.notes)
  } else {
    body.return_date = str(form.return_date)
    body.supplier_credit_reference = str(form.supplier_credit_reference)
  }
  const r = await api(base, { method: 'POST', body })
  saving.value = false

  if (r.status === 201) {
    showForm.value = false
    message.value = 'Request submitted. Stock changes only after the owner approves.'
    await load(1)
    emit('changed')
  } else if (r.status === 422) {
    errors.value = r.data.errors || {}
  } else {
    errors.value = { general: [apiMessage(r, 'Could not save the request')] }
  }
}

async function act(row, action, confirmText, doneText) {
  if (!window.confirm(confirmText)) return
  message.value = ''
  error.value = ''
  const r = await api(base + '/' + row.id + '/' + action, { method: 'POST' })
  if (r.ok) {
    message.value = doneText
    await load(meta.page)
    emit('changed')
  } else {
    error.value = apiMessage(r, 'Action failed')
  }
}

const approve = (row) => act(row, 'approve', 'Approve this request? Stock is reduced and cannot be undone.', 'Approved and posted. Stock reduced.')
const cancel = (row) => act(row, 'cancel', 'Cancel this request?', 'Request cancelled.')

function openReject(row) {
  rejecting.value = row
  rejectReason.value = ''
  rejectErrors.value = {}
}

async function saveReject() {
  const r = await api(base + '/' + rejecting.value.id + '/reject', { method: 'POST', body: { reason: rejectReason.value } })
  if (r.ok) {
    rejecting.value = null
    message.value = 'Request rejected.'
    await load(meta.page)
    emit('changed')
  } else if (r.status === 422) {
    rejectErrors.value = r.data.errors || {}
  } else {
    rejectErrors.value = { general: [apiMessage(r, 'Action failed')] }
  }
}

onMounted(() => load())
</script>

<template>
  <div class="inv-card">
    <div class="inv-toolbar">
      <div>
        <h3>{{ isWaste ? 'Wastage' : 'Supplier returns' }}</h3>
        <p class="sub" style="margin-bottom: 0">
          {{ isWaste ? 'Spoilage and other write-offs. The clerk requests, the owner approves.' : 'Stock sent back to the supplier. The clerk requests, the owner approves.' }}
        </p>
      </div>
      <button class="inv-btn primary" @click="openForm">+ New {{ isWaste ? 'wastage' : 'return' }} request</button>
    </div>

    <p v-if="message" class="inv-alert-ok">{{ message }}</p>
    <p v-if="error" class="inv-alert-err">{{ error }}</p>

    <div class="inv-chips" style="margin-bottom: 14px">
      <button v-for="s in STATUSES" :key="s.value" class="inv-chip" :class="{ on: statusFilter === s.value }" @click="setStatus(s.value)">
        {{ s.label }}
      </button>
    </div>

    <p v-if="loading" class="inv-empty">Loading...</p>

    <div v-else class="inv-table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th>Date</th><th>Product</th><th>Location</th><th>Batch</th><th class="num">Quantity</th>
            <th>Reason</th><th v-if="!isWaste">Supplier</th><th>Status</th>
            <th v-if="canSeeCost" class="num">{{ isWaste ? 'Loss value' : 'Return value' }}</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id">
            <td>{{ fmtDate(r.created_at) }}</td>
            <td>{{ productName(r.product_id) }}</td>
            <td>{{ locationCode(r.location_id) }}</td>
            <td>{{ String(r.batch_id).slice(0, 8) }}</td>
            <td class="num">{{ r.quantity }} {{ unitOf(r) }}</td>
            <td>
              {{ isWaste ? prettyStatus(r.reason) : r.reason }}
              <small v-if="r.notes">{{ r.notes }}</small>
            </td>
            <td v-if="!isWaste">{{ supplierName(r.supplier_id) }}</td>
            <td>
              <StatusBadge :tone="statusTone(r.status)">{{ prettyStatus(r.status) }}</StatusBadge>
              <small v-if="r.decision_notes">{{ r.decision_notes }}</small>
            </td>
            <td v-if="canSeeCost" class="num">{{ money(isWaste ? r.loss_value : r.return_value) }}</td>
            <td class="inv-actions">
              <template v-if="r.status === 'SUBMITTED'">
                <button v-if="isOwner" class="inv-btn sm primary" @click="approve(r)">Approve</button>
                <button v-if="isOwner" class="inv-btn sm" @click="openReject(r)">Reject</button>
                <button class="inv-btn sm" @click="cancel(r)">Cancel</button>
              </template>
            </td>
          </tr>
          <tr v-if="!rows.length"><td :colspan="10" class="inv-empty">Nothing here yet.</td></tr>
        </tbody>
      </table>
    </div>

    <footer class="inv-pager" v-if="meta.last > 1">
      <button class="inv-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Previous</button>
      <span>Page {{ meta.page }} of {{ meta.last }} ({{ meta.total }})</span>
      <button class="inv-btn" :disabled="meta.page >= meta.last" @click="load(meta.page + 1)">Next</button>
    </footer>
  </div>

  <!-- New request -->
  <Modal v-if="showForm" :title="isWaste ? 'New wastage request' : 'New supplier return request'" width="560px" @close="showForm = false">
    <form class="inv-form" @submit.prevent="createRequest">
      <p v-if="errors.general" class="inv-alert-err">{{ errors.general[0] }}</p>

      <label>Batch and location
        <select v-model="form.line_key" :disabled="loadingLines">
          <option value="" disabled>{{ loadingLines ? 'Loading stock...' : 'Select stock' }}</option>
          <option v-for="l in lines" :key="l.key" :value="l.key">{{ l.label }}</option>
        </select>
        <small v-if="!isWaste" class="hint">Only supplier receipt batches can be returned to the supplier.</small>
        <small v-if="!loadingLines && !lines.length" class="hint">No stock available for this request.</small>
        <small class="inv-err">{{ errOf(errors, 'batch_id') || errOf(errors, 'location_id') }}</small>
      </label>

      <label>Quantity ({{ unit }})
        <input v-model="form.quantity" type="number" :step="step" min="0" />
        <small v-if="selectedLine" class="hint">Available: {{ selectedLine.available }} {{ unit }} (reserved stock cannot be used)</small>
        <small class="inv-err">{{ errOf(errors, 'quantity') }}</small>
      </label>

      <template v-if="isWaste">
        <label>Reason
          <select v-model="form.reason">
            <option v-for="r in REASONS" :key="r" :value="r">{{ prettyStatus(r) }}</option>
          </select>
          <small class="inv-err">{{ errOf(errors, 'reason') }}</small>
        </label>
        <label>Notes {{ form.reason === 'OTHER' ? '(required)' : '(optional)' }}
          <textarea v-model="form.notes" rows="2" />
          <small class="inv-err">{{ errOf(errors, 'notes') }}</small>
        </label>
      </template>

      <template v-else>
        <label>Reason
          <input v-model="form.reason" placeholder="e.g. Below agreed grade" />
          <small class="inv-err">{{ errOf(errors, 'reason') }}</small>
        </label>
        <div class="inv-row">
          <label>Return date
            <input v-model="form.return_date" type="date" />
            <small class="inv-err">{{ errOf(errors, 'return_date') }}</small>
          </label>
          <label>Supplier credit reference
            <input v-model="form.supplier_credit_reference" />
            <small class="inv-err">{{ errOf(errors, 'supplier_credit_reference') }}</small>
          </label>
        </div>
      </template>

      <div class="inv-buttons">
        <button type="button" class="inv-btn" @click="showForm = false">Cancel</button>
        <button class="inv-btn primary" :disabled="saving">{{ saving ? 'Saving...' : 'Submit request' }}</button>
      </div>
    </form>
  </Modal>

  <!-- Reject -->
  <Modal v-if="rejecting" title="Reject request" width="440px" @close="rejecting = null">
    <form class="inv-form" @submit.prevent="saveReject">
      <p v-if="rejectErrors.general" class="inv-alert-err">{{ rejectErrors.general[0] }}</p>
      <label>Reason (required)
        <input v-model="rejectReason" />
        <small class="inv-err">{{ errOf(rejectErrors, 'reason') }}</small>
      </label>
      <div class="inv-buttons">
        <button type="button" class="inv-btn" @click="rejecting = null">Cancel</button>
        <button class="inv-btn primary">Reject</button>
      </div>
    </form>
  </Modal>
</template>

<style scoped>
.hint { color: var(--inv-muted); font-weight: 400; font-size: 12px; }
</style>