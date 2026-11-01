<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api } from '../api/client'
import '../theme.css'
import { unwrapList, errOf, today, canSeeCost, prettyStatus, money, apiMessage } from '../api/lookups'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import Modal from '../components/Modal.vue'

// Temporary until the gateway token: OM = owner, SC = stock clerk
const isOwner = (import.meta.env.VITE_ROLE || 'OM') === 'OM'

const QUALITY = ['SALEABLE', 'QUARANTINED', 'SPOILED']
const RIPENESS = ['UNRIPE', 'READY', 'OVERRIPE']

const batches = ref([])
const meta = reactive({ page: 1, last: 1, total: 0 })
const loading = ref(false)
const message = ref('')
const error = ref('')
const filter = ref('ALL')
const search = ref('')

const detail = ref(null) // { batch, assessments }
const review = ref(null) // current sell-by review or null
const mode = ref('') // '' | 'assess' | 'review'
const saving = ref(false)
const errors = ref({})

const assessForm = reactive({ quality_state: '', ripeness: '', sell_by_date: '', use_by_date: '', reason: '', notes: '' })
const reviewForm = reactive({ decision: 'SALE_APPROVED', valid_until: '', reason: '' })

const tomorrow = () => new Date(Date.now() + 86400000).toLocaleDateString('en-CA', { timeZone: 'Asia/Colombo' })
const dateOf = (v) => (v ? new Date(v).toLocaleDateString('en-CA', { timeZone: 'Asia/Colombo' }) : '-')
const fmtTime = (v) => (v ? new Date(v).toLocaleString() : '-')
const unitLabel = (u) => (u === 'EACH' ? 'item' : 'kg')
const decisionLabel = (d) => prettyStatus(String(d || '').replace(/_/g, ' '))

const isPastSellBy = (b) => !!b.sell_by_date && b.sell_by_date < today()
const isPastUseBy = (b) => !!b.use_by_date && b.use_by_date < today()
const isApproved = (b) => !!b.sell_by_approved_until && b.sell_by_approved_until >= today()
const needsReview = (b) => isPastSellBy(b) && !isPastUseBy(b) && !isApproved(b)

const stateTone = (s) => (s === 'SALEABLE' ? 'green' : s === 'QUARANTINED' ? 'orange' : 'red')

const counts = computed(() => ({
  review: batches.value.filter(needsReview).length,
  quarantined: batches.value.filter((b) => b.quality_state === 'QUARANTINED').length,
}))

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return batches.value.filter((b) => {
    if (filter.value === 'REVIEW' && !needsReview(b)) return false
    if (filter.value === 'QUARANTINED' && b.quality_state !== 'QUARANTINED') return false
    if (filter.value === 'SALEABLE' && b.quality_state !== 'SALEABLE') return false
    if (!q) return true
    return [b.product?.name, b.product?.sku, b.id, b.source].filter(Boolean).join(' ').toLowerCase().includes(q)
  })
})

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const r = await api('/batches', { params: { page } })
    if (r.ok) {
      const l = unwrapList(r)
      batches.value = l.items
      Object.assign(meta, { page: l.page, last: l.last, total: l.total })
    } else {
      error.value = apiMessage(r, 'Could not load batches')
    }
  } catch (e) {
    error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    loading.value = false
  }
}

async function loadDetail(id) {
  const [r, rv] = await Promise.all([api('/batches/' + id), api('/batches/' + id + '/sell-by-review')])
  if (r.ok) {
    detail.value = r.data.data
    review.value = rv.ok ? rv.data.data : null
  } else {
    error.value = apiMessage(r, 'Could not open the batch')
  }
}

async function openDetail(b) {
  mode.value = ''
  errors.value = {}
  message.value = ''
  await loadDetail(b.id)
}

function closeDetail() {
  detail.value = null
  mode.value = ''
}

function startAssess() {
  const b = detail.value.batch
  Object.assign(assessForm, {
    quality_state: b.quality_state,
    ripeness: b.ripeness,
    sell_by_date: b.sell_by_date || '',
    use_by_date: b.use_by_date || '',
    reason: '',
    notes: '',
  })
  errors.value = {}
  mode.value = 'assess'
}

async function saveAssess() {
  const b = detail.value.batch
  const body = {}
  if (assessForm.quality_state !== b.quality_state) body.quality_state = assessForm.quality_state
  if (assessForm.ripeness !== b.ripeness) body.ripeness = assessForm.ripeness
  if ((assessForm.sell_by_date || '') !== (b.sell_by_date || '')) body.sell_by_date = assessForm.sell_by_date || null
  if ((assessForm.use_by_date || '') !== (b.use_by_date || '')) body.use_by_date = assessForm.use_by_date || null
  if (assessForm.reason) body.reason = assessForm.reason
  if (assessForm.notes) body.notes = assessForm.notes

  if (!['quality_state', 'ripeness', 'sell_by_date', 'use_by_date'].some((k) => k in body)) {
    errors.value = { changes: ['Nothing to change.'] }
    return
  }

  saving.value = true
  errors.value = {}
  const r = await api('/batches/' + b.id + '/assessments', { method: 'POST', body })
  saving.value = false

  if (r.status === 201) {
    mode.value = ''
    message.value = 'Batch updated.'
    await loadDetail(b.id)
    await load(meta.page)
  } else if (r.status === 422) {
    errors.value = r.data.errors || {}
  } else {
    errors.value = { general: [apiMessage(r, 'Could not update the batch')] }
  }
}

function startReview() {
  Object.assign(reviewForm, { decision: 'SALE_APPROVED', valid_until: tomorrow(), reason: '' })
  errors.value = {}
  mode.value = 'review'
}

async function saveReview() {
  const b = detail.value.batch
  const body = { decision: reviewForm.decision, reason: reviewForm.reason }
  if (reviewForm.decision === 'SALE_APPROVED') body.valid_until = reviewForm.valid_until

  saving.value = true
  errors.value = {}
  const r = await api('/batches/' + b.id + '/sell-by-review', { method: 'POST', body })
  saving.value = false

  if (r.status === 201) {
    mode.value = ''
    message.value = 'Sell-by review saved.'
    await loadDetail(b.id)
    await load(meta.page)
  } else if (r.status === 422) {
    errors.value = r.data.errors || {}
  } else {
    errors.value = { general: [apiMessage(r, 'Could not save the review')] }
  }
}

function describe(a) {
  const after = a.after_values || {}
  const before = a.before_values || {}
  return Object.keys(after).map((k) => k.replace(/_/g, ' ') + ': ' + (before[k] ?? '-') + ' → ' + (after[k] ?? '-'))
}

onMounted(() => load())
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Batches">
      <button class="inv-btn" :disabled="loading" @click="load(meta.page)">{{ loading ? 'Refreshing...' : 'Refresh' }}</button>
    </PageHeader>

    <p v-if="message" class="inv-alert-ok">{{ message }}</p>
    <p v-if="error" class="inv-alert-err">{{ error }}</p>

    <div class="inv-grid-4">
      <StatCard label="Batches" :value="meta.total" hint="All received lots" icon="🏷️" tone="green" />
      <StatCard label="Needs sell-by review" :value="counts.review" hint="Past sell-by, not approved (this page)" icon="⏰" tone="orange" />
      <StatCard label="Quarantined" :value="counts.quarantined" hint="Blocked from sale (this page)" icon="🚫" tone="red" />
    </div>

    <div class="inv-card">
      <div class="toolbar">
        <input v-model="search" class="search" placeholder="Search product, SKU, batch id..." />
        <div class="inv-chips">
          <button class="inv-chip" :class="{ on: filter === 'ALL' }" @click="filter = 'ALL'">All</button>
          <button class="inv-chip" :class="{ on: filter === 'REVIEW' }" @click="filter = 'REVIEW'">Needs review <b>{{ counts.review }}</b></button>
          <button class="inv-chip" :class="{ on: filter === 'QUARANTINED' }" @click="filter = 'QUARANTINED'">Quarantined <b>{{ counts.quarantined }}</b></button>
          <button class="inv-chip" :class="{ on: filter === 'SALEABLE' }" @click="filter = 'SALEABLE'">Saleable</button>
        </div>
      </div>

      <p v-if="loading" class="inv-empty">Loading...</p>

      <div v-else class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th>Product</th><th>Batch</th><th>Received</th><th class="num">Received qty</th>
              <th>Ripeness</th><th>Sell-by / Use-by</th><th>Quality</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in visible" :key="b.id">
              <td>{{ b.product?.name }}<small>{{ b.product?.sku }}</small></td>
              <td>{{ prettyStatus(b.source) }}<small>{{ String(b.id).slice(0, 8) }}</small></td>
              <td>{{ dateOf(b.received_at) }}</td>
              <td class="num">{{ b.accepted_qty }} {{ unitLabel(b.product?.base_unit) }}</td>
              <td>{{ prettyStatus(b.ripeness) }}</td>
              <td>
                <span v-if="b.sell_by_date">Sell-by {{ b.sell_by_date }}</span>
                <small v-if="b.use_by_date">Use-by {{ b.use_by_date }}</small>
                <span v-if="!b.sell_by_date && !b.use_by_date">-</span>
                <StatusBadge v-if="isPastUseBy(b)" tone="red">Past use-by</StatusBadge>
                <StatusBadge v-else-if="isApproved(b)" tone="blue">Approved until {{ b.sell_by_approved_until }}</StatusBadge>
                <StatusBadge v-else-if="isPastSellBy(b)" tone="orange">Past sell-by</StatusBadge>
              </td>
              <td><StatusBadge :tone="stateTone(b.quality_state)">{{ prettyStatus(b.quality_state) }}</StatusBadge></td>
              <td class="inv-actions"><button class="inv-btn sm" @click="openDetail(b)">Details</button></td>
            </tr>
            <tr v-if="!visible.length"><td colspan="8" class="inv-empty">No batches found.</td></tr>
          </tbody>
        </table>
      </div>

      <footer class="inv-pager" v-if="meta.last > 1">
        <button class="inv-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Previous</button>
        <span>Page {{ meta.page }} of {{ meta.last }} ({{ meta.total }} batches)</span>
        <button class="inv-btn" :disabled="meta.page >= meta.last" @click="load(meta.page + 1)">Next</button>
      </footer>
    </div>

    <!-- Batch details -->
    <Modal v-if="detail" :title="detail.batch.product?.name + ' · batch ' + String(detail.batch.id).slice(0, 8)" width="760px" @close="closeDetail">
      <p v-if="message" class="inv-alert-ok">{{ message }}</p>

      <dl class="dl">
        <div><dt>Source</dt><dd>{{ prettyStatus(detail.batch.source) }}</dd></div>
        <div><dt>Received</dt><dd>{{ dateOf(detail.batch.received_at) }}</dd></div>
        <div><dt>Received qty</dt><dd>{{ detail.batch.accepted_qty }} {{ unitLabel(detail.batch.product?.base_unit) }}</dd></div>
        <div><dt>Ripeness</dt><dd>{{ prettyStatus(detail.batch.ripeness) }}</dd></div>
        <div><dt>Quality</dt><dd><StatusBadge :tone="stateTone(detail.batch.quality_state)">{{ prettyStatus(detail.batch.quality_state) }}</StatusBadge></dd></div>
        <div><dt>Sell-by</dt><dd>{{ detail.batch.sell_by_date || '-' }}</dd></div>
        <div><dt>Use-by</dt><dd>{{ detail.batch.use_by_date || '-' }}</dd></div>
        <div><dt>Sale approved until</dt><dd>{{ detail.batch.sell_by_approved_until || '-' }}</dd></div>
        <div v-if="canSeeCost"><dt>Unit cost</dt><dd>{{ money(detail.batch.unit_cost) }}</dd></div>
        <div v-if="detail.batch.inspection_notes"><dt>Notes</dt><dd>{{ detail.batch.inspection_notes }}</dd></div>
      </dl>

      <div v-if="review" class="review-box">
        <strong>Sell-by review:</strong>
        <StatusBadge :tone="review.decision === 'SALE_APPROVED' ? 'blue' : 'red'">{{ decisionLabel(review.decision) }}</StatusBadge>
        <span v-if="review.valid_until"> until {{ review.valid_until }}</span>
        <small>{{ review.reason }} · {{ fmtTime(review.decided_at) }}</small>
      </div>

      <div class="inv-buttons" style="justify-content: flex-start; margin: 14px 0">
        <button class="inv-btn primary" @click="startAssess">Assess batch</button>
        <button
          v-if="isOwner && isPastSellBy(detail.batch) && !isPastUseBy(detail.batch)"
          class="inv-btn"
          @click="startReview"
        >Sell-by review</button>
      </div>

      <h3 style="margin: 6px 0 8px">Assessment history</h3>
      <div class="inv-table-wrap">
        <table class="inv-table">
          <thead><tr><th>When</th><th>Change</th><th>Reason</th></tr></thead>
          <tbody>
            <tr v-for="(a, i) in detail.assessments" :key="a.id || i">
              <td>{{ fmtTime(a.created_at) }}</td>
              <td><div v-for="line in describe(a)" :key="line">{{ line }}</div></td>
              <td>{{ a.reason || '-' }}<small v-if="a.notes">{{ a.notes }}</small></td>
            </tr>
            <tr v-if="!detail.assessments.length"><td colspan="3" class="inv-empty">No assessments yet.</td></tr>
          </tbody>
        </table>
      </div>
    </Modal>

    <!-- Assess form -->
    <Modal v-if="detail && mode === 'assess'" title="Assess batch" width="520px" @close="mode = ''">
      <form class="inv-form" @submit.prevent="saveAssess">
        <p v-if="errors.general" class="inv-alert-err">{{ errors.general[0] }}</p>
        <p v-if="errors.changes" class="inv-alert-err">{{ errors.changes[0] }}</p>

        <div class="inv-row">
          <label>Quality
            <select v-model="assessForm.quality_state">
              <option v-for="q in QUALITY" :key="q" :value="q">{{ prettyStatus(q) }}</option>
            </select>
            <small class="inv-err">{{ errOf(errors, 'quality_state') }}</small>
          </label>
          <label>Ripeness
            <select v-model="assessForm.ripeness">
              <option v-for="r in RIPENESS" :key="r" :value="r">{{ prettyStatus(r) }}</option>
            </select>
            <small class="inv-err">{{ errOf(errors, 'ripeness') }}</small>
          </label>
        </div>

        <div class="inv-row">
          <label>Sell-by date
            <input v-model="assessForm.sell_by_date" type="date" :disabled="isPastSellBy(detail.batch)" />
            <small v-if="isPastSellBy(detail.batch)" class="hint">Past sell-by: use the owner review.</small>
            <small class="inv-err">{{ errOf(errors, 'sell_by_date') }}</small>
          </label>
          <label>Use-by date
            <input v-model="assessForm.use_by_date" type="date" :disabled="isPastUseBy(detail.batch)" />
            <small class="inv-err">{{ errOf(errors, 'use_by_date') }}</small>
          </label>
        </div>

        <label>Reason (required for quality or date changes)
          <input v-model="assessForm.reason" />
          <small v-if="detail.batch.quality_state === 'QUARANTINED'" class="hint">Only the owner can release a batch from quarantine.</small>
          <small class="inv-err">{{ errOf(errors, 'reason') }}</small>
        </label>
        <label>Notes (optional) <textarea v-model="assessForm.notes" rows="2" /></label>

        <div class="inv-buttons">
          <button type="button" class="inv-btn" @click="mode = ''">Cancel</button>
          <button class="inv-btn primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Sell-by review form -->
    <Modal v-if="detail && mode === 'review'" title="Sell-by review (owner)" width="480px" @close="mode = ''">
      <form class="inv-form" @submit.prevent="saveReview">
        <p v-if="errors.general" class="inv-alert-err">{{ errors.general[0] }}</p>
        <p v-if="errors.batch_id" class="inv-alert-err">{{ errors.batch_id[0] }}</p>

        <label>Decision
          <select v-model="reviewForm.decision">
            <option value="SALE_APPROVED">Sale approved (checked, firm and fresh)</option>
            <option value="NOT_FOR_SALE">Not for sale</option>
          </select>
          <small class="inv-err">{{ errOf(errors, 'decision') }}</small>
        </label>
        <label v-if="reviewForm.decision === 'SALE_APPROVED'">Valid until
          <input v-model="reviewForm.valid_until" type="date" />
          <small class="inv-err">{{ errOf(errors, 'valid_until') }}</small>
        </label>
        <label>Reason (required)
          <input v-model="reviewForm.reason" />
          <small class="inv-err">{{ errOf(errors, 'reason') }}</small>
        </label>
        <p class="hint">The original sell-by date is not changed.</p>

        <div class="inv-buttons">
          <button type="button" class="inv-btn" @click="mode = ''">Cancel</button>
          <button class="inv-btn primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save review' }}</button>
        </div>
      </form>
    </Modal>
  </section>
</template>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.search { flex: 1; min-width: 220px; max-width: 340px; padding: 9px 14px; border: 1px solid var(--inv-border); border-radius: 10px; font-size: 13.5px; background: var(--inv-bg); }
.search:focus { outline: 2px solid var(--inv-green-100); border-color: var(--inv-green-700); }
.inv-chip b { margin-left: 4px; color: var(--inv-muted); }
.inv-chip.on b { color: #cfe8da; }
.dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 18px; margin: 0 0 12px; }
.dl dt { font-size: 12px; color: var(--inv-muted); }
.dl dd { margin: 2px 0 0; font-size: 14px; font-weight: 600; }
.review-box { background: var(--inv-blue-bg); border-radius: 12px; padding: 10px 14px; font-size: 13.5px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.review-box small { width: 100%; color: var(--inv-muted); }
.hint { color: var(--inv-muted); font-weight: 400; font-size: 12px; }
</style>