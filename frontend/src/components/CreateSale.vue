<template>
  <div class="d-flex w-100 vh-100">
    
    <!-- වම් පැත්තේ Sidebar එක -->
    <div class="bg-dark text-white p-3 d-flex flex-column" style="width: 250px; min-width: 250px;">
      <h4 class="text-center mb-4 text-success fw-bold">FruitPOS</h4>
      <nav class="nav flex-column">
        <router-link to="/" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Dashboard</router-link>
        <router-link to="/create-sale" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">POS & Checkout</router-link>
        <router-link to="/sales-list" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Sales History</router-link>
        <router-link to="/return-sale" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Returns</router-link>
      </nav>
    </div>

    <!-- මැද භාගයේ භාණ්ඩ (Search එකත් එක්ක) -->
    <div class="flex-grow-1 p-4 bg-light">
      <h3 class="mb-3 fw-bold">Products</h3>
      
      <!-- Search Bar එක -->
      <input 
        v-model="searchQuery" 
        type="text" 
        class="form-control mb-4" 
        placeholder="Search products..." 
        style="max-width: 400px;" 
      />
      
      <div class="d-flex flex-wrap gap-3">
        <!-- ෆිල්ටර් වුණු භාණ්ඩ ලැයිස්තුව -->
        <div 
          v-for="product in filteredProducts" 
          :key="product.name"
          class="card p-4 shadow-sm cursor-pointer" 
          style="width: 180px;" 
          @click="addToCart(product.name, product.price)"
        >
          <h5 class="text-center">{{ product.name }}</h5>
          <p class="text-success text-center fw-bold mb-0">LKR {{ product.price }} / Kg</p>
        </div>
        
        <!-- භාණ්ඩ හම්බුනේ නැත්නම් -->
        <div v-if="filteredProducts.length === 0" class="text-muted mt-3">
          No products found matching "{{ searchQuery }}".
        </div>
      </div>
    </div>

    <!-- දකුණු පැත්තේ බිල (Cart) -->
    <div class="bg-white p-4 border-start shadow-sm d-flex flex-column" style="width: 350px; min-width: 350px;">
      <h4 class="mb-3 border-bottom pb-2 fw-bold">Current Sale</h4>
      
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
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();

// Search Query එක සහ Products ලැයිස්තුව
const searchQuery = ref('');
const products = ref([
  { name: 'Mango', price: 650.00 },
  { name: 'Banana', price: 120.00 }
]);

const generateRequestId = () => 'REQ-' + Math.random().toString(36).substr(2, 9);

const cart = ref([]);
const message = ref('');
const error = ref('');

// බිලට භාණ්ඩ එකතු කරන function එක
const addToCart = (name, price) => {
  cart.value.push({
    name: name,
    product_id: '987e6543-e21b-34d5-a678-426614174000',
    quantity: 1.0,
    price: price,
    total: price * 1.0
  });
};

// මුළු මුදල ගණනය කරන function එක
const totalAmount = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.total, 0);
});

// Search Query එකට අනුව භාණ්ඩ ෆිල්ටර් කරන Function එක
const filteredProducts = computed(() => {
  if (!searchQuery.value) {
    return products.value; // Search Bar එක හිස් නම් ඔක්කොම පෙන්නන්න
  }
  return products.value.filter(p => 
    p.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

// බිල සම්පූර්ණ කරන function එක
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
    
    // සාර්ථක වුණාම රිසිට් එක පෙන්නන තිරයට යන්න
    router.push(`/receipt/${response.data.sale.id}`);
    
    cart.value = []; // Cart එක හිස් කරන්න
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