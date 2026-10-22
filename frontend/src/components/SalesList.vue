<template>
  <div style="padding: 20px; max-width: 800px; margin: 0 auto; font-family: sans-serif;">
    <h2>All Sales (POS)</h2>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; text-align: left;">
      <thead>
        <tr style="background-color: #f2f2f2;">
          <th>Sale ID</th>
          <th>Sale Number</th>
          <th>Total Amount</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="sale in sales" :key="sale.id">
          <td>{{ sale.id }}</td>
          <td>{{ sale.sale_number }}</td>
          <td>LKR {{ sale.total_amount }}</td>
          <td>{{ sale.status }}</td>
          <td>{{ new Date(sale.created_at).toLocaleString() }}</td>
        </tr>
        <tr v-if="sales.length === 0">
          <td colspan="5" style="text-align: center;">No sales found.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const sales = ref([]);
const error = ref('');

const fetchSales = async () => {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/sales');
    sales.value = response.data.data; // අපේ API එකෙන් data array එක ගන්නවා
  } catch (err) {
    console.error(err);
    error.value = 'Failed to load sales. Check if backend is running.';
  }
};

// පිටුව load වෙද්දීම API එකට කෝල් කරන්න
onMounted(() => {
  fetchSales();
});
</script>