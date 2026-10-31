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
        <router-link to="/shift" class="sidebar-link text-white text-decoration-none p-2 mb-2 rounded">Shift Management</router-link>
      </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1 p-4" style="background-color: #f4f6f9;">
      <h2 class="mb-4 fw-bold">Shift Management</h2>

      <div class="row">
        <!-- Open Shift Section -->
        <div class="col-md-6 mb-4" v-if="!currentShift">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold py-3">Open New Shift</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Opening Float (LKR)</label>
                <input v-model="openingFloat" type="number" min="0" class="form-control" placeholder="Enter starting cash" />
              </div>
              <button @click="openShift" class="btn btn-emerald w-100">Open Shift</button>
            </div>
          </div>
        </div>

        <!-- Close Shift Section -->
        <div class="col-md-6 mb-4" v-if="currentShift">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold py-3 text-success">Active Shift</div>
            <div class="card-body">
              <p><strong>Opened At:</strong> {{ new Date(currentShift.opened_at).toLocaleString() }}</p>
              <p><strong>Opening Float:</strong> LKR {{ currentShift.opening_float }}</p>
              
              <hr />
              
              <div class="mb-3">
                <label class="form-label">Closing Cash (LKR)</label>
                <input v-model="closingCash" type="number" min="0" class="form-control" placeholder="Enter counted cash" />
              </div>
              <button @click="closeShift" class="btn btn-danger w-100">Close Shift</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Messages -->
      <div v-if="message" class="alert alert-success mt-3">{{ message }}</div>
      <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>

      <!-- Shift History -->
      <div class="mt-5" v-if="shifts.length > 0">
        <h4 class="mb-3 fw-bold">Shift History</h4>
        <div class="table-responsive">
          <table class="table table-bordered table-striped bg-white">
            <thead class="table-dark">
              <tr>
                <th>Shift ID</th>
                <th>Opened At</th>
                <th>Closed At</th>
                <th>Opening Float</th>
                <th>Expected Cash</th>
                <th>Closing Cash</th>
                <th>Variance</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="shift in shifts" :key="shift.id">
                <td>#{{ shift.id }}</td>
                <td>{{ new Date(shift.opened_at).toLocaleString() }}</td>
                <td>{{ shift.closed_at ? new Date(shift.closed_at).toLocaleString() : '-' }}</td>
                <td>LKR {{ shift.opening_float }}</td>
                <td>{{ shift.expected_cash ? 'LKR ' + shift.expected_cash : '-' }}</td>
                <td>{{ shift.closing_cash ? 'LKR ' + shift.closing_cash : '-' }}</td>
                <td :class="shift.variance < 0 ? 'text-danger' : (shift.variance > 0 ? 'text-success' : '')">
                  {{ shift.variance !== null ? 'LKR ' + shift.variance : '-' }}
                </td>
                <td>
                  <span class="badge" :class="shift.status === 'open' ? 'bg-success' : 'bg-secondary'">
                    {{ shift.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const shifts = ref([]);
const currentShift = ref(null);
const openingFloat = ref(0);
const closingCash = ref(0);
const message = ref('');
const error = ref('');

// දැනට තියෙන ෂිෆ්ට් එක ගන්න
const fetchShifts = async () => {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/shifts');
    shifts.value = response.data.data;
    
    // අපි හිතමු user_id 1 ට open ෂිෆ්ට් එකක් තියෙනවද කියලා
    const openShift = shifts.value.find(s => s.status === 'open' && s.user_id === 1);
    if (openShift) {
      currentShift.value = openShift;
    } else {
      currentShift.value = null;
    }
  } catch (err) {
    console.error(err);
  }
};

// ෂිෆ්ට් එක පටන් ගන්න
const openShift = async () => {
  message.value = '';
  error.value = '';
  try {
    const response = await axios.post('http://127.0.0.1:8000/api/shifts/open', {
      user_id: 1, // දැනට hardcode කරලා
      opening_float: openingFloat.value
    });
    message.value = response.data.message;
    openingFloat.value = 0;
    fetchShifts(); // ලැයිස්තුව refresh කරන්න
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to open shift.';
  }
};

// ෂිෆ්ට් එක අවසන් කරන්න
const closeShift = async () => {
  message.value = '';
  error.value = '';
  try {
    const response = await axios.post(`http://127.0.0.1:8000/api/shifts/${currentShift.value.id}/close`, {
      closing_cash: closingCash.value
    });
    message.value = response.data.message;
    closingCash.value = 0;
    fetchShifts(); // ලැයිස්තුව refresh කරන්න
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to close shift.';
  }
};

onMounted(() => {
  fetchShifts();
});
</script>