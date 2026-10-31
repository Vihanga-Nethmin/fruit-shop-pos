<template>
  <div class="container mt-5">
    <!-- Loading State -->
    <div v-if="loading" class="text-center mt-5">
      <div class="spinner-border text-success" role="status"></div>
      <p class="mt-2">Loading Receipt...</p>
    </div>

    <!-- Error State -->
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <!-- Receipt Content -->
    <div v-if="sale && !loading">
      <div class="d-flex justify-content-between mb-3">
        <router-link to="/sales-list" class="btn btn-outline-secondary">Back to List</router-link>
        <button @click="printReceipt" class="btn btn-emerald">Print Receipt</button>
      </div>

      <!-- මේ කොටස තමයි Print වෙන්නේ -->
      <div id="receipt-area" class="card shadow-sm p-4 mx-auto" style="max-width: 400px; background-color: #fff;">
        <div class="text-center mb-3">
          <h4 class="fw-bold mb-0">FruitPOS</h4>
          <p class="small text-muted mb-0">123 Main Street, Colombo</p>
          <p class="small text-muted">Tel: 011-2345678</p>
          <hr />
        </div>

        <div class="d-flex justify-content-between small mb-1">
          <span>Sale No:</span>
          <span>{{ sale.sale_number }}</span>
        </div>
        <div class="d-flex justify-content-between small mb-1">
          <span>Date:</span>
          <span>{{ new Date(sale.created_at).toLocaleString() }}</span>
        </div>
        <div class="d-flex justify-content-between small mb-3">
          <span>Cashier:</span>
          <span>User #{{ sale.user_id }}</span>
        </div>

        <hr />

        <!-- Items Table -->
        <table class="table table-borderless table-sm text-center mb-0">
          <thead>
            <tr class="border-bottom">
              <th class="text-start">Item</th>
              <th>Qty</th>
              <th class="text-end">Price</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in sale.lines" :key="line.id" class="small">
              <td class="text-start">{{ line.product_id.substring(0, 8) }}...</td>
              <td>{{ line.quantity }}</td>
              <td class="text-end">{{ line.line_total }}</td>
            </tr>
          </tbody>
        </table>

        <hr />

        <!-- Totals -->
        <div class="d-flex justify-content-between small mb-1">
          <span>Subtotal:</span>
          <span>LKR {{ sale.total_amount }}</span>
        </div>
        <div class="d-flex justify-content-between small mb-1">
          <span>Tax:</span>
          <span>LKR {{ sale.tax_amount }}</span>
        </div>
        <div class="d-flex justify-content-between small mb-1">
          <span>Discount:</span>
          <span>LKR {{ sale.discount_amount }}</span>
        </div>
        
        <hr />
        
        <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
          <span>Total:</span>
          <span>LKR {{ sale.total_amount }}</span>
        </div>

        <div class="text-center mt-4">
          <p class="small text-muted mb-0">Thank you for shopping with us!</p>
          <p class="small text-muted">Please come again.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const sale = ref(null);
const loading = ref(true);
const error = ref('');

const fetchSaleDetails = async () => {
  const saleId = route.params.id;
  try {
    const response = await axios.get(`http://127.0.0.1:8000/api/sales/${saleId}`);
    sale.value = response.data.data;
  } catch (err) {
    console.error(err);
    error.value = 'Failed to load receipt. Sale not found.';
  } finally {
    loading.value = false;
  }
};

// Print කරන function එක
const printReceipt = () => {
  // මේකෙන් බ්‍රව්සර් එකේ ප්‍රින්ට් ඩයලොග් එක ඕපන් වෙනවා
  window.print();
};

onMounted(() => {
  fetchSaleDetails();
});
</script>

<style>
/* ප්‍රින්ට් කරද්දී අනිත් දේවල් (buttons, sidebar) හංගන්න */
@media print {
  body * {
    visibility: hidden;
  }
  #receipt-area, #receipt-area * {
    visibility: visible;
  }
  #receipt-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    box-shadow: none !important;
  }
}
</style>