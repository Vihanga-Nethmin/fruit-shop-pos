<template>
  <nav
    style="
      padding: 12px 24px;
      background: #1f3a5f;
      color: #fff;
      font-family: sans-serif;
    "
  >
    <strong>Fruit Shop POS</strong> &nbsp;|&nbsp; Inventory (demo host)
    &nbsp;&nbsp;
    <RouterLink to="/products" style="color: #fff">Products</RouterLink>
  </nav>
  <RouterView />
</template>
