<template>
  <div class="d-flex min-vh-100">
    <!-- Sidebar -->
    <div class="bg-dark text-white p-3 d-flex flex-column" style="width: 250px; min-width: 250px;">
      <h4 class="text-center mb-4 text-success fw-bold">FruitPOS</h4>
      <nav class="nav flex-column">
        <router-link to="/" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Dashboard</router-link>
        <router-link to="/create-sale" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">POS & Checkout</router-link>
        <router-link to="/sales-list" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Sales History</router-link>
      </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1 p-4" style="background-color: #f4f6f9;">
      <!-- Loading State -->
      <div v-if="loading" class="text-center mt-5">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2">Loading Sale Details...</p>
      </div>

      <!-- Error State -->
      <div v-if="error" class="alert alert-danger">{{ error }}</div>

      <!-- Sale Details -->
      <div v-if="sale && !loading">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="fw-bold">Sale Details: {{ sale.sale_number }}</h2>
          <router-link to="/sales-list" class="btn btn-outline-secondary">Back to List</router-link>
        </div>

        <div class="row">
          <!-- Left Column: Items -->
          <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-header bg-white fw-bold py-3">Items Purchased</div>
              <div class="card-body p-0">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Product ID</th>
                      <th>Quantity (Kg)</th>
                      <th>Unit Price (LKR)</th>
                      <th>Total (LKR)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="line in sale.lines" :key="line.id">
                      <td class="text-muted small">{{ line.product_id }}</td>
                      <td>{{ line.quantity }}</td>
                      <td>{{ line.unit_price }}</td>
                      <td class="fw-bold">{{ line.line_total }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Right Column: Summary & Payment -->
          <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-header bg-white fw-bold py-3">Summary</div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Status:</span>
                  <span class="badge bg-success">{{ sale.status }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Date:</span>
                  <span>{{ new Date(sale.created_at).toLocaleString() }}</span>
                </div>
                <hr />
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Subtotal:</span>
                  <span>LKR {{ sale.total_amount }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Tax:</span>
                  <span>LKR {{ sale.tax_amount }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Discount:</span>
                  <span>LKR {{ sale.discount_amount }}</span>
                </div>
                <hr />
                <div class="d-flex justify-content-between fs-4 fw-bold text-success">
                  <span>Total:</span>
                  <span>LKR {{ sale.total_amount }}</span>
                </div>
              </div>
            </div>

            <div class="card shadow-sm border-0">
              <div class="card-header bg-white fw-bold py-3">Payment Info</div>
              <div class="card-body">
                <div v-if="sale.payments && sale.payments.length > 0">
                  <div v-for="payment in sale.payments" :key="payment.id" class="mb-2">
                    <p class="mb-1"><strong>Method:</strong> {{ payment.method.toUpperCase() }}</p>
                    <p class="mb-0"><strong>Amount:</strong> LKR {{ payment.amount }}</p>
                  </div>
                </div>
                <p v-else class="text-muted mb-0">No payment info available.</p>
              </div>
            </div>
          </div>
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
    // අපේ අලුත් GET /api/sales/{id} API එකට කෝල් කරනවා
    const response = await axios.get(`http://127.0.0.1:8000/api/sales/${saleId}`);
    sale.value = response.data.data;
  } catch (err) {
    console.error(err);
    error.value = 'Failed to load sale details. Sale not found.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchSaleDetails();
});
</script>