<?php
// sidebar.php — shared sidebar include
// Usage: include 'sidebar.php'; (after session_start and auth check)
// Set $activePage = 'dashboard' | 'pos' | 'manage-products' | 'restock' | 'sales' | 'customers'
// before including this file.
$activePage = $activePage ?? '';
?>
<aside class="sidebar">
  <!-- ===== LOGO ===== -->
  <!-- TODO: Replace the src below with the path to your ListaHub logo image -->
  <!-- e.g. src="./public/ListaHub-logo-2-1@2x.png" -->
  <section class="logos">
    <img
      class="listahub-logo"
      alt="ListaHub"
      src="pics_icons/ListaHub-logo-2-1.png"
    />
  </section>

  <!-- ===== DASHBOARD ===== -->
  <a href="dashboard.php" class="menus <?= $activePage === 'dashboard' ? 'active' : '' ?>">
    <!-- TODO: Replace src with your home/dashboard icon -->
    <!-- e.g. src="./public/material-symbols-home-outline-rounded.svg" -->
    <img class="menu-icon" alt="" src="pics_icons/home-page.png" />
    <span class="menu">Dashboard</span>
  </a>

  <!-- ===== POS ===== -->
  <a href="pos.php" class="menus2 <?= $activePage === 'pos' ? 'active' : '' ?>">
    <!-- TODO: Replace src with your POS/cashier icon -->
    <!-- e.g. src="./public/hugeicons-cashier-02.svg" -->
    <img class="menu-icon" alt="" src="pics_icons/cash-register (2).svg" />
    <span class="menu">POS</span>
  </a>

  <!-- ===== INVENTORY ===== -->
  <section class="inventory-container">
    <div class="inventory-label">INVENTORY</div>
    <a href="manage-products.php" class="menus2 <?= $activePage === 'manage-products' ? 'active' : '' ?>">
      <!-- TODO: Replace src with your product/manage icon -->
      <!-- e.g. src="./public/gridicons-product.svg" -->
      <img class="menu-icon" alt="" src="pics_icons/inventory-alt (1).svg" />
      <span class="menu">Manage Products</span>
    </a>
    <a href="restock.php" class="menus2 <?= $activePage === 'restock' ? 'active' : '' ?>">
      <!-- TODO: Replace src with your restock icon -->
      <!-- e.g. src="./public/gridicons-product.svg" -->
      <img class="menu-icon" alt="" src="pics_icons/box-add (1).svg" />
      <span class="menu">Restock</span>
    </a>
  </section>

  <!-- ===== SALES ===== -->
  <section class="inventory-container">
    <div class="inventory-label">SALES</div>
    <a href="sales.php" class="menus2 <?= $activePage === 'sales' ? 'active' : '' ?>">
      <!-- TODO: Replace src with your sales/money icon -->
      <!-- e.g. src="./public/tdesign-money.svg" -->
      <img class="menu-icon" alt="" src="pics_icons/money (2).png" />
      <span class="menu">Sales Analytics</span>
    </a>
  </section>

  <!-- ===== CUSTOMER CREDIT ===== -->
  <section class="inventory-container">
    <div class="inventory-label">CUSTOMER CREDIT</div>
    <a href="customers.php" class="menus2 <?= $activePage === 'customers' ? 'active' : '' ?>">
      <!-- TODO: Replace src with your credit card icon -->
      <!-- e.g. src="./public/material-symbols-credit-card-outline.svg" -->
      <img class="menu-icon" alt="" src="pics_icons/credit-card-buyer.svg" />
      <span class="menu">Manage Customers</span>
    </a>
  </section>

  <!-- ===== USER / LOGOUT ===== -->
  <section class="logout-container">
    <div class="owner-container">
      <div class="owner-card">
        <div class="image-parent">
          <div class="image"></div>
          <!-- TODO: Replace src with your store icon -->
          <!-- e.g. src="./public/material-symbols-store-outline-rounded.svg" -->
          <img class="store-icon" alt="" src="pics_icons/store.png" />
        </div>
        <div class="owner-details">
          <div class="owner-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
          <div class="owner-store"><?= htmlspecialchars($_SESSION['store_name'] ?? '') ?></div>
        </div>
      </div>
    </div>
    <form method="post" action="logout.php">
      <button type="submit" class="menus7">
        <!-- TODO: Replace src with your logout icon -->
        <!-- e.g. src="./public/material-symbols-logout-rounded.svg" -->
        <img class="menu-icon" alt="" src="pics_icons/exit.svg" />
        <span class="menu6">Log out</span>
      </button>
    </form>
  </section>
</aside>