<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api, download } from '../api/client'
import '../theme.css'
import { unwrapList, apiMessage } from '../api/lookups'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import Modal from '../components/Modal.vue'

// Temporary until the gateway token: OM = owner, SC = stock clerk
const isOwner = (import.meta.env.VITE_ROLE || 'OM') === 'OM'

const METHODS = ['GET', 'POST', 'PATCH', 'PUT', 'DELETE']
const OUTCOMES = [
  { value: '', label: 'All' },
  { value: 'SUCCESS', label: 'Success' },
  { value: 'DENIED', label: 'Denied' },
  { value: 'FAILED', label: 'Failed' },
]
const ENTITIES = [
  'products', 'categories', 'suppliers', 'locations', 'purchase-orders', 'goods-receipts', 'batches',
  'wastage-records', 'supplier-returns', 'stock-counts', 'inventory/opening-balances', 'inventory/transfers',
  'inventory/reservations', 'reports/stock', 'reports/wastage', 'audit-events',
]

const rows = ref([])
const meta = reactive({ page: 1, last: 1, total: 0 })
const stats = ref({ total: 0, denied: 0, failed: 0 })
const loading = ref(false)
const downloading = ref(false)
const error = ref('')
const detail = ref(null)
let seq = 0

const filters = reactive({ from: '', to: '', method: '', outcome: '', entity_type: '', entity_ref: '', actor_id: '' })

const methodTone = (m) => ({ GET: 'gray', POST: 'blue', PATCH: 'orange', PUT: 'orange', DELETE: 'red' }[m] || 'gray')
const outcomeTone = (o) => ({ SUCCESS: 'green', DENIED: 'red', FAILED: 'orange' }[o] || 'gray')
const cap = (s) => String(s || '').toLowerCase().replace(/^\w/, (c) => c.toUpperCase())
const when = (v) => (v ? new Date(v).toLocaleString() : '-')
const action = (e) => String(e.route || e.path || '').replace(/^api\/v1\//, '')
const shortRef = (v) => (v ? String(v).slice(0, 8) : '')
const pretty = (v) => JSON.stringify(v, null, 2)

function baseParams() {
  return { from: filters.from || undefined, to: filters.to || undefined }
}

function listParams(page) {
  return {
    ...baseParams(),
    page,
    method: filters.method || undefined,
    outcome: filters.outcome || undefined,
    entity_type: filters.entity_type.trim() || undefined,
    entity_ref: filters.entity_ref.trim() || undefined,
    actor_id: filters.actor_id.trim() || undefined,
  }
}

const flat = (errs) => Object.values(errs || {}).flat()

function message(r, fallback) {
  if (r.status === 403) return 'The audit log is for the owner only.'
  if (r.status === 422) return flat(r.data?.errors).join(' ') || fallback
  return apiMessage(r, fallback)
}

async function loadStats() {
  const base = baseParams()
  const [all, denied, failed] = await Promise.all([
    api('/audit-events', { params: base }),
    api('/audit-events', { params: { ...base, outcome: 'DENIED' } }),
    api('/audit-events', { params: { ...base, outcome: 'FAILED' } }),
  ])
  stats.value = {
    total: all.ok ? unwrapList(all).total : 0,
    denied: denied.ok ? unwrapList(denied).total : 0,
    failed: failed.ok ? unwrapList(failed).total : 0,
  }
}

async function load(page = 1) {
  if (!isOwner) return
  const mine = ++seq
  loading.value = true
  error.value = ''
  try {
    const r = await api('/audit-events', { params: listParams(page) })
    if (mine !== seq) return
    if (r.ok) {
      const l = unwrapList(r)
      rows.value = l.items
      Object.assign(meta, { page: l.page, last: l.last, total: l.total })
    } else {
      error.value = message(r, 'Could not load the audit log')
    }
  } catch (e) {
    if (mine === seq) error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    if (mine === seq) loading.value = false
  }
}

function apply() {
  load(1)
  loadStats()
}

function setOutcome(v) {
  filters.outcome = v
  load(1)
}

function reset() {
  Object.assign(filters, { from: '', to: '', method: '', outcome: '', entity_type: '', entity_ref: '', actor_id: '' })
  apply()
}

async function downloadCsv() {
  downloading.value = true
  error.value = ''
  try {
    const r = await download('/audit-events', { ...listParams(undefined), format: 'csv' })
    if (!r.ok) {
      error.value = message(r, 'Could not download the file')
      return
    }
    const url = URL.createObjectURL(r.blob)
    const a = document.createElement('a')
    a.href = url
    a.download = r.filename || 'audit-log.csv'
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    downloading.value = false
  }
}

const hasFilters = computed(() => Object.values(filters).some((v) => v))

onMounted(() => {
  load()
  if (isOwner) loadStats()
})
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Audit log">
      <button v-if="isOwner" class="inv-btn" :disabled="loading" @click="apply">{{ loading ? 'Refreshing...' : 'Refresh' }}</button>
      <button v-if="isOwner" class="inv-btn primary" :disabled="downloading || loading" @click="downloadCsv">
        {{ downloading ? 'Preparing...' : 'Download CSV' }}
      </button>
    </PageHeader>

    <p v-if="!isOwner" class="inv-alert-err">The audit log is for the owner only.</p>

    <template v-else>
      <p v-if="error" class="inv-alert-err">{{ error }}</p>

      <div class="inv-grid-4">
        <StatCard label="Events" :value="stats.total" hint="In the selected period" icon="🧾" tone="blue" />
        <StatCard label="Denied" :value="stats.denied" hint="Blocked by role rules" icon="🚫" tone="red" />
        <StatCard label="Failed" :value="stats.failed" hint="Errors while processing" icon="⚠️" tone="orange" />
      </div>

      <div class="inv-card">
        <div class="inv-chips" style="margin-bottom: 14px">
          <button v-for="o in OUTCOMES" :key="o.value" class="inv-chip" :class="{ on: filters.outcome === o.value }" @click="setOutcome(o.value)">
            {{ o.label }}
          </button>
        </div>

        <form class="filters" @submit.prevent="apply">
          <label>From <input v-model="filters.from" type="date" /></label>
          <label>To <input v-model="filters.to" type="date" /></label>
          <label>Method
            <select v-model="filters.method">
              <option value="">Any</option>
              <option v-for="m in METHODS" :key="m" :value="m">{{ m }}</option>
            </select>
          </label>
          <label>Entity
            <input v-model="filters.entity_type" list="entity-list" placeholder="e.g. batches" />
            <datalist id="entity-list"><option v-for="e in ENTITIES" :key="e" :value="e" /></datalist>
          </label>
          <label>Entity id <input v-model="filters.entity_ref" placeholder="record id" /></label>
          <label>Actor id <input v-model="filters.actor_id" placeholder="user id" /></label>
          <div class="btns">
            <button class="inv-btn primary" :disabled="loading">Apply</button>
            <button type="button" class="inv-btn" :disabled="loading || !hasFilters" @click="reset">Reset</button>
          </div>
        </form>
        <p class="hint">Without dates, the last 30 days are shown.</p>

        <p v-if="loading" class="inv-empty">Loading...</p>

        <div v-else class="inv-table-wrap">
          <table class="inv-table">
            <thead>
              <tr><th>When</th><th>Role</th><th>Action</th><th>Entity</th><th>Result</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="e in rows" :key="e.id">
                <td>{{ when(e.occurred_at) }}</td>
                <td>{{ e.actor_role }}</td>
                <td>
                  <StatusBadge :tone="methodTone(e.method)">{{ e.method }}</StatusBadge>
                  <small>{{ action(e) }}</small>
                </td>
                <td>{{ e.entity_type }}<small v-if="e.entity_ref">{{ shortRef(e.entity_ref) }}</small></td>
                <td>
                  <StatusBadge :tone="outcomeTone(e.outcome)">{{ cap(e.outcome) }}</StatusBadge>
                  <small>HTTP {{ e.status_code }}</small>
                </td>
                <td class="inv-actions"><button class="inv-btn sm" @click="detail = e">Details</button></td>
              </tr>
              <tr v-if="!rows.length"><td colspan="6" class="inv-empty">No audit events found.</td></tr>
            </tbody>
          </table>
        </div>

        <footer class="inv-pager" v-if="meta.last > 1">
          <button class="inv-btn" :disabled="meta.page <= 1 || loading" @click="load(meta.page - 1)">Previous</button>
          <span>Page {{ meta.page }} of {{ meta.last }} ({{ meta.total }} events)</span>
          <button class="inv-btn" :disabled="meta.page >= meta.last || loading" @click="load(meta.page + 1)">Next</button>
        </footer>
      </div>
    </template>

    <!-- Event details -->
    <Modal v-if="detail" title="Audit event" width="640px" @close="detail = null">
      <p class="sub">
        <StatusBadge :tone="outcomeTone(detail.outcome)">{{ cap(detail.outcome) }}</StatusBadge>
        · HTTP {{ detail.status_code }} · {{ when(detail.occurred_at) }}
      </p>
      <dl class="dl">
        <div><dt>Action</dt><dd>{{ detail.method }} {{ action(detail) }}</dd></div>
        <div><dt>Role</dt><dd>{{ detail.actor_role }}</dd></div>
        <div><dt>Actor id</dt><dd class="mono">{{ detail.actor_id }}</dd></div>
        <div><dt>Entity</dt><dd>{{ detail.entity_type }}</dd></div>
        <div><dt>Entity id</dt><dd class="mono">{{ detail.entity_ref || '-' }}</dd></div>
        <div><dt>IP address</dt><dd>{{ detail.ip || '-' }}</dd></div>
        <div class="wide"><dt>Path</dt><dd class="mono">{{ detail.path }}</dd></div>
        <div class="wide"><dt>Correlation id</dt><dd class="mono">{{ detail.correlation_id }}</dd></div>
      </dl>
      <h3 style="margin: 12px 0 6px">Request details</h3>
      <pre v-if="detail.payload" class="payload">{{ pretty(detail.payload) }}</pre>
      <p v-else class="hint">No request body was recorded. Sensitive values are redacted.</p>
      <div class="inv-buttons"><button class="inv-btn" @click="detail = null">Close</button></div>
    </Modal>
  </section>
</template>

<style scoped>
.filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px 12px; align-items: end; }
.filters label { display: grid; gap: 3px; font-size: 12px; font-weight: 600; }
.filters input, .filters select { padding: 8px 10px; border: 1px solid var(--inv-border); border-radius: 10px; font-size: 13px; font-weight: 400; background: #fff; }
.filters input:focus, .filters select:focus { outline: 2px solid var(--inv-green-100); border-color: var(--inv-green-700); }
.btns { display: flex; gap: 8px; }
.hint { color: var(--inv-muted); font-size: 12px; margin: 8px 0 12px; }
.dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px 18px; margin: 0; }
.dl dt { font-size: 12px; color: var(--inv-muted); }
.dl dd { margin: 2px 0 0; font-size: 13.5px; font-weight: 600; word-break: break-all; }
.dl .wide { grid-column: 1 / -1; }
.mono { font-family: ui-monospace, Consolas, monospace; font-weight: 500 !important; font-size: 12.5px !important; }
.payload { background: var(--inv-bg); border: 1px solid var(--inv-border); border-radius: 12px; padding: 12px; font-size: 12.5px; overflow-x: auto; margin: 0; }
</style>