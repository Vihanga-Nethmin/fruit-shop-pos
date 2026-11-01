<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { api } from "../api/client";
import {
  unwrapList,
  errOf,
  today,
  canSeeCost,
  statusTone,
  prettyStatus,
  money,
  apiMessage,
} from "../api/lookups";
import StatusBadge from "./StatusBadge.vue";
import Modal from "./Modal.vue";

const props = defineProps({ lookups: { type: Object, required: true } });

// Backend rules wala ripeness values thawa thiyenawa nam methana add karanna
const RIPENESS = ["UNRIPE", "READY", "OVERRIPE"];
const rows = ref([]);
const meta = reactive({ page: 1, last: 1, total: 0 });
const loading = ref(false);
const message = ref("");
const error = ref("");

const showForm = ref(false);
const saving = ref(false);
const errors = ref({});
const viewing = ref(null);

const emptyLine = () => ({
  product_id: "",
  location_id: "",
  delivered_qty: "",
  accepted_qty: "",
  rejected_qty: "",
  gross_weight: "",
  tare_weight: "",
  line_cost: "",
  ripeness: "READY",
  sell_by_date: "",
  use_by_date: "",
  reject_reason: "",
  touched: false,
});
const form = reactive({
  supplier_id: "",
  receipt_date: today(),
  delivery_reference: "",
  notes: "",
  lines: [emptyLine()],
});

const activeSuppliers = computed(() =>
  props.lookups.suppliers.filter((s) => s.is_active !== false),
);
const activeLocations = computed(() =>
  props.lookups.locations.filter((l) => l.is_active !== false),
);
const activeProducts = computed(() =>
  props.lookups.products.filter((p) => p.is_active !== false),
);

const supplierName = (id) =>
  props.lookups.suppliers.find((s) => s.id === id)?.name ||
  String(id).slice(0, 8);
const productOf = (id) => props.lookups.products.find((p) => p.id === id);
const productName = (id) => productOf(id)?.name || String(id).slice(0, 8);
const locationCode = (id) =>
  props.lookups.locations.find((l) => l.id === id)?.code ||
  String(id).slice(0, 8);

const unitOf = (l) =>
  productOf(l.product_id)?.base_unit === "EACH" ? "item" : "kg";
const stepOf = (l) => (unitOf(l) === "item" ? "1" : "0.001");
const isKg = (l) => productOf(l.product_id)?.base_unit === "KG";
const le = (i, f) => errOf(errors.value, "lines." + i + "." + f);
const fmtDate = (v) => (v ? new Date(v).toLocaleString() : "-");

const num = (v) => (v === "" || v === null ? undefined : Number(v));
const str = (v) => (v === "" || v === null ? undefined : v);

async function load(page = 1) {
  loading.value = true;
  error.value = "";
  try {
    const r = await api("/goods-receipts", { params: { page } });
    if (r.ok) {
      const l = unwrapList(r);
      rows.value = l.items;
      Object.assign(meta, { page: l.page, last: l.last, total: l.total });
    } else {
      error.value = apiMessage(r, "Could not load goods receipts");
    }
  } catch (e) {
    error.value = "Cannot reach the API. Is php artisan serve running?";
  } finally {
    loading.value = false;
  }
}

function openForm() {
  Object.assign(form, {
    supplier_id: "",
    receipt_date: today(),
    delivery_reference: "",
    notes: "",
    lines: [emptyLine()],
  });
  errors.value = {};
  message.value = "";
  showForm.value = true;
}

const addLine = () => form.lines.push(emptyLine());
const removeLine = (i) => form.lines.splice(i, 1);
function syncAccepted(l) {
  if (!l.touched) l.accepted_qty = l.delivered_qty;
}

async function createReceipt() {
  saving.value = true;
  errors.value = {};
  const body = {
    supplier_id: form.supplier_id,
    receipt_date: form.receipt_date,
    delivery_reference: str(form.delivery_reference),
    notes: str(form.notes),
    lines: form.lines.map((l) => ({
      product_id: l.product_id,
      location_id: l.location_id,
      delivered_qty: num(l.delivered_qty),
      accepted_qty: num(l.accepted_qty),
      rejected_qty: num(l.rejected_qty),
      gross_weight: num(l.gross_weight),
      tare_weight: num(l.tare_weight),
      line_cost: num(l.line_cost),
      ripeness: l.ripeness,
      sell_by_date: str(l.sell_by_date),
      use_by_date: str(l.use_by_date),
      reject_reason: str(l.reject_reason),
    })),
  };
  const r = await api("/goods-receipts", { method: "POST", body });
  saving.value = false;

  if (r.status === 201) {
    showForm.value = false;
    message.value = "Receipt saved as a draft. Post it to add the stock.";
    await load(1);
  } else if (r.status === 422) {
    errors.value = r.data.errors || {};
  } else {
    errors.value = { general: [apiMessage(r, "Something went wrong")] };
  }
}

async function view(row) {
  const r = await api("/goods-receipts/" + row.id);
  if (r.ok) viewing.value = r.data.data;
  else error.value = apiMessage(r, "Could not open the receipt");
}

async function post(row) {
  if (
    !window.confirm(
      "Post this receipt? Accepted stock is added and the receipt cannot be edited.",
    )
  )
    return;
  message.value = "";
  error.value = "";
  const r = await api("/goods-receipts/" + row.id + "/post", {
    method: "POST",
  });
  if (r.ok) {
    message.value = "Receipt posted. Batches created.";
    await load(meta.page);
  } else {
    error.value = apiMessage(r, "Could not post the receipt");
  }
}

onMounted(() => load());
</script>

<template>
  <div class="inv-card">
    <div class="inv-toolbar">
      <div>
        <h3>Goods receipts</h3>
        <p class="sub" style="margin-bottom: 0">
          Deliveries from suppliers. Only accepted quantity enters stock.
        </p>
      </div>
      <button class="inv-btn primary" @click="openForm">+ New receipt</button>
    </div>

    <p v-if="message" class="inv-alert-ok">{{ message }}</p>
    <p v-if="error" class="inv-alert-err">{{ error }}</p>
    <p v-if="loading" class="inv-empty">Loading...</p>

    <div v-else class="inv-table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Supplier</th>
            <th>Delivery ref</th>
            <th>Status</th>
            <th>Posted</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id">
            <td>{{ r.receipt_date }}</td>
            <td>{{ supplierName(r.supplier_id) }}</td>
            <td>{{ r.delivery_reference || "-" }}</td>
            <td>
              <StatusBadge :tone="statusTone(r.status)">{{
                prettyStatus(r.status)
              }}</StatusBadge>
            </td>
            <td>{{ fmtDate(r.posted_at) }}</td>
            <td class="inv-actions">
              <button class="inv-btn sm" @click="view(r)">View</button>
              <button
                v-if="r.status === 'DRAFT'"
                class="inv-btn sm primary"
                @click="post(r)"
              >
                Post
              </button>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="6" class="inv-empty">No goods receipts yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <footer class="inv-pager" v-if="meta.last > 1">
      <button
        class="inv-btn"
        :disabled="meta.page <= 1"
        @click="load(meta.page - 1)"
      >
        Previous
      </button>
      <span
        >Page {{ meta.page }} of {{ meta.last }} ({{
          meta.total
        }}
        receipts)</span
      >
      <button
        class="inv-btn"
        :disabled="meta.page >= meta.last"
        @click="load(meta.page + 1)"
      >
        Next
      </button>
    </footer>
  </div>

  <!-- New receipt -->
  <Modal
    v-if="showForm"
    title="New goods receipt"
    width="760px"
    @close="showForm = false"
  >
    <form class="inv-form" @submit.prevent="createReceipt">
      <p v-if="errors.general" class="inv-alert-err">{{ errors.general[0] }}</p>

      <div class="inv-row">
        <label
          >Supplier
          <select v-model="form.supplier_id">
            <option value="" disabled>Select a supplier</option>
            <option v-for="s in activeSuppliers" :key="s.id" :value="s.id">
              {{ s.name }}
            </option>
          </select>
          <small class="inv-err">{{ errOf(errors, "supplier_id") }}</small>
        </label>
        <label
          >Receipt date
          <input v-model="form.receipt_date" type="date" />
          <small class="inv-err">{{ errOf(errors, "receipt_date") }}</small>
        </label>
        <label
          >Delivery reference <input v-model="form.delivery_reference"
        /></label>
      </div>

      <div v-for="(l, i) in form.lines" :key="i" class="inv-line">
        <div class="inv-line-head">
          <strong>Line {{ i + 1 }}</strong>
          <button
            v-if="form.lines.length > 1"
            type="button"
            class="inv-btn sm"
            @click="removeLine(i)"
          >
            Remove
          </button>
        </div>

        <div class="inv-row">
          <label
            >Product
            <select v-model="l.product_id">
              <option value="" disabled>Select a product</option>
              <option v-for="p in activeProducts" :key="p.id" :value="p.id">
                {{ p.name }} ({{ p.sku }})
              </option>
            </select>
            <small class="inv-err">{{ le(i, "product_id") }}</small>
          </label>
          <label
            >Location
            <select v-model="l.location_id">
              <option value="" disabled>Select a location</option>
              <option
                v-for="loc in activeLocations"
                :key="loc.id"
                :value="loc.id"
              >
                {{ loc.code }} - {{ loc.name }}
              </option>
            </select>
            <small class="inv-err">{{ le(i, "location_id") }}</small>
          </label>
        </div>

        <div class="inv-row">
          <label
            >Delivered ({{ unitOf(l) }})
            <input
              v-model="l.delivered_qty"
              type="number"
              :step="stepOf(l)"
              min="0"
              @input="syncAccepted(l)"
            />
            <small class="inv-err">{{ le(i, "delivered_qty") }}</small>
          </label>
          <label
            >Accepted ({{ unitOf(l) }})
            <input
              v-model="l.accepted_qty"
              type="number"
              :step="stepOf(l)"
              min="0"
              @input="l.touched = true"
            />
            <small class="inv-err">{{ le(i, "accepted_qty") }}</small>
          </label>
          <label
            >Rejected ({{ unitOf(l) }})
            <input
              v-model="l.rejected_qty"
              type="number"
              :step="stepOf(l)"
              min="0"
            />
            <small class="inv-err">{{ le(i, "rejected_qty") }}</small>
          </label>
        </div>

        <div class="inv-row" v-if="Number(l.rejected_qty) > 0">
          <label
            >Reject reason
            <input v-model="l.reject_reason" />
            <small class="inv-err">{{ le(i, "reject_reason") }}</small>
          </label>
        </div>

        <div class="inv-row" v-if="isKg(l)">
          <label
            >Gross weight (kg)
            <input v-model="l.gross_weight" type="number" step="0.001" min="0"
          /></label>
          <label
            >Tare weight (kg)
            <input v-model="l.tare_weight" type="number" step="0.001" min="0"
          /></label>
        </div>

        <div class="inv-row">
          <label
            >Line cost (LKR, total)
            <input v-model="l.line_cost" type="number" step="0.01" min="0" />
            <small class="inv-err">{{ le(i, "line_cost") }}</small>
          </label>
          <label
            >Ripeness
            <select v-model="l.ripeness">
              <option v-for="r in RIPENESS" :key="r" :value="r">{{ r }}</option>
            </select>
          </label>
        </div>

        <div class="inv-row">
          <label
            >Sell-by date
            <input v-model="l.sell_by_date" type="date" />
            <small class="inv-err">{{ le(i, "sell_by_date") }}</small>
          </label>
          <label
            >Use-by date (optional)
            <input v-model="l.use_by_date" type="date" />
            <small class="inv-err">{{ le(i, "use_by_date") }}</small>
          </label>
        </div>
      </div>

      <button type="button" class="inv-btn" @click="addLine">+ Add line</button>

      <label>Notes <textarea v-model="form.notes" rows="2" /></label>

      <div class="inv-buttons">
        <button type="button" class="inv-btn" @click="showForm = false">
          Cancel
        </button>
        <button class="inv-btn primary" :disabled="saving">
          {{ saving ? "Saving..." : "Save draft" }}
        </button>
      </div>
    </form>
  </Modal>

  <!-- View receipt -->
  <Modal
    v-if="viewing"
    title="Goods receipt"
    width="860px"
    @close="viewing = null"
  >
    <p class="sub">
      {{ supplierName(viewing.supplier_id) }} · {{ viewing.receipt_date }} ·
      <StatusBadge :tone="statusTone(viewing.status)">{{
        prettyStatus(viewing.status)
      }}</StatusBadge>
    </p>
    <div class="inv-table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Location</th>
            <th class="num">Delivered</th>
            <th class="num">Accepted</th>
            <th class="num">Rejected</th>
            <th class="num">Net weight</th>
            <th v-if="canSeeCost" class="num">Line cost</th>
            <th>Sell-by</th>
            <th>Batch</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in viewing.lines" :key="l.id">
            <td>{{ productName(l.product_id) }}</td>
            <td>{{ locationCode(l.location_id) }}</td>
            <td class="num">{{ l.delivered_qty }}</td>
            <td class="num">{{ l.accepted_qty }}</td>
            <td class="num">
              {{ l.rejected_qty
              }}<small v-if="l.reject_reason">{{ l.reject_reason }}</small>
            </td>
            <td class="num">{{ l.net_weight ?? "-" }}</td>
            <td v-if="canSeeCost" class="num">{{ money(l.line_cost) }}</td>
            <td>{{ l.sell_by_date || "-" }}</td>
            <td>{{ l.batch_id ? String(l.batch_id).slice(0, 8) : "-" }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="inv-buttons">
      <button class="inv-btn" @click="viewing = null">Close</button>
    </div>
  </Modal>
</template>
