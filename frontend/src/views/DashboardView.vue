<template>
  <div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="brand">
        <Apple :size="29" />
        <span>Fruit Shop POS</span>
      </div>

      <p class="menu-label">MAIN MENU</p>

      <nav class="menu">
        <button
          v-for="item in menuItems"
          :key="item.name"
          :class="['menu-item', { active: activeMenu === item.name }]"
          @click="activeMenu = item.name"
        >
          <component :is="item.icon" :size="19" />
          <span>{{ item.name }}</span>
        </button>
      </nav>

      <div class="sidebar-bottom">
        <div class="user-avatar">
          <UserRound :size="22" />
        </div>

        <div class="user-info">
          <strong>Store Manager</strong>
          <span>Administrator</span>
        </div>

        <button
          class="logout-button"
          title="Log out"
          aria-label="Log out"
          @click="logout"
        >
          <LogOut :size="19" />
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
      <header class="topbar">
        <div>
          <p class="breadcrumb">Pages / Dashboard</p>
          <h1>{{ activeMenu }}</h1>
        </div>

        <div class="topbar-right">
          <div class="status">
            <span class="status-dot"></span>
            Frontend Preview
          </div>

          <div class="profile-icon">
            <UserRound :size="21" />
          </div>
        </div>
      </header>

      <section class="welcome-section">
        <div>
          <p class="welcome-label">WELCOME BACK!</p>
          <h2>Good to see you again 👋</h2>
          <p>
            Here's an overview of your fruit shop today.
          </p>
        </div>

        <div class="welcome-icon">
          <Apple :size="48" />
        </div>
      </section>

      <!-- Summary cards -->
      <section class="stats-grid">
        <article
          v-for="stat in stats"
          :key="stat.title"
          class="stat-card"
        >
          <div class="stat-top">
            <span class="stat-icon" :class="stat.color">
              <component :is="stat.icon" :size="23" />
            </span>

            <span class="stat-period">Today</span>
          </div>

          <p class="stat-title">{{ stat.title }}</p>
          <h3>{{ stat.value }}</h3>
          <p class="stat-description">{{ stat.description }}</p>
        </article>
      </section>

      <!-- Lower dashboard -->
      <section class="content-grid">
        <article class="panel recent-panel">
          <div class="panel-heading">
            <div>
              <h3>Recent Sales</h3>
              <p>Latest transactions in your store</p>
            </div>

            <button class="text-button" @click="activeMenu = 'Sales'">
              View sales
              <ArrowRight :size="16" />
            </button>
          </div>

          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Time</th>
                  <th>Amount</th>
                  <th>Status</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="sale in recentSales" :key="sale.id">
                  <td class="order-id">{{ sale.id }}</td>
                  <td>{{ sale.time }}</td>
                  <td class="amount">{{ sale.amount }}</td>
                  <td>
                    <span class="sale-status">{{ sale.status }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        <article class="panel stock-panel">
          <div class="panel-heading">
            <div>
              <h3>Stock Overview</h3>
              <p>Inventory items needing attention</p>
            </div>

            <Package :size="21" class="heading-icon" />
          </div>

          <div
            v-for="product in lowStockProducts"
            :key="product.name"
            class="stock-item"
          >
            <div class="product-icon">
              <Apple :size="19" />
            </div>

            <div class="stock-details">
              <strong>{{ product.name }}</strong>
              <span>{{ product.quantity }} units remaining</span>

              <div class="stock-bar">
                <div
                  class="stock-progress"
                  :style="{ width: product.percentage + '%' }"
                ></div>
              </div>
            </div>

            <span class="low-label">Low</span>
          </div>

          <button
            class="inventory-button"
            @click="activeMenu = 'Inventory'"
          >
            Manage inventory
            <ArrowRight :size="16" />
          </button>
        </article>
      </section>

      <footer class="footer">
        <span>Fruit Shop POS</span>
        <span>Dashboard preview · Sample data</span>
      </footer>
    </main>
  </div>
</template>

<script setup>
import { ref, markRaw } from 'vue'
import { useRouter } from 'vue-router'

import {
  Apple,
  LayoutDashboard,
  ShoppingCart,
  Package,
  ChartNoAxesCombined,
  ClipboardList,
  Settings,
  UserRound,
  LogOut,
  ArrowRight,
  Banknote,
  Boxes,
  TrendingUp
} from 'lucide-vue-next'

const router = useRouter()

const activeMenu = ref('Dashboard')

const menuItems = [
  { name: 'Dashboard', icon: markRaw(LayoutDashboard) },
  { name: 'Sales', icon: markRaw(ShoppingCart) },
  { name: 'Inventory', icon: markRaw(Package) },
  { name: 'Products', icon: markRaw(Boxes) },
  { name: 'Reports', icon: markRaw(ChartNoAxesCombined) },
  { name: 'Transactions', icon: markRaw(ClipboardList) },
  { name: 'Settings', icon: markRaw(Settings) }
]

const stats = [
  {
    title: 'Total Sales',
    value: 'LKR 45,280',
    description: 'Sales recorded today',
    icon: markRaw(Banknote),
    color: 'green'
  },
  {
    title: 'Total Orders',
    value: '128',
    description: 'Orders processed today',
    icon: markRaw(ShoppingCart),
    color: 'blue'
  },
  {
    title: 'Products',
    value: '86',
    description: 'Products in catalogue',
    icon: markRaw(Package),
    color: 'orange'
  },
  {
    title: 'Sales Growth',
    value: '+12.5%',
    description: 'Sample performance figure',
    icon: markRaw(TrendingUp),
    color: 'purple'
  }
]

const recentSales = [
  { id: '#ORD-1001', time: '09:15 AM', amount: 'LKR 2,450', status: 'Completed' },
  { id: '#ORD-1002', time: '09:42 AM', amount: 'LKR 1,850', status: 'Completed' },
  { id: '#ORD-1003', time: '10:05 AM', amount: 'LKR 3,200', status: 'Completed' },
  { id: '#ORD-1004', time: '10:28 AM', amount: 'LKR 1,250', status: 'Completed' }
]

const lowStockProducts = [
  { name: 'Apples', quantity: 8, percentage: 20 },
  { name: 'Oranges', quantity: 12, percentage: 35 },
  { name: 'Bananas', quantity: 6, percentage: 15 }
]

function logout() {
  router.push('/login')
}
</script>

<style scoped>
* {
  box-sizing: border-box;
}

.dashboard {
  display: flex;
  min-height: 100vh;
  background: #f6f8f6;
  color: #26372b;
  font-family: Inter, Arial, sans-serif;
}

.sidebar {
  position: sticky;
  top: 0;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  width: 250px;
  height: 100vh;
  padding: 28px 18px 18px;
  background: #173f2c;
  color: #fff;
}

.brand {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 0 7px;
  color: #d5eead;
  font-size: 18px;
  font-weight: 700;
}

.menu-label {
  margin: 48px 10px 15px;
  color: #9eb9a5;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.5px;
}

.menu {
  display: grid;
  gap: 7px;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 13px;
  width: 100%;
  padding: 13px 12px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #d2dfd5;
  font-family: inherit;
  font-size: 13px;
  text-align: left;
  cursor: pointer;
}

.menu-item:hover,
.menu-item.active {
  background: #ffffff17;
  color: #d5eead;
}

.sidebar-bottom {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: auto;
  padding: 17px 3px 0;
  border-top: 1px solid #ffffff20;
}

.user-avatar,
.profile-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 39px;
  height: 39px;
  border-radius: 50%;
  background: #e1efda;
  color: #28633d;
}

.user-info {
  display: grid;
  flex: 1;
  gap: 4px;
  min-width: 0;
}

.user-info strong {
  font-size: 12px;
}

.user-info span {
  color: #b7cbbd;
  font-size: 11px;
}

.logout-button {
  display: grid;
  place-items: center;
  padding: 7px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #d2dfd5;
  cursor: pointer;
}

.logout-button:hover {
  background: #ffffff17;
}

.main-content {
  flex: 1;
  min-width: 0;
  padding: 30px;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
}

.breadcrumb {
  margin: 0 0 7px;
  color: #89958c;
  font-size: 12px;
}

.topbar h1 {
  margin: 0;
  font-size: 25px;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 18px;
}

.status {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border: 1px solid #e3e9e3;
  border-radius: 20px;
  background: #fff;
  color: #66766a;
  font-size: 11px;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #76b95c;
}

.welcome-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 27px 30px;
  border-radius: 14px;
  background: linear-gradient(110deg, #e7f2df, #f3f8ee);
}

.welcome-label {
  margin: 0 0 9px;
  color: #528044;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.5px;
}

.welcome-section h2 {
  margin: 0 0 9px;
  font-size: clamp(19px, 2vw, 25px);
}

.welcome-section p:last-child {
  margin: 0;
  color: #748176;
  font-size: 13px;
}

.welcome-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 85px;
  height: 85px;
  border-radius: 20px;
  background: #ffffffa8;
  color: #478044;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 17px;
  margin-top: 24px;
}

.stat-card,
.panel {
  min-width: 0;
  border: 1px solid #e9ede9;
  border-radius: 12px;
  background: #fff;
}

.stat-card {
  padding: 19px;
}

.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.stat-icon {
  display: grid;
  place-items: center;
  width: 43px;
  height: 43px;
  border-radius: 11px;
}

.stat-icon.green {
  background: #e7f4e5;
  color: #39804b;
}

.stat-icon.blue {
  background: #e8f1ff;
  color: #467bc1;
}

.stat-icon.orange {
  background: #fff0df;
  color: #c9822e;
}

.stat-icon.purple {
  background: #f1eaff;
  color: #8563c7;
}

.stat-period {
  color: #89958c;
  font-size: 10px;
}

.stat-title {
  margin: 20px 0 7px;
  color: #7b887e;
  font-size: 12px;
}

.stat-card h3 {
  margin: 0;
  font-size: clamp(17px, 1.7vw, 23px);
  overflow-wrap: anywhere;
}

.stat-description {
  margin: 10px 0 0;
  color: #89958c;
  font-size: 10px;
}

.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(260px, 1fr);
  gap: 20px;
  margin-top: 24px;
}

.panel {
  padding: 22px;
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 22px;
}

.panel-heading h3 {
  margin: 0 0 7px;
  font-size: 16px;
}

.panel-heading p {
  margin: 0;
  color: #89958c;
  font-size: 11px;
}

.text-button,
.inventory-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border: 0;
  background: transparent;
  color: #347346;
  font-family: inherit;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  white-space: nowrap;
}

th {
  padding: 12px 9px;
  border-bottom: 1px solid #edf0ed;
  color: #89958c;
  font-size: 10px;
  font-weight: 600;
}

td {
  padding: 15px 9px;
  border-bottom: 1px solid #f0f2f0;
  color: #69766c;
  font-size: 11px;
}

tbody tr:last-child td {
  border-bottom: 0;
}

.order-id {
  color: #344d39;
  font-weight: 600;
}

.amount {
  color: #26372b;
  font-weight: 600;
}

.sale-status {
  display: inline-block;
  padding: 5px 8px;
  border-radius: 5px;
  background: #eaf6e8;
  color: #438047;
  font-size: 10px;
}

.heading-icon {
  color: #4e8554;
}

.stock-item {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 22px;
}

.product-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 38px;
  height: 38px;
  border-radius: 9px;
  background: #edf5e9;
  color: #4e8554;
}

.stock-details {
  flex: 1;
  min-width: 0;
}

.stock-details strong,
.stock-details span {
  display: block;
}

.stock-details strong {
  margin-bottom: 5px;
  font-size: 12px;
}

.stock-details span {
  color: #89958c;
  font-size: 10px;
}

.stock-bar {
  height: 5px;
  margin-top: 9px;
  overflow: hidden;
  border-radius: 5px;
  background: #edf0ed;
}

.stock-progress {
  height: 100%;
  border-radius: 5px;
  background: #e0a34d;
}

.low-label {
  align-self: flex-start;
  padding: 5px 7px;
  border-radius: 5px;
  background: #fff0df;
  color: #b77a2d;
  font-size: 9px;
}

.inventory-button {
  width: 100%;
  margin-top: 7px;
  padding: 12px;
  border: 1px solid #dfe9dd;
  border-radius: 8px;
}

.inventory-button:hover {
  background: #f5f9f3;
}

.footer {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-top: 28px;
  color: #89958c;
  font-size: 10px;
}

@media (max-width: 1100px) {
  .sidebar {
    width: 215px;
  }

  .main-content {
    padding: 24px;
  }

  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .content-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 700px) {
  .sidebar {
    width: 65px;
    padding: 22px 8px 14px;
  }

  .brand {
    justify-content: center;
    padding: 0;
  }

  .brand span,
  .menu-label,
  .menu-item span,
  .user-info,
  .logout-button {
    display: none;
  }

  .menu {
    margin-top: 35px;
  }

  .menu-item {
    justify-content: center;
    padding: 13px 5px;
  }

  .sidebar-bottom {
    justify-content: center;
    padding-top: 15px;
  }

  .main-content {
    padding: 20px 14px;
  }

  .topbar h1 {
    font-size: 21px;
  }

  .status {
    padding: 8px;
    font-size: 0;
  }

  .status-dot {
    width: 9px;
    height: 9px;
  }

  .topbar-right {
    gap: 9px;
  }

  .profile-icon {
    width: 35px;
    height: 35px;
  }

  .welcome-section {
    padding: 22px 18px;
  }

  .welcome-icon {
    width: 55px;
    height: 55px;
    border-radius: 14px;
  }

  .welcome-icon svg {
    width: 32px;
    height: 32px;
  }

  .stats-grid {
    gap: 12px;
    margin-top: 16px;
  }

  .stat-card {
    padding: 14px;
  }

  .stat-card h3 {
    font-size: 17px;
  }

  .panel {
    padding: 16px 12px;
  }

  .footer {
    flex-direction: column;
  }
}
</style>