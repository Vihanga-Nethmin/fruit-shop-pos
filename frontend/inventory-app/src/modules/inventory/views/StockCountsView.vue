<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api } from '../api/client'
import '../theme.css'
import { loadLookups, unwrapList, errOf, canSeeCost, prettyStatus, money, apiMessage } from '../api/lookups'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import Modal from '../components/Modal.vue'

// Temporary until the gateway token: OM = owner, SC = stock clerk
const isOwner = (import.meta.env.VITE_ROLE || 'OM') === 'OM'

const STATUSES = [
  { value: 'ALL', label: 'All' },
  { value: 'OPEN', label: 'Counting' },
  { value: 'SUBMITTED', label: 'Awaiting approval' },
  { value: 'POSTED', label: 'Approved' },
  { value: 'CANCELLED', label: 'Cancelled' },
]
const LABEL = { OPEN: 'Counting', SUBMITTED: 'Awaiting approval', POSTED: 'Approved', CANCELLED: 'Cancelled' }
const TONE = { OPEN: 'blue', SUBMITTED: 'orange', POSTED: 'green', CANCELLED: 'gray' }
const label = (s) => LABEL[s] || prettyStatus(s)
const tone = (s) => TONE[s] || 'gray'

const lookups = ref({ suppliers: [], locations: [], products: [] })
const rows = ref([])
const meta = reactive({ page: 1, last: 1, total: 0 })
const stats = ref({ OPEN: 0, SUBMITTED: 0, POSTED: 0 })
const loading = ref(false)
const message = ref('')
const error = ref('')
const statusFilter = ref('ALL')

// New count
const showForm = ref(false)
const saving = ref(false)
const errors = ref({})
const form = reactive({ location_id: '', product_id: '', notes: '' })

// Count detail
const detail = ref(null)
const counted = reactive({})
const working = ref(false)
const detailErrors = ref([])
const reviewNotes = ref('')

const activeLocations = computed(() => lookups.value.locations.filter((l) => l.is_active !== false))
const activeProducts = computed(() => lookups.value.products.filter((p) => p.is_active !== false))
const locationCode = (id) => lookups.value.locations.find((l) => l.id === id)?.code
const productName = (id) => lookups.value.products.find((p) => p.id === id)?.name
const scopeOf = (c) => [locationCode(c.location_id), productName(c.product_id)].filter(Boolean).join(' · ') || 'Whole shop'
const fmtDate = (v) => (v ? new Date(v).toLocaleString() : '-')

const editable = computed(() => detail.value?.status === 'OPEN')
const unitOf = (l) => (l.product?.base_unit === 'EACH' ? 'item' : 'kg')
const stepOf = (l) => (l.product?.base_unit === 'EACH' ? '1' : '0.001')

const str = (v) => (v === '' || v === null ? undefined : v)
const flat = (errs) => Object.values(errs || {}).flat()
const signed = (v) => (Number(v) > 0 ? '+' + v : String(v))
const varClass = (v) => (Number(v) < 0 ? 'neg' : Number(v) > 0 ? 'pos' : '')

function preview(l) {
  const v = counted[l.id]
  if (v === '' || v === null || v === undefined) return null
  const d = Number(v) - Number(l.snapshot_qty)
  return l.product?.base_unit === 'EACH' ? d.toFixed(0) : d.toFixed(3)
}

const totals = computed(() => {
  const lines = detail.value?.lines || []
  return {
    qty: lines.reduce((s, l) => s + Number(l.variance || 0), 0),
    value: lines.reduce((s, l) => s + Number(l.variance_value || 0), 0),
    counted: lines.filter((l) => l.counted_qty !== null).length,
    all: lines.length,
  }
})

async function loadStats() {
  const res = await Promise.all(
    ['OPEN', 'SUBMITTED', 'POSTED'].map((s) => api('/stock-counts', { params: { status: s } })),
  )
  stats.value = {
    OPEN: res[0].ok ? unwrapList(res[0]).total : 0,
    SUBMITTED: res[1].ok ? unwrapList(res[1]).total : 0,
    POSTED: res[2].ok ? unwrapList(res[2]).total : 0,
  }
}

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = { page, status: statusFilter.value === 'ALL' ? undefined : statusFilter.value }
    const r = await api('/stock-counts', { params })
    if (r.ok) {
      const l = unwrapList(r)
      rows.value = l.items
      Object.assign(meta, { page: l.page, last: l.last, total: l.total })
    } else {
      error.value = apiMessage(r, 'Could not load stock counts')
    }
  } catch (e) {
    error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    loading.value = false
  }
}

const refreshAll = () => Promise.all([load(meta.page), loadStats()])

function setStatus(v) {
  statusFilter.value = v
  load(1)
}

function setDetail(c) {
  detail.value = c
  Object.keys(counted).forEach((k) => delete counted[k])
  c.lines.forEach((l) => {
    counted[l.id] = l.counted_qty ?? ''
  })
}

async function openCount(row) {
  detailErrors.value = []
  reviewNotes.value = ''
  message.value = ''
  const r = await api('/stock-counts/' + row.id)
  if (r.ok) setDetail(r.data.data)
  else error.value = apiMessage(r, 'Could not open the count')
}

function closeDetail() {
  detail.value = null
}

function openForm() {
  Object.assign(form, { location_id: '', product_id: '', notes: '' })
  errors.value = {}
  message.value = ''
  showForm.value = true
}

async function createCount() {
  errors.value = {}
  if (!form.location_id && !form.product_id) {
    errors.value = { scope: ['Give a location and/or a product to count.'] }
    return
  }
  saving.value = true
  const r = await api('/stock-counts', {
    method: 'POST',
    body: { location_id: str(form.location_id), product_id: str(form.product_id), notes: str(form.notes) },
  })
  saving.value = false

  if (r.status === 201) {
    showForm.value = false
    message.value = 'Count started. These positions are locked until the count is approved or cancelled.'
    detailErrors.value = []
    reviewNotes.value = ''
    setDetail(r.data.data)
    await refreshAll()
  } else if (r.status === 422) {
    errors.value = r.data.errors || {}
  } else {
    errors.value = { general: [apiMessage(r, 'Could not start the count')] }
  }
}

function fillExpected() {
  detail.value.lines.forEach((l) => {
    counted[l.id] = l.snapshot_qty
  })
}

async function saveEntries() {
  detailErrors.value = []
  const lines = detail.value.lines
    .filter((l) => counted[l.id] !== '' && counted[l.id] !== null && counted[l.id] !== undefined)
    .map((l) => ({ line_id: l.id, counted_qty: Number(counted[l.id]) }))

  if (!lines.length) {
    detailErrors.value = ['Enter at least one counted quantity.']
    return false
  }
  const r = await api('/stock-counts/' + detail.value.id + '/entries', { method: 'POST', body: { lines } })
  if (r.ok) {
    setDetail(r.data.data)
    return true
  }
  detailErrors.value = r.status === 422 ? flat(r.data.errors) : [apiMessage(r, 'Could not save the counts')]
  return false
}

async function onSave() {
  working.value = true
  message.value = ''
  if (await saveEntries()) message.value = 'Counts saved.'
  working.value = false
}

async function onSubmit() {
  working.value = true
  message.value = ''
  if (await saveEntries()) {
    const r = await api('/stock-counts/' + detail.value.id + '/submit', { method: 'POST' })
    if (r.ok) {
      setDetail(r.data.data)
      message.value = 'Count submitted. The owner can now approve it.'
      await refreshAll()
    } else {
      detailErrors.value = r.status === 422 ? flat(r.data.errors) : [apiMessage(r, 'Could not submit the count')]
    }
  }
  working.value = false
}

async function onApprove() {
  if (!window.confirm('Approve this count? Variances are posted as stock adjustments and cannot be undone.')) return
  working.value = true
  detailErrors.value = []
  message.value = ''
  const r = await api('/stock-counts/' + detail.value.id + '/approve', {
    method: 'POST',
    body: { review_notes: str(reviewNotes.value) },
  })
  if (r.ok) {
    setDetail(r.data.data)
    message.value = 'Count approved. Stock adjusted and positions unlocked.'
    await refreshAll()
  } else {
    detailErrors.value = r.status === 422 ? flat(r.data.errors) : [apiMessage(r, 'Could not approve the count')]
  }
  working.value = false
}

async function onCancel() {
  if (!window.confirm('Cancel this count? Positions are unlocked and nothing is adjusted.')) return
  working.value = true
  detailErrors.value = []
  const r = await api('/stock-counts/' + detail.value.id + '/cancel', { method: 'POST' })
  if (r.ok) {
    setDetail(r.data.data)
    message.value = 'Count cancelled.'
    await refreshAll()
  } else {
    detailErrors.value = [apiMessage(r, 'Could not cancel the count')]
  }
  working.value = false
}

onMounted(async () => {
  try {
    lookups.value = await loadLookups()
  } catch (e) {
    error.value = 'Could not load products and locations.'
  }
  await Promise.all([load(), loadStats()])
})
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Stock counts">
      <button class="inv-btn primary" @click="openForm">+ New count</button>
    </PageHeader>

    <p v-if="message && !detail" class="inv-alert-ok">{{ message }}</p>
    <p v-if="error" class="inv-alert-err">{{ error }}</p>

    <div class="inv-grid-4">
      <StatCard label="Counting" :value="stats.OPEN" hint="Positions locked" icon="🧮" tone="blue" />
      <StatCard label="Awaiting approval" :value="stats.SUBMITTED" hint="Owner decision needed" icon="⏳" tone="orange" />
      <StatCard label="Approved" :value="stats.POSTED" hint="Adjustments posted" icon="✅" tone="green" />
    </div>

    <div class="inv-card">
      <div class="inv-chips" style="margin-bottom: 14px">
        <button v-for="s in STATUSES" :key="s.value" class="inv-chip" :class="{ on: statusFilter === s.value }" @click="setStatus(s.value)">
          {{ s.label }}
        </button>
      </div>

      <p v-if="loading" class="inv-empty">Loading...</p>

      <div v-else class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr><th>Started</th><th>Scope</th><th>Status</th><th>Submitted</th><th>Notes</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="c in rows" :key="c.id">
              <td>{{ fmtDate(c.created_at) }}</td>
              <td>{{ scopeOf(c) }}</td>
              <td><StatusBadge :tone="tone(c.status)">{{ label(c.status) }}</StatusBadge></td>
              <td>{{ fmtDate(c.submitted_at) }}</td>
              <td>{{ c.review_notes || c.notes || '-' }}</td>
              <td class="inv-actions"><button class="inv-btn sm" @click="openCount(c)">Open</button></td>
            </tr>
            <tr v-if="!rows.length"><td colspan="6" class="inv-empty">No stock counts yet.</td></tr>
          </tbody>
        </table>
      </div>

      <footer class="inv-pager" v-if="meta.last > 1">
        <button class="inv-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Previous</button>
        <span>Page {{ meta.page }} of {{ meta.last }} ({{ meta.total }} counts)</span>
        <button class="inv-btn" :disabled="meta.page >= meta.last" @click="load(meta.page + 1)">Next</button>
      </footer>
    </div>

    <!-- New count -->
    <Modal v-if="showForm" title="New stock count" width="480px" @close="showForm = false">
      <form class="inv-form" @submit.prevent="createCount">
        <p v-if="errors.general" class="inv-alert-err">{{ errors.general[0] }}</p>
        <p v-if="errors.scope" class="inv-alert-err">{{ errors.scope[0] }}</p>

        <label>Location
          <select v-model="form.location_id">
            <option value="">Any location</option>
            <option v-for="l in activeLocations" :key="l.id" :value="l.id">{{ l.code }} - {{ l.name }}</option>
          </select>
          <small class="inv-err">{{ errOf(errors, 'location_id') }}</small>
        </label>
        <label>Product
          <select v-model="form.product_id">
            <option value="">Any product</option>
            <option v-for="p in activeProducts" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
          </select>
          <small class="inv-err">{{ errOf(errors, 'product_id') }}</small>
        </label>
        <label>Notes (optional) <textarea v-model="form.notes" rows="2" /></label>
        <p class="hint">Choose a location, a product, or both. Counted positions are locked: sales reservations, transfers, wastage and returns on them wait until the count ends.</p>

        <div class="inv-buttons">
          <button type="button" class="inv-btn" @click="showForm = false">Cancel</button>
          <button class="inv-btn primary" :disabled="saving">{{ saving ? 'Starting...' : 'Start count' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Count detail -->
    <Modal v-if="detail" :title="'Stock count · ' + scopeOf(detail)" width="920px" @close="closeDetail">
      <p class="sub">
        <StatusBadge :tone="tone(detail.status)">{{ label(detail.status) }}</StatusBadge>
        · Started {{ fmtDate(detail.created_at) }}
        <span v-if="detail.submitted_at"> · Submitted {{ fmtDate(detail.submitted_at) }}</span>
        <span v-if="detail.decided_at"> · Decided {{ fmtDate(detail.decided_at) }}</span>
      </p>

      <p v-if="message" class="inv-alert-ok">{{ message }}</p>
      <div v-if="detailErrors.length" class="inv-alert-err">
        <div v-for="(e, i) in detailErrors" :key="i">{{ e }}</div>
      </div>

      <div class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th>Product</th><th>Batch</th><th>Location</th>
              <th class="num">Expected</th><th class="num">Counted</th><th class="num">Variance</th>
              <th v-if="canSeeCost" class="num">Variance value</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in detail.lines" :key="l.id">
              <td>{{ l.product?.name }}<small>{{ l.product?.sku }}</small></td>
              <td>
                {{ prettyStatus(l.batch?.source) }} {{ String(l.batch_id).slice(0, 8) }}
                <small v-if="l.batch?.sell_by_date">Sell-by {{ l.batch.sell_by_date }}</small>
              </td>
              <td>{{ l.location?.code }}</td>
              <td class="num">{{ l.snapshot_qty }} {{ unitOf(l) }}</td>
              <td class="num">
                <input v-if="editable" v-model="counted[l.id]" class="cell" type="number" :step="stepOf(l)" min="0" />
                <template v-else>{{ l.counted_qty ?? '-' }}</template>
              </td>
              <td class="num">
                <span v-if="l.variance !== null" :class="varClass(l.variance)">{{ signed(l.variance) }}</span>
                <span v-else-if="editable && preview(l) !== null" class="muted" :class="varClass(preview(l))">{{ signed(preview(l)) }}</span>
                <span v-else>-</span>
              </td>
              <td v-if="canSeeCost" class="num">
                <span v-if="l.variance_value !== null" :class="varClass(l.variance_value)">{{ money(l.variance_value) }}</span>
                <span v-else>-</span>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="detail.status !== 'OPEN'">
            <tr>
              <td colspan="5" class="num"><strong>Net variance</strong></td>
              <td class="num" :class="varClass(totals.qty)"><strong>{{ signed(totals.qty.toFixed(3)) }}</strong></td>
              <td v-if="canSeeCost" class="num" :class="varClass(totals.value)"><strong>{{ money(totals.value) }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <p v-if="editable" class="hint" style="margin-top: 8px">
        Counted {{ totals.counted }} of {{ totals.all }} lines. Every line needs a counted quantity before you can submit.
      </p>

      <label v-if="isOwner && detail.status === 'SUBMITTED'" class="inv-form" style="margin-top: 12px">
        <span style="font-size: 13px; font-weight: 600">Review notes (optional)</span>
        <input v-model="reviewNotes" />
      </label>
      <p v-if="detail.review_notes" class="hint">Review notes: {{ detail.review_notes }}</p>

      <div class="inv-buttons" style="margin-top: 14px">
        <button class="inv-btn" @click="closeDetail">Close</button>
        <template v-if="detail.status === 'OPEN'">
          <button class="inv-btn" :disabled="working" @click="fillExpected">Fill with expected</button>
          <button class="inv-btn" :disabled="working" @click="onCancel">Cancel count</button>
          <button class="inv-btn" :disabled="working" @click="onSave">Save counts</button>
          <button class="inv-btn primary" :disabled="working" @click="onSubmit">Submit for approval</button>
        </template>
        <template v-else-if="detail.status === 'SUBMITTED'">
          <button class="inv-btn" :disabled="working" @click="onCancel">Cancel count</button>
          <button v-if="isOwner" class="inv-btn primary" :disabled="working" @click="onApprove">Approve and adjust stock</button>
        </template>
      </div>
    </Modal>
  </section>
</template>

<style scoped>
.hint { color: var(--inv-muted); font-weight: 400; font-size: 12px; }
.cell { width: 110px; padding: 6px 8px; border: 1px solid var(--inv-border); border-radius: 8px; text-align: right; font-size: 13.5px; }
.cell:focus { outline: 2px solid var(--inv-green-100); border-color: var(--inv-green-700); }
.neg { color: var(--inv-red); font-weight: 600; }
.pos { color: var(--inv-green-700); font-weight: 600; }
.muted { opacity: 0.7; font-style: italic; }
tfoot td { border-top: 2px solid var(--inv-border); }
</style>