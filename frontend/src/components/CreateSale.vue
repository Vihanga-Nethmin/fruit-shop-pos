<template>
  <div class="d-flex w-100 vh-100"> <!-- මේකෙන් තිරය පුරාම පැතිරෙනවා -->
    
          <!-- වම් පැත්තේ Sidebar එක -->
      <div class="bg-dark text-white p-3 d-flex flex-column" style="width: 250px; min-width: 250px;">
        <h4 class="text-center mb-4 text-success">FruitPOS</h4>
        <nav class="nav flex-column">
          <router-link to="/" class="sidebar-link" active-class="active">Dashboard</router-link>
          <router-link to="/create-sale" class="sidebar-link" active-class="active">POS & Checkout</router-link>
          <router-link to="/sales-list" class="sidebar-link" active-class="active">Sales History</router-link>
        </nav>
      </div>

    <!-- මැද භාගයේ භාණ්ඩ -->
    <div class="flex-grow-1 p-4 bg-light">
      <h3 class="mb-3">Products</h3>
      <input type="text" class="form-control mb-4" placeholder="Search products..." style="max-width: 400px;" />
      
      <div class="d-flex flex-wrap gap-3">
        <div class="card p-4 shadow-sm cursor-pointer" style="width: 180px;" @click="addToCart('Mango', 650.00)">
          <h5 class="text-center">Mango</h5>
          <p class="text-success text-center fw-bold mb-0">LKR 650.00 / Kg</p>
        </div>
        
        <div class="card p-4 shadow-sm cursor-pointer" style="width: 180px;" @click="addToCart('Banana', 120.00)">
          <h5 class="text-center">Banana</h5>
          <p class="text-success text-center fw-bold mb-0">LKR 120.00 / Kg</p>
        </div>
      </div>
    </div>

    <!-- දකුණු පැත්තේ බිල (Cart) -->
    <div class="bg-white p-4 border-start shadow-sm d-flex flex-column" style="width: 350px; min-width: 350px;">
      <h4 class="mb-3 border-bottom pb-2">Current Sale</h4>
      
      <div class="flex-grow-1 overflow-auto">
        <div v-if="cart.length === 0" class="text-muted text-center mt-5">
          No items added yet.
        </div>

        <div v-for="(item, index) in cart" :key="index" class="d-flex justify-content-between mb-3 align-items-center border-bottom pb-2">
          <div>
            <h6 class="mb-0 fw-bold">{{ item.name }}</h6>
            <small class="text-muted">{{ item.quantity }} kg x LKR {{ item.price }}</small>
          </div>
          <div class="fw-bold">LKR {{ item.total }}</div>
        </div>
      </div>

      <div class="mt-auto border-top pt-3">
        <div class="d-flex justify-content-between fs-4 fw-bold mb-3">
          <span>Total:</span>
          <span>LKR {{ totalAmount }}</span>
        </div>

        <button @click="createSale" class="btn btn-success w-100 py-2 fs-5">Complete Sale</button>
        
        <div v-if="message" class="alert alert-success mt-3 mb-0">{{ message }}</div>
        <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const generateRequestId = () => 'REQ-' + Math.random().toString(36).substr(2, 9);

const cart = ref([]);
const message = ref('');
const error = ref('');

const addToCart = (name, price) => {
  cart.value.push({
    name: name,
    product_id: '987e6543-e21b-34d5-a678-426614174000',
    quantity: 1.0,
    price: price,
    total: price * 1.0
  });
};

const totalAmount = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.total, 0);
});

const createSale = async () => {
  if (cart.value.length === 0) {
    error.value = "Please add items to the cart first.";
    return;
  }
  
  message.value = '';
  error.value = '';

  const salePayload = {
    tenant_id: '123e4567-e89b-12d3-a456-426614174000',
    user_id: 1,
    items: cart.value.map(item => ({
      product_id: item.product_id,
      quantity: item.quantity,
      unit_price: item.price
    })),
    payment_method: 'cash',
    payment_amount: totalAmount.value,
    request_id: generateRequestId()
  };

  try {
    const response = await axios.post('http://127.0.0.1:8000/api/sales', salePayload);
    message.value = response.data.message + ' (Sale ID: ' + response.data.sale.id + ')';
    cart.value = [];
  } catch (err) {
    console.error(err);
    error.value = err.response?.data?.message || 'Failed to create sale.';
  }
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
  transition: transform 0.2s;
}
.cursor-pointer:hover {
  background-color: #f8f9fa;
  transform: scale(1.02);
}
</style>