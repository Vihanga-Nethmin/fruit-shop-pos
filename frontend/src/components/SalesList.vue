<template>
  <div class="d-flex min-vh-100">
    <!-- Sidebar -->
    <div class="bg-dark text-white p-3 d-flex flex-column" style="width: 250px; min-width: 250px;">
      <h4 class="text-center mb-4 text-success">FruitPOS</h4>
      <nav class="nav flex-column">
        <router-link to="/" class="sidebar-link" active-class="active">Dashboard</router-link>
        <router-link to="/create-sale" class="sidebar-link" active-class="active">POS & Checkout</router-link>
        <router-link to="/sales-list" class="sidebar-link" active-class="active">Sales History</router-link>
      </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1 p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Sales History</h2>
        <button class="btn btn-emerald">Export CSV</button>
      </div>

      <div class="table-responsive">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Sale ID</th>
              <th>Sale Number</th>
              <th>Total Amount</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
<tr v-for="sale in sales" :key="sale.id" @click="viewSale(sale.id)" style="cursor: pointer;" class="hover-row">              <td>#{{ sale.id }}</td>
              <td>{{ sale.sale_number }}</td>
              <td class="fw-bold">LKR {{ sale.total_amount }}</td>
              <td><span class="badge bg-success">{{ sale.status }}</span></td>
              <td>{{ new Date(sale.created_at).toLocaleString() }}</td>
            </tr>
            <tr v-if="sales.length === 0">
              <td colspan="5" class="text-center py-4 text-muted">No sales found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router'; // මේක උඩින්ම තියෙන්න ඕනේ

const router = useRouter(); // මේක script එක ඇතුලේ උඩින්ම තියන්න

const sales = ref([]);

// දත්ත ලබා ගන්නා function එක
const fetchSales = async () => {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/sales');
    sales.value = response.data.data;
  } catch (err) {
    console.error(err);
  }
};

// බිලක් උඩ Click කරද්දී වෙන තිරයට යන function එක
const viewSale = (id) => {
  router.push(`/sales/${id}`);
};

onMounted(() => {
  fetchSales();
});
</script>