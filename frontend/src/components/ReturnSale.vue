<template>
  <div class="d-flex min-vh-100">
    <!-- Sidebar -->
    <div class="bg-dark text-white p-3 d-flex flex-column" style="width: 250px; min-width: 250px;">
      <h4 class="text-center mb-4 text-success fw-bold">FruitPOS</h4>
      <nav class="nav flex-column">
        <router-link to="/" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Dashboard</router-link>
        <router-link to="/create-sale" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">POS & Checkout</router-link>
        <router-link to="/sales-list" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Sales History</router-link>
        <router-link to="/return-sale" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Returns</router-link>
      </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1 p-4" style="background-color: #f4f6f9;">
      <h2 class="mb-4 fw-bold">Process Return</h2>
      
      <!-- Search Sale -->
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
          <label class="form-label fw-bold">Enter Sale ID to Return</label>
          <div class="input-group">
            <input v-model="searchSaleId" type="number" class="form-control" placeholder="e.g. 1" />
            <button @click="fetchSale" class="btn btn-emerald">Find Sale</button>
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center mt-5">
        <div class="spinner-border text-success" role="status"></div>
      </div>

      <!-- Error -->
      <div v-if="error" class="alert alert-danger">{{ error }}</div>

      <!-- Sale Details & Return Form -->
      <div v-if="sale">
        <div class="alert alert-info">
          Found Sale: <strong>{{ sale.sale_number }}</strong> (Date: {{ new Date(sale.created_at).toLocaleString() }})
        </div>

        <div class="row">
          <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-header bg-white fw-bold py-3">Select Items to Return</div>
              <div class="card-body p-0">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Product ID</th>
                      <th>Original Qty</th>
                      <th>Return Qty</th>
                      <th>Unit Price (LKR)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="line in sale.lines" :key="line.id">
                      <td class="text-muted small">{{ line.product_id }}</td>
                      <td>{{ line.quantity }}</td>
                      <td>
                        <input 
                          v-model="returnQuantities[line.id]" 
                          type="number" 
                          step="0.001" 
                          min="0" 
                          :max="line.quantity" 
                          class="form-control form-control-sm" 
                          placeholder="0"
                        />
                      </td>
                      <td>{{ line.unit_price }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card shadow-sm border-0">
              <div class="card-header bg-white fw-bold py-3">Return Summary</div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                  <span class="text-muted">Total Refund Amount:</span>
                  <span class="fs-4 fw-bold text-success">LKR {{ totalRefund }}</span>
                </div>
                
                <button @click="processReturn" class="btn btn-danger w-100 py-2 fs-5">Process Refund</button>
                
                <div v-if="successMessage" class="alert alert-success mt-3 mb-0">{{ successMessage }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const searchSaleId = ref('');
const sale = ref(null);
const loading = ref(false);
const error = ref('');
const successMessage = ref('');
const returnQuantities = ref({});

// Sale එකක් හොයාගන්න function එක
const fetchSale = async () => {
  if (!searchSaleId.value) return;
  
  loading.value = true;
  error.value = '';
  sale.value = null;
  successMessage.value = '';

  try {
    const response = await axios.get(`http://127.0.0.1:8000/api/sales/${searchSaleId.value}`);
    sale.value = response.data.data;
    
    // අලුත් Sale එකක් හම්බුනාම Return Quantities හිස් කරන්න
    returnQuantities.value = {};
    sale.value.lines.forEach(line => {
      returnQuantities.value[line.id] = 0;
    });
    
  } catch (err) {
    error.value = 'Sale not found. Please check the ID.';
  } finally {
    loading.value = false;
  }
};

// මුළු Refund එක ගණනය කරන function එක
const totalRefund = computed(() => {
  if (!sale.value) return '0.00';
  
  let total = 0;
  sale.value.lines.forEach(line => {
    const qty = parseFloat(returnQuantities.value[line.id]) || 0;
    total += (qty * line.unit_price);
  });
  return total.toFixed(2);
});

// Return එක Process කරන function එක
const processReturn = async () => {
  error.value = '';
  successMessage.value = '';

  // අපි යවන්න ඕන Items ලැයිස්තුව හදාගන්නවා (ආපහු ගන්න ඒවා විතරක්)
  const itemsToReturn = [];
  sale.value.lines.forEach(line => {
    const qty = parseFloat(returnQuantities.value[line.id]) || 0;
    if (qty > 0) {
      itemsToReturn.push({
        sale_line_id: line.id,
        quantity: qty
      });
    }
  });

  if (itemsToReturn.length === 0) {
    error.value = 'Please enter a quantity to return.';
    return;
  }

  try {
    const payload = {
      items: itemsToReturn,
      payment_method: 'cash',
      reason: 'Customer Return'
    };

    const response = await axios.post(`http://127.0.0.1:8000/api/sales/${sale.value.id}/returns`, payload);
    successMessage.value = response.data.message + ' (Refunded: LKR ' + response.data.data.total_refund_amount + ')';
    
    // සාර්ථක වුණාම inputs හිස් කරන්න
    sale.value.lines.forEach(line => {
      returnQuantities.value[line.id] = 0;
    });

  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to process return.';
  }
};
</script>