<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api } from '../api/client'
import '../theme.css'
import { errOf, apiMessage } from '../api/lookups'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import StatusBadge from '../components/StatusBadge.vue'
import Modal from '../components/Modal.vue'

// Eka eka tab ekata fields, table columns. createOnly = aluth karaddi witharai (passe wenas karanna bae)
const TABS = {
  suppliers: {
    label: 'Suppliers',
    one: 'supplier',
    path: '/suppliers',
    fields: [
      { key: 'supplier_code', label: 'Supplier code', createOnly: true, required: true },
      { key: 'name', label: 'Name', required: true },
      { key: 'contact_person', label: 'Contact person' },
      { key: 'phone', label: 'Phone', required: true },
      { key: 'email', label: 'Email', type: 'email' },
      { key: 'address', label: 'Address' },
      { key: 'notes', label: 'Notes', type: 'textarea' },
    ],
    cols: [
      { label: 'Supplier', get: (r) => r.name, sub: (r) => r.supplier_code },
      { label: 'Contact', get: (r) => r.contact_person || '-', sub: (r) => r.phone },
      { label: 'Email', get: (r) => r.email || '-' },
      { label: 'Address', get: (r) => r.address || '-' },
    ],
  },
  locations: {
    label: 'Locations',
    one: 'location',
    path: '/locations',
    fields: [
      { key: 'code', label: 'Code', createOnly: true, required: true },
      { key: 'name', label: 'Name', required: true },
    ],
    cols: [
      { label: 'Code', get: (r) => r.code },
      { label: 'Name', get: (r) => r.name },
    ],
  },
  categories: {
    label: 'Categories',
    one: 'category',
    path: '/categories',
    fields: [{ key: 'name', label: 'Name', required: true }],
    cols: [{ label: 'Name', get: (r) => r.name }],
  },
}

const tab = ref('suppliers')
const lists = reactive({ suppliers: [], locations: [], categories: [] })
const loading = ref(false)
const message = ref('')
const error = ref('')
const search = ref('')
const statusFilter = ref('ALL')

const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)
const errors = ref({})
const form = reactive({})

const cfg = computed(() => TABS[tab.value])

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return lists[tab.value].filter((r) => {
    if (statusFilter.value === 'ACTIVE' && !r.is_active) return false
    if (statusFilter.value === 'INACTIVE' && r.is_active) return false
    if (!q) return true
    return [r.name, r.code, r.supplier_code, r.contact_person, r.phone, r.email]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
      .includes(q)
  })
})

const activeOf = (key) => lists[key].filter((r) => r.is_active).length

async function load(key) {
  const r = await api(TABS[key].path)
  if (r.ok) lists[key] = r.data.data || []
  else error.value = apiMessage(r, 'Could not load ' + TABS[key].label.toLowerCase())
}

async function loadAll() {
  loading.value = true
  error.value = ''
  try {
    await Promise.all(Object.keys(TABS).map(load))
  } catch (e) {
    error.value = 'Cannot reach the API. Is php artisan serve running?'
  } finally {
    loading.value = false
  }
}

function setTab(key) {
  tab.value = key
  search.value = ''
  statusFilter.value = 'ALL'
  message.value = ''
}

function fillForm(row) {
  Object.keys(form).forEach((k) => delete form[k])
  cfg.value.fields.forEach((f) => {
    form[f.key] = row ? row[f.key] ?? '' : ''
  })
}

function openCreate() {
  editing.value = null
  fillForm(null)
  errors.value = {}
  message.value = ''
  showForm.value = true
}

function openEdit(row) {
  editing.value = row
  fillForm(row)
  errors.value = {}
  message.value = ''
  showForm.value = true
}

async function save() {
  saving.value = true
  errors.value = {}

  const body = {}
  cfg.value.fields.forEach((f) => {
    if (editing.value && f.createOnly) return
    const v = String(form[f.key] ?? '').trim()
    if (editing.value) body[f.key] = v === '' && !f.required ? null : v // edit: blank optional field clear wenawa
    else if (v !== '') body[f.key] = v
  })

  const r = editing.value
    ? await api(cfg.value.path + '/' + editing.value.id, { method: 'PATCH', body })
    : await api(cfg.value.path, { method: 'POST', body })
  saving.value = false

  if (r.ok) {
    showForm.value = false
    message.value = editing.value ? 'Saved changes.' : 'Added the ' + cfg.value.one + '.'
    await load(tab.value)
  } else if (r.status === 422) {
    errors.value = r.data.errors || {}
  } else {
    errors.value = { general: [apiMessage(r, 'Something went wrong')] }
  }
}

async function toggleActive(row) {
  const action = row.is_active ? 'Deactivate' : 'Activate'
  const extra = row.is_active ? ' It will no longer appear in dropdowns. The record is kept.' : ''
  if (!window.confirm(action + ' ' + row.name + '?' + extra)) return
  message.value = ''
  error.value = ''
  const r = await api(cfg.value.path + '/' + row.id, { method: 'PATCH', body: { is_active: !row.is_active } })
  if (r.ok) {
    message.value = row.name + (row.is_active ? ' deactivated.' : ' activated.')
    await load(tab.value)
  } else {
    error.value = apiMessage(r, 'Could not update the ' + cfg.value.one)
  }
}

onMounted(loadAll)
</script>

<template>
  <section class="inv-page">
    <PageHeader title="Master data">
      <button class="inv-btn primary" @click="openCreate">+ Add {{ cfg.one }}</button>
    </PageHeader>

    <p v-if="message" class="inv-alert-ok">{{ message }}</p>
    <p v-if="error" class="inv-alert-err">{{ error }}</p>

    <div class="inv-grid-4">
      <StatCard label="Suppliers" :value="lists.suppliers.length" :hint="activeOf('suppliers') + ' active'" icon="🚚" tone="blue" />
      <StatCard label="Locations" :value="lists.locations.length" :hint="activeOf('locations') + ' active'" icon="📍" tone="green" />
      <StatCard label="Categories" :value="lists.categories.length" :hint="activeOf('categories') + ' active'" icon="🗂️" tone="orange" />
    </div>

    <div class="inv-chips" style="margin-bottom: 16px">
      <button v-for="(t, key) in TABS" :key="key" class="inv-chip" :class="{ on: tab === key }" @click="setTab(key)">
        {{ t.label }}
      </button>
    </div>

    <div class="inv-card">
      <div class="toolbar">
        <input v-model="search" class="search" :placeholder="'Search ' + cfg.label.toLowerCase() + '...'" />
        <div class="inv-chips">
          <button class="inv-chip" :class="{ on: statusFilter === 'ALL' }" @click="statusFilter = 'ALL'">All</button>
          <button class="inv-chip" :class="{ on: statusFilter === 'ACTIVE' }" @click="statusFilter = 'ACTIVE'">Active</button>
          <button class="inv-chip" :class="{ on: statusFilter === 'INACTIVE' }" @click="statusFilter = 'INACTIVE'">Inactive</button>
        </div>
      </div>

      <p v-if="loading" class="inv-empty">Loading...</p>

      <div v-else class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th v-for="c in cfg.cols" :key="c.label">{{ c.label }}</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in visible" :key="r.id" :class="{ off: !r.is_active }">
              <td v-for="c in cfg.cols" :key="c.label">
                {{ c.get(r) }}<small v-if="c.sub && c.sub(r)">{{ c.sub(r) }}</small>
              </td>
              <td><StatusBadge :tone="r.is_active ? 'green' : 'gray'">{{ r.is_active ? 'Active' : 'Inactive' }}</StatusBadge></td>
              <td class="inv-actions">
                <button class="inv-btn sm" @click="openEdit(r)">Edit</button>
                <button class="inv-btn sm" @click="toggleActive(r)">{{ r.is_active ? 'Deactivate' : 'Activate' }}</button>
              </td>
            </tr>
            <tr v-if="!visible.length">
              <td :colspan="cfg.cols.length + 2" class="inv-empty">No {{ cfg.label.toLowerCase() }} found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="hint">Inactive {{ cfg.label.toLowerCase() }} are hidden from dropdowns in other pages. Records are never deleted.</p>
    </div>

    <!-- Add / edit -->
    <Modal v-if="showForm" :title="(editing ? 'Edit ' : 'Add ') + cfg.one" width="520px" @close="showForm = false">
      <form class="inv-form" @submit.prevent="save">
        <p v-if="errors.general" class="inv-alert-err">{{ errors.general[0] }}</p>

        <label v-for="f in cfg.fields" :key="f.key">
          {{ f.label }} <span v-if="f.required && !(editing && f.createOnly)" class="req">*</span>
          <textarea v-if="f.type === 'textarea'" v-model="form[f.key]" rows="2" />
          <input v-else v-model="form[f.key]" :type="f.type || 'text'" :disabled="!!editing && f.createOnly" />
          <small v-if="editing && f.createOnly" class="hint">The {{ f.label.toLowerCase() }} cannot be changed.</small>
          <small class="inv-err">{{ errOf(errors, f.key) }}</small>
        </label>

        <div class="inv-buttons">
          <button type="button" class="inv-btn" @click="showForm = false">Cancel</button>
          <button class="inv-btn primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
        </div>
      </form>
    </Modal>
  </section>
</template>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.search { flex: 1; min-width: 220px; max-width: 340px; padding: 9px 14px; border: 1px solid var(--inv-border); border-radius: 10px; font-size: 13.5px; background: var(--inv-bg); }
.search:focus { outline: 2px solid var(--inv-green-100); border-color: var(--inv-green-700); }
.off td { color: var(--inv-muted); }
.hint { color: var(--inv-muted); font-weight: 400; font-size: 12px; margin: 12px 0 0; }
.req { color: var(--inv-red); }
</style>