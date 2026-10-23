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
      <h2 class="mb-4 fw-bold">Dashboard</h2>
      
      <!-- මේ පේළිය තමයි වැදගත්. අපි col-12 (mobile) සහ col-md-4 (desktop) පාවිච්චි කරනවා -->
      <div class="row g-4">
        <div class="col-12 col-md-4">
          <div class="card p-4 shadow-sm border-0 bg-white">
            <h6 class="text-muted mb-2">Total Sales Today</h6>
            <h3 class="fw-bold text-success mb-0">LKR 15,500.00</h3>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card p-4 shadow-sm border-0 bg-white">
            <h6 class="text-muted mb-2">Total Items Sold</h6>
            <h3 class="fw-bold text-primary mb-0">125 Kg</h3>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card p-4 shadow-sm border-0 bg-white">
            <h6 class="text-muted mb-2">Active Cashier</h6>
            <h3 class="fw-bold text-dark mb-0">Aloka</h3>
          </div>
        </div>
      </div>

      <!-- Recent Sales Table -->
      <div class="mt-5">
        <h4 class="mb-3 fw-bold">Recent Sales</h4>
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead class="table-success">
              <tr>
                <th>Sale Number</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>SALE-5</td>
                <td>LKR 890.00</td>
                <td><span class="badge bg-success">completed</span></td>
                <td>2026-10-23 02:10</td>
              </tr>
              <tr>
                <td>SALE-4</td>
                <td>LKR 1540.00</td>
                <td><span class="badge bg-success">completed</span></td>
                <td>2026-10-23 02:05</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// මේ පිටුවට දැනට විශේෂ කේතයක් අවශ්‍ය නැහැ
</script>