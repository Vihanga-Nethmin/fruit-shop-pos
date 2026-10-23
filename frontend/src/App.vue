<template>
  <div>
    <router-view />
  </div>
</template>

<style>
/* මේක තමයි අපේ පද්ධතියේ ප්‍රධාන තේමාව */
body {
  margin: 0;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f4f6f9;
  color: #333;
}

/* ලස්සන කොළ පාට බොත්තම් */
.btn-emerald {
  background-color: #2e7d32;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.2s;
}
.btn-emerald:hover {
  background-color: #1b5e20;
  color: white;
}

/* Navigation Bar එකේ ලින්ක්ස් */
.sidebar-link {
  color: #d1d5db;
  text-decoration: none;
  padding: 12px 15px;
  display: block;
  border-radius: 6px;
  margin-bottom: 5px;
  font-weight: 500;
  transition: all 0.2s;
}
.sidebar-link:hover {
  background-color: #374151;
  color: white;
}
.sidebar-link.active {
  background-color: #2e7d32;
  color: white;
  font-weight: bold;
}

/* Table එකේ පෙනුම */
.custom-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.custom-table th {
  background-color: #2e7d32;
  color: white;
  padding: 12px;
  text-align: left;
}
.custom-table td {
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
}
.custom-table tr:hover {
  background-color: #f3f4f6;
}
</style>