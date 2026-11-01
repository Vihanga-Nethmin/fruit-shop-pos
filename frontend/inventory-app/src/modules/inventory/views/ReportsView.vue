<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { api, download } from '../api/client'
import '../theme.css'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'

// Temporary until the gateway token: OM = owner, SC = stock clerk
const isOwner = (import.meta.env.VITE_ROLE || 'OM') === 'OM'

const REPORTS = [
  { key: 'stock', label: 'Stock', path: '/reports/stock', period: false },
  { key: 'ageing', label: 'Ageing', path: '/reports/ageing', period: false },
  { key: 'wastage', label: 'Wastage', path: '/reports/wastage', period: true },
  { key: 'count', label: 'Count variance', path: '/reports/count-variance', period: true },
  { key: 'suppliers', label: 'Supplier receipts', path: '/reports/supplier-receipts', period: true },
  { key: 'reorder', label: 'Reorder suggestions', path: '/reports/reorder-suggestions', period: false },
]

const tab = ref('stock')
const groupBy = ref('product')
const from = ref('')
const to = ref('')

const data = ref(null)
const loading = ref(false)
const downloading = ref(false)
const error = ref('')
let seq = 0

const current = computed(() => REPORTS.find((r) => r.key === tab.value))
const rows = computed(() => data.value?.rows || [])

// ---------- helpers ----------
const unitLabel = (u) => (u === 'EACH' ? 'item' : 'kg')
const plain = (v, u) => Number(v ?? 0).toFixed(u === 'EACH' ? 0 : 3)
const qty = (v, u) => plain(v, u) + ' ' + unitLabel(u)
const money = (v) =>
  'LKR ' + Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const signed = (v, u) => {
  const n = Number(v ?? 0)
  return (n > 0 ? '+' : '') + plain(n, u)
}
const sum = (list, f) => list.reduce((s, x) => s + Number(f(x) || 0), 0)
const cap = (s) => String(s || '-').toLowerCase().replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
const day = (v) => (v ? String(v).slice(0, 10) : '-')
const batchLabel = (r) => cap(r.source) + ' ' + String(r.batch_id || '').slice(0, 8)
const qualityTone = (s) => (s === 'SALEABLE' ? 'green' : s === 'QUARANTINED' ? 'orange' : 'red')
const varClass = (v) => (Number(v) < 0 ? 'neg' : Number(v) > 0 ? 'pos' : '')
const product = { label: 'Product', get: (r) => r.name, sub: (r) => r.sku }

// ---------- table columns ----------
const columns = computed(() => {
  const k = tab.value

  if (k === 'stock' && groupBy.value === 'product') {
    return [
      product,
      { label: 'Physical', num: true, get: (r) => qty(r.physical_qty, r.unit) },
      { label: 'Reserved', num: true, get: (r) => plain(r.reserved_qty, r.unit) },
      { label: 'Sellable', num: true, get: (r) => plain(r.sellable_qty, r.unit) },
      { label: 'Blocked', num: true, get: (r) => plain(r.blocked_qty, r.unit) },
      { label: 'Stock value', num: true, get: (r) => money(r.stock_value) },
    ]
  }
  if (k === 'stock' && groupBy.value === 'batch') {
    return [
      product,
      { label: 'Batch', get: batchLabel },
      { label: 'Received', get: (r) => day(r.received_date) },
      { label: 'Sell-by / Use-by', get: (r) => r.sell_by_date || '-', sub: (r) => (r.use_by_date ? 'Use-by ' + r.use_by_date : '') },
      { label: 'Quality', get: (r) => cap(r.quality_state), tone: (r) => qualityTone(r.quality_state) },
      { label: 'Unit cost', num: true, get: (r) => money(r.unit_cost) },
      { label: 'Physical', num: true, get: (r) => qty(r.physical_qty, r.unit) },
      { label: 'Sellable', num: true, get: (r) => plain(r.sellable_qty, r.unit) },
      { label: 'Blocked', num: true, get: (r) => plain(r.blocked_qty, r.unit) },
      { label: 'Stock value', num: true, get: (r) => money(r.stock_value) },
    ]
  }
  if (k === 'stock') {
    return [
      { label: 'Location', get: (r) => r.location_code, sub: (r) => r.location },
      product,
      { label: 'Physical', num: true, get: (r) => qty(r.physical_qty, r.unit) },
      { label: 'Reserved', num: true, get: (r) => plain(r.reserved_qty, r.unit) },
      { label: 'Sellable', num: true, get: (r) => plain(r.sellable_qty, r.unit) },
      { label: 'Blocked', num: true, get: (r) => plain(r.blocked_qty, r.unit) },
      { label: 'Stock value', num: true, get: (r) => money(r.stock_value) },
    ]
  }
  if (k === 'ageing') {
    return [
      product,
      { label: 'Batch', get: batchLabel },
      { label: 'Received', get: (r) => day(r.received_date) },
      { label: 'Age', num: true, get: (r) => r.age_days + ' days' },
      { label: 'Age group', get: (r) => r.age_bucket, tone: () => 'blue' },
      { label: 'Quality', get: (r) => cap(r.quality_state), tone: (r) => qualityTone(r.quality_state) },
      { label: 'Physical', num: true, get: (r) => qty(r.physical_qty, r.unit) },
      { label: 'Stock value', num: true, get: (r) => money(r.stock_value) },
    ]
  }
  if (k === 'wastage') {
    return [
      { label: 'Reason', get: (r) => cap(r.reason) },
      product,
      { label: 'Records', num: true, get: (r) => r.records },
      { label: 'Quantity', num: true, get: (r) => qty(r.quantity, r.unit) },
      { label: 'Loss value', num: true, get: (r) => money(r.loss_value) },
    ]
  }
  if (k === 'count') {
    return [
      { label: 'Approved', get: (r) => day(r.approved_at) },
      product,
      { label: 'Location', get: (r) => r.location_code },
      { label: 'Batch type', get: (r) => cap(r.batch_source) },
      { label: 'Expected', num: true, get: (r) => plain(r.snapshot_qty, r.unit) },
      { label: 'Counted', num: true, get: (r) => plain(r.counted_qty, r.unit) },
      { label: 'Variance', num: true, get: (r) => signed(r.variance, r.unit) + ' ' + unitLabel(r.unit), cls: (r) => varClass(r.variance) },
      { label: 'Variance value', num: true, get: (r) => money(r.variance_value), cls: (r) => varClass(r.variance_value) },
    ]
  }
  if (k === 'suppliers') {
    return [
      { label: 'Supplier', get: (r) => r.supplier, sub: (r) => r.supplier_code },
      product,
      { label: 'Receipts', num: true, get: (r) => r.receipts },
      { label: 'Delivered', num: true, get: (r) => plain(r.delivered_qty, r.unit) },
      { label: 'Accepted', num: true, get: (r) => plain(r.accepted_qty, r.unit) },
      { label: 'Rejected', num: true, get: (r) => plain(r.rejected_qty, r.unit) },
      { label: 'Rejection rate', num: true, get: (r) => Number(r.rejection_rate_pct || 0).toFixed(2) + '%', tone: (r) => (Number(r.rejection_rate_pct) > 5 ? 'orange' : 'green') },
      { label: 'Total cost', num: true, get: (r) => money(r.total_cost) },
      { label: 'Avg unit cost', num: true, get: (r) => money(r.avg_unit_cost) },
    ]
  }
  // reorder: row fields mama nodannawa nisa, first row eke keys walin hadanawa
  const first = rows.value[0]
  if (!first) return []
  return Object.keys(first)
    .filter((key) => typeof first[key] !== 'object' || first[key] === null)
    .map((key) => ({
      label: cap(key),
      num: typeof first[key] === 'number' || /qty|value|cost|days/.test(key),
      get: (r) => (r[key] === null || r[key] === undefined || r[key] === '' ? '-' : r[key]),
    }))
})

// ---------- summary cards ----------
const ageBuckets = computed(() => {
  if (tab.value !== 'ageing') return []
  const map = new Map()
  rows.value.forEach((r) => {
    const b = map.get(r.age_bucket) || { bucket: r.age_bucket, batches: 0, value: 0 }
    b.batches += 1
    b.value += Number(r.stock_value || 0)
    map.set(r.age_bucket, b)
  })
  return [...map.values()]
})

const cards = computed(() => {
  const d = data.value
  if (!d) return []
  const k = tab.value
  const units = d.totals_by_unit || []

  if (k === 'stock') {
    return [
      { label: 'Total stock value', value: money(d.total_value), hint: 'At batch cost', icon: '💰', tone: 'green' },
      ...units.map((u) => ({
        label: 'Physical (' + unitLabel(u.unit) + ')',
        value: plain(u.physical_qty, u.unit),
        hint: 'Sellable ' + plain(u.sellable_qty, u.unit) + ' · Blocked ' + plain(u.blocked_qty, u.unit),
        icon: '📦',
        tone: 'blue',
      })),
    ]
  }
  if (k === 'ageing') {
    return [
      { label: 'Stock value', value: money(sum(rows.value, (r) => r.stock_value)), hint: 'All batches with stock', icon: '💰', tone: 'green' },
      { label: 'Batches', value: rows.value.length, hint: 'With stock on hand', icon: '🏷️', tone: 'blue' },
      { label: 'Oldest stock', value: rows.value.length ? Math.max(...rows.value.map((r) => r.age_days)) + ' days' : '-', hint: 'Since received', icon: '⏳', tone: 'orange' },
    ]
  }
  if (k === 'wastage') {
    return [
      { label: 'Total loss value', value: money(sum(units, (u) => u.loss_value)), hint: 'Approved wastage in this period', icon: '🗑️', tone: 'red' },
      { label: 'Wastage records', value: sum(rows.value, (r) => r.records), hint: 'Posted write-offs', icon: '📝', tone: 'orange' },
    ]
  }
  if (k === 'count') {
    return [
      { label: 'Shortages', value: money(sum(units, (u) => u.shortage_value)), hint: 'Stock found missing', icon: '📉', tone: 'red' },
      { label: 'Overages', value: money(sum(units, (u) => u.overage_value)), hint: 'Stock found extra', icon: '📈', tone: 'green' },
      { label: 'Counts approved', value: new Set(rows.value.map((r) => r.count_id)).size, hint: 'In this period', icon: '🧮', tone: 'blue' },
    ]
  }
  if (k === 'suppliers') {
    return [
      { label: 'Total cost', value: money(d.total_cost), hint: 'Accepted stock received', icon: '💰', tone: 'green' },
      { label: 'Receipts', value: sum(rows.value, (r) => r.receipts), hint: 'Posted in this period', icon: '🚚', tone: 'blue' },
      {
        label: 'Rejected',
        value: units.length ? units.map((u) => plain(u.rejected_qty, u.unit) + ' ' + unitLabel(u.unit)).join(' · ') : '0',
        hint: 'Quality rejections',
        icon: '⚠️',
        tone: 'orange',
      },
    ]
  }
  return [{ label: 'Suggestions', value: rows.value.length, hint: 'Advisory only', icon: '🛒', tone: 'blue' }]
})

// ---------- loading ----------
const flat = (errs) => Object.values(errs || {}).flat()

function message(r, fallback) {
  if (r.status === 403) return 'Reports are for the owner only.'
  if (r.status === 422) return flat(r.data?.errors).join(' ') || fallback
  return r.data?.error?.message || r.data?.message || fallback + ' (' + r.status + ').'
}

function params(extra = {}) {
  const p = { ...extra }
  if (tab.value === 'stock') p.group_by = groupBy.value
  if (current.value.period) {
    p.from = from.value || undefined
    p.to = to.value || undefined
  }
  return p
}

async function load() {
  if (!isOwner) return
  const mine = ++seq
  loading.value = true
  error.value = ''
  data.value = null
  try {
    const r = await api(current.value.path, { params: params() })
    if (mine !== seq) return
    if (r.ok) data.value = r.data.data
    else error.value = message(r, 'Could not load the report')
  } catch (e) {
    if (mine === seq) error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    if (mine === seq) loading.value = false
  }
}

async function downloadCsv() {
  downloading.value = true
  error.value = ''
  try {
    const r = await download(current.value.path, params({ format: 'csv' }))
    if (!r.ok) {
      error.value = message(r, 'Could not download the file')
      return
    }
    const url = URL.createObjectURL(r.blob)
    const a = document.createElement('a')
    a.href = url
    a.download = r.filename || current.value.key + '-report.csv'
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

function resetDates() {
  from.value = ''
  to.value = ''
  load()
}

watch([tab, groupBy], load)
onMounted(load)
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Reports">
      <button v-if="isOwner" class="inv-btn" :disabled="loading" @click="load">{{ loading ? 'Refreshing...' : 'Refresh' }}</button>
      <button v-if="isOwner" class="inv-btn primary" :disabled="downloading || loading" @click="downloadCsv">
        {{ downloading ? 'Preparing...' : 'Download CSV' }}
      </button>
    </PageHeader>

    <p v-if="!isOwner" class="inv-alert-err">Reports are for the owner only.</p>

    <template v-else>
      <p v-if="error" class="inv-alert-err">{{ error }}</p>

      <div class="inv-chips" style="margin-bottom: 16px">
        <button v-for="r in REPORTS" :key="r.key" class="inv-chip" :class="{ on: tab === r.key }" @click="tab = r.key">
          {{ r.label }}
        </button>
      </div>

      <div class="inv-grid-4" v-if="cards.length">
        <StatCard v-for="c in cards" :key="c.label" :label="c.label" :value="c.value" :hint="c.hint" :icon="c.icon" :tone="c.tone" />
      </div>

      <div class="inv-card">
        <div class="toolbar">
          <div v-if="tab === 'stock'" class="inv-chips">
            <button class="inv-chip" :class="{ on: groupBy === 'product' }" @click="groupBy = 'product'">By product</button>
            <button class="inv-chip" :class="{ on: groupBy === 'batch' }" @click="groupBy = 'batch'">By batch</button>
            <button class="inv-chip" :class="{ on: groupBy === 'location' }" @click="groupBy = 'location'">By location</button>
          </div>

          <form v-if="current.period" class="period" @submit.prevent="load">
            <label>From <input v-model="from" type="date" /></label>
            <label>To <input v-model="to" type="date" /></label>
            <button class="inv-btn" :disabled="loading">Apply</button>
            <button type="button" class="inv-btn" :disabled="loading" @click="resetDates">Last 30 days</button>
            <span v-if="data" class="range">Showing {{ data.from }} to {{ data.to }}</span>
          </form>

          <span v-if="data" class="range">Generated {{ new Date(data.generated_at).toLocaleString() }}</span>
        </div>

        <p v-if="tab === 'reorder' && data?.advisory" class="inv-alert-ok">{{ data.advisory }}</p>

        <div v-if="tab === 'ageing' && ageBuckets.length" class="buckets">
          <div v-for="b in ageBuckets" :key="b.bucket" class="bucket">
            <strong>{{ b.bucket }}</strong>
            <span>{{ b.batches }} batches · {{ money(b.value) }}</span>
          </div>
        </div>

        <div v-if="tab === 'wastage' && data?.by_reason?.length" class="buckets">
          <div v-for="b in data.by_reason" :key="b.reason" class="bucket">
            <strong>{{ cap(b.reason) }}</strong>
            <span>{{ money(b.loss_value) }}</span>
          </div>
        </div>

        <p v-if="loading" class="inv-empty">Loading...</p>

        <div v-else class="inv-table-wrap">
          <table class="inv-table">
            <thead>
              <tr><th v-for="c in columns" :key="c.label" :class="{ num: c.num }">{{ c.label }}</th></tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in rows" :key="i">
                <td v-for="c in columns" :key="c.label" :class="[{ num: c.num }, c.cls ? c.cls(r) : '']">
                  <StatusBadge v-if="c.tone" :tone="c.tone(r)">{{ c.get(r) }}</StatusBadge>
                  <template v-else>{{ c.get(r) }}</template>
                  <small v-if="c.sub && c.sub(r)">{{ c.sub(r) }}</small>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td :colspan="Math.max(columns.length, 1)" class="inv-empty">
                  {{ tab === 'reorder' ? 'No reorder suggestions right now.' : 'No data for this report.' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.period { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.period label { display: grid; gap: 3px; font-size: 12px; font-weight: 600; }
.period input { padding: 7px 9px; border: 1px solid var(--inv-border); border-radius: 10px; font-size: 13px; }
.range { font-size: 12.5px; color: var(--inv-muted); }
.buckets { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.bucket { background: var(--inv-bg); border: 1px solid var(--inv-border); border-radius: 12px; padding: 8px 14px; display: grid; gap: 2px; font-size: 13px; }
.bucket span { color: var(--inv-muted); font-size: 12.5px; }
.neg { color: var(--inv-red); font-weight: 600; }
.pos { color: var(--inv-green-700); font-weight: 600; }
</style>