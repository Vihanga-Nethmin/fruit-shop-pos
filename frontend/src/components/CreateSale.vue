<template>
  <div style="padding: 20px; max-width: 400px; margin: 0 auto; font-family: sans-serif;">
    <h2>New Sale (POS)</h2>
    
    <div style="margin-bottom: 10px;">
      <label>Tenant ID: </label>
      <input v-model="saleData.tenant_id" type="text" style="width: 100%; padding: 5px;" />
    </div>
    <div style="margin-bottom: 10px;">
      <label>User ID: </label>
      <input v-model="saleData.user_id" type="number" style="width: 100%; padding: 5px;" />
    </div>
    <div style="margin-bottom: 10px;">
      <label>Product ID: </label>
      <input v-model="saleData.items[0].product_id" type="text" style="width: 100%; padding: 5px;" />
    </div>
    <div style="margin-bottom: 10px;">
      <label>Quantity (kg): </label>
      <input v-model="saleData.items[0].quantity" type="number" step="0.001" style="width: 100%; padding: 5px;" />
    </div>
    <div style="margin-bottom: 10px;">
      <label>Unit Price: </label>
      <input v-model="saleData.items[0].unit_price" type="number" step="0.01" style="width: 100%; padding: 5px;" />
    </div>
    <div style="margin-bottom: 10px;">
      <label>Payment Amount: </label>
      <input v-model="saleData.payment_amount" type="number" step="0.01" style="width: 100%; padding: 5px;" />
    </div>

    <button @click="createSale" style="padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer; width: 100%;">Complete Sale</button>

    <p v-if="message" style="color: green; font-weight: bold; margin-top: 15px;">{{ message }}</p>
    <p v-if="error" style="color: red; font-weight: bold; margin-top: 15px;">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

// Idempotency සඳහා අහඹු Request ID එකක් හදාගන්න
const generateRequestId = () => 'REQ-' + Math.random().toString(36).substr(2, 9);

const saleData = ref({
  tenant_id: '123e4567-e89b-12d3-a456-426614174000',
  user_id: 1,
  items: [
    {
      product_id: '987e6543-e21b-34d5-a678-426614174000',
      quantity: 1.250,
      unit_price: 650.00
    }
  ],
  payment_method: 'cash',
  payment_amount: 812.50,
  request_id: generateRequestId()
});

const message = ref('');
const error = ref('');

const createSale = async () => {
  message.value = '';
  error.value = '';
  
  try {
    // අපේ Laravel API එකට POST කරනවා
    const response = await axios.post('http://127.0.0.1:8000/api/sales', saleData.value);
    message.value = response.data.message + ' (Sale ID: ' + response.data.sale.id + ')';
    
    // අලුත් බිලක් හැදුවට පස්සේ අලුත් Request ID එකක් හදාගන්න
    saleData.value.request_id = generateRequestId();
    
  } catch (err) {
    console.error(err);
    error.value = err.response?.data?.message || 'Failed to create sale. Check console.';
  }
};
</script>