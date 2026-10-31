<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { api } from '../api/client'
import '../theme.css'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'

const REFRESH_MS = 5 * 60 * 1000 // SRS 8.2: 5 minutes

const alerts = ref([])
const availability = ref([])
const positions = ref([])
const meta = reactive({ page: 1, last: 1, total: 0 })

const loading = ref(false)
const error = ref('')
const lastUpdated = ref(null)
const activeType = ref('ALL')
let timer = null

const today = () => new Date().toLocaleDateString('en-CA')

const unitLabel = (u) => (u === 'KG' ? 'kg' : 'item')
const qty = (v, unit) => Number(v ?? 0).toFixed(unit === 'KG' ? 3 : 0)

function prettyType(t) {
  return String(t || 'ALERT').toLowerCase().replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
}
function alertText(a) {
  const name = a.product_name || a.product?.name || a.name || ''
  const msg = a.message || prettyType(a.type)
  return name && msg.startsWith(name + ':') ? msg.slice(name.length + 1).trim() : msg
}

function severityTone(s) {
  const v = String(s || '').toUpperCase()
  if (v === 'HIGH' || v === 'CRITICAL' || v === 'ERROR') return 'red'
  if (v === 'MEDIUM' || v === 'WARNING') return 'orange'
  return 'blue'
}

function stateTone(state) {
  if (state === 'SALEABLE') return 'green'
  if (state === 'QUARANTINED') return 'red'
  return 'gray'
}

// Alert chips: All + each type with its count
const chips = computed(() => {
  const counts = {}
  alerts.value.forEach((a) => {
    counts[a.type] = (counts[a.type] || 0) + 1
  })
  return [
    { type: 'ALL', label: 'All', count: alerts.value.length },
    ...Object.entries(counts).map(([type, count]) => ({ type, label: prettyType(type), count })),
  ]
})

const visibleAlerts = computed(() =>
  activeType.value === 'ALL' ? alerts.value : alerts.value.filter((a) => a.type === activeType.value),
)

const noSellable = computed(() => alerts.value.filter((a) => a.type === 'ZERO_SELLABLE').length)
async function loadAlerts() {
  const r = await api('/inventory/alerts')
  if (!r.ok) throw new Error('alerts ' + r.status)
  alerts.value = r.data?.data?.alerts || []
}

async function loadAvailability() {
  const r = await api('/inventory/availability')
  if (!r.ok) throw new Error('availability ' + r.status)
  availability.value = r.data?.data || []
}

async function loadPositions(page = 1) {
  const r = await api('/inventory/positions', { params: { page } })
  if (!r.ok) throw new Error('positions ' + r.status)
  const d = r.data.data
  positions.value = d.data
  meta.page = d.current_page
  meta.last = d.last_page
  meta.total = d.total
}

async function refresh(page = meta.page) {
  loading.value = true
  error.value = ''
  try {
    await Promise.all([loadAlerts(), loadAvailability(), loadPositions(page)])
    lastUpdated.value = new Date()
  } catch (e) {
    error.value = 'Could not load stock data (' + e.message + '). Is php artisan serve running?'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  refresh(1)
  timer = setInterval(() => refresh(), REFRESH_MS)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Stock">
      <span class="updated" v-if="lastUpdated">
        Updated {{ lastUpdated.toLocaleTimeString() }}
      </span>
      <button class="inv-btn primary" :disabled="loading" @click="refresh()">
        {{ loading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </PageHeader>

    <p v-if="error" class="inv-alert-err">{{ error }}</p>

    <div class="inv-grid-4">
      <StatCard label="Active alerts" :value="alerts.length" hint="Items needing attention" icon="🔔" tone="orange" />
      <StatCard label="Products tracked" :value="availability.length" hint="With stock records" icon="📦" tone="green" />
      <StatCard label="No sellable stock" :value="noSellable" hint="Sellable quantity is zero" icon="⚠️" tone="red" />
      <StatCard label="Stock lines" :value="meta.total" hint="Batch and location positions" icon="🏷️" tone="blue" />
    </div>

    <!-- Alerts -->
    <div class="inv-card block">
      <h3>Alerts</h3>
      <p class="sub">Click a chip to filter</p>

      <div class="chips">
        <button
          v-for="c in chips"
          :key="c.type"
          class="chip"
          :class="{ on: activeType === c.type }"
          @click="activeType = c.type"
        >
          {{ c.label }} <b>{{ c.count }}</b>
        </button>
      </div>

      <ul class="alert-list" v-if="visibleAlerts.length">
        <li v-for="(a, i) in visibleAlerts" :key="i">
          <div>
            <strong>{{ a.product_name || a.product?.name || a.name || a.sku || prettyType(a.type) }}</strong>
<small>{{ alertText(a) }}</small>

          </div>
          <StatusBadge :tone="severityTone(a.severity)">{{ a.severity ? prettyType(a.severity) : prettyType(a.type) }}</StatusBadge>
        </li>
      </ul>
      <p v-else class="inv-empty">No alerts. Everything looks fine.</p>
    </div>

    <!-- Availability -->
    <div class="inv-card block">
      <h3>Availability by product</h3>
      <p class="sub">Sellable = physical - reserved, in-date and saleable stock only</p>
      <div class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th>Product</th><th class="num">Physical</th><th class="num">Reserved</th>
              <th class="num">Sellable</th><th class="num">Blocked</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in availability" :key="r.product_id">
              <td>{{ r.name }}<small>{{ r.sku }}</small></td>
              <td class="num">{{ qty(r.physical_qty, r.base_unit) }} {{ unitLabel(r.base_unit) }}</td>
              <td class="num">{{ qty(r.reserved_qty, r.base_unit) }}</td>
              <td class="num">
                <StatusBadge :tone="Number(r.sellable_qty) > 0 ? 'green' : 'red'">
                  {{ qty(r.sellable_qty, r.base_unit) }} {{ unitLabel(r.base_unit) }}
                </StatusBadge>
              </td>
              <td class="num">{{ qty(r.blocked_qty, r.base_unit) }}</td>
            </tr>
            <tr v-if="!availability.length"><td colspan="5" class="inv-empty">No stock records yet.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Batches and locations -->
    <div class="inv-card block">
      <h3>Batches and locations</h3>
      <p class="sub">Every stock position</p>
      <div class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th>Product</th><th>Location</th><th>Batch</th><th>Ripeness</th>
              <th>Sell-by / Use-by</th><th>Quality</th>
              <th class="num">Physical</th><th class="num">Reserved</th><th class="num">Sellable</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in positions" :key="p.id">
              <td>{{ p.product?.name }}<small>{{ p.product?.sku }}</small></td>
              <td>{{ p.location?.code }}<small>{{ p.location?.name }}</small></td>
              <td>{{ p.batch?.source }}<small>{{ String(p.batch_id).slice(0, 8) }}</small></td>
              <td>{{ p.batch?.ripeness || '-' }}</td>
              <td>
                <template v-if="p.batch?.sell_by_date || p.batch?.use_by_date">
                  <span v-if="p.batch?.sell_by_date">Sell-by {{ p.batch.sell_by_date }}</span>
                  <small v-if="p.batch?.use_by_date">Use-by {{ p.batch.use_by_date }}</small>
                  <StatusBadge
                    v-if="p.batch?.use_by_date && p.batch.use_by_date < today()"
                    tone="red"
                  >Past use-by</StatusBadge>
                  <StatusBadge
                    v-else-if="p.batch?.sell_by_date && p.batch.sell_by_date < today()"
                    tone="orange"
                  >Past sell-by</StatusBadge>
                </template>
                <template v-else>-</template>
              </td>
              <td><StatusBadge :tone="stateTone(p.batch?.quality_state)">{{ prettyType(p.batch?.quality_state) }}</StatusBadge></td>
              <td class="num">{{ qty(p.physical_qty, p.product?.base_unit) }}</td>
              <td class="num">{{ qty(p.reserved_qty, p.product?.base_unit) }}</td>
              <td class="num">{{ qty(p.sellable_qty, p.product?.base_unit) }}</td>
            </tr>
            <tr v-if="!positions.length"><td colspan="9" class="inv-empty">No positions yet.</td></tr>
          </tbody>
        </table>
      </div>

      <footer class="pager" v-if="meta.last > 1">
        <button class="inv-btn" :disabled="meta.page <= 1 || loading" @click="refresh(meta.page - 1)">Previous</button>
        <span>Page {{ meta.page }} of {{ meta.last }} ({{ meta.total }} lines)</span>
        <button class="inv-btn" :disabled="meta.page >= meta.last || loading" @click="refresh(meta.page + 1)">Next</button>
      </footer>
    </div>
  </section>
</template>

<style scoped>
.block { margin-bottom: 20px; }
.updated { font-size: 12.5px; color: var(--inv-muted); }
.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.chip { border: 1px solid var(--inv-border); background: #fff; border-radius: 999px; padding: 6px 14px; font-size: 13px; cursor: pointer; color: var(--inv-text); }
.chip b { margin-left: 4px; color: var(--inv-muted); }
.chip.on { background: var(--inv-green-900); color: #fff; border-color: var(--inv-green-900); }
.chip.on b { color: #cfe8da; }
.alert-list { list-style: none; margin: 0; padding: 0; }
.alert-list li { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--inv-border); }
.alert-list li:last-child { border-bottom: 0; }
.alert-list small { display: block; color: var(--inv-muted); font-size: 12.5px; margin-top: 2px; }
.pager { display: flex; justify-content: center; align-items: center; gap: 14px; margin-top: 14px; font-size: 13px; color: var(--inv-muted); }
</style>