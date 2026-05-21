<?php
// ============================================================
//  pos.php  —  Point of Sale
//  Requires: session, lhdb.php, sidebar.php
// ============================================================
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once './utils/lhdb.php';

$user_id = (int) $_SESSION['user_id'];

// Fetch all active products for this user
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "SELECT product_id, product_name, sku, current_stock, retail_price
         FROM   Product
         WHERE  user_id = :user_id
           AND  current_stock > 0
         ORDER  BY product_name ASC"
    );
    $stmt->execute([':user_id' => $user_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("POS fetch error: " . $e->getMessage());
    $products = [];
}

$activePage = 'pos';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Point of Sale – ListaHub</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>

  <!--
    Load order (IMPORTANT — do not reorder):
      1. global_sidebar.css  — body reset + sidebar CSS variables
      2. global_pos.css      — POS CSS variables (extends :root)
      3. sidebar.css         — sidebar component styles
      4. pos.css             — POS page layout + component styles
  -->
  <link rel="stylesheet" href="global_sidebar.css"/>
  <link rel="stylesheet" href="global_pos.css"/>
  <link rel="stylesheet" href="sidebar.css"/>
  <link rel="stylesheet" href="pos.css"/>
</head>
<body>

<div class="page-wrapper">

  <!-- ============================================================
       SIDEBAR
       $activePage must be set before including sidebar.php.
       ============================================================ -->
  <?php
    $activePage = 'pos';
    include 'sidebar.php';
  ?>

  <!-- ============================================================
       MAIN BODY
       ============================================================ -->
  <div class="main-body">

    <!-- ── LEFT PANEL: Product Table ── -->
    <div class="overview">
      <h1 class="page-title">Point of Sale</h1>

      <!-- Top action bar -->
      <div class="top-actions">
        <button class="btn-clear" id="btn-clear" type="button">clear</button>
        <a href="dashboard.php" class="btn-dashboard">Back to Dashboard</a>
      </div>

      <!-- Search bar -->
      <div class="searchbar">
        <!--
          NOTE: You can replace the <i> below with an <img> pointing to your magnifying glass icon.
          e.g. <img class="searchbar-icon" src="./pics_icons/magnifying-glass.svg" alt="Search"/>
        -->
        <i class="bi bi-search searchbar-icon"></i>
        <input
          id="search-input"
          type="text"
          placeholder="Search for product name / sku"
          autocomplete="off"
        />
      </div>

      <!-- Table section -->
      <div class="table-section">
        <div class="table-wrap">
          <div class="table-inner">
            <div class="table2">

              <!-- THEAD -->
              <div class="thead">
                <div class="thead-row">
                  <span class="col-name"><b>Product Name</b></span>
                  <span class="col-sku"><b>SKU</b></span>
                  <span class="col-stock"><b>Stock</b></span>
                  <span class="col-qty"><b>Add Qty</b></span>
                  <span class="col-price"><b>Price</b></span>
                  <span class="col-actions"><b>Actions</b></span>
                </div>
                <div class="thead-divider"></div>
              </div>

              <!-- TBODY — rendered from PHP -->
              <div class="tbody" id="pos-tbody">
                <?php if (empty($products)): ?>
                  <p class="no-products-msg">No products available.</p>
                <?php else: ?>
                  <?php foreach ($products as $p): ?>
                  <div class="tbody-row"
                       data-product-id="<?= (int)$p['product_id'] ?>"
                       data-price="<?= (float)$p['retail_price'] ?>"
                       data-stock="<?= (int)$p['current_stock'] ?>"
                       data-hidden="">
                    <div class="tbody-row-content">
                      <span class="col-name col-name-val"><?= htmlspecialchars($p['product_name']) ?></span>
                      <span class="col-sku  col-sku-val"><?= htmlspecialchars($p['sku'] ?? '—') ?></span>
                      <span class="col-stock col-stock-val"><?= number_format((int)$p['current_stock']) ?></span>
                      <div class="col-qty">
                        <div class="qty-control">
                          <!--
                            NOTE: You can replace the <i> tags below with <img> tags pointing to your icons.
                            Add button:   <img src="./pics_icons/lsiconcircle-add-outline.svg" width="16" height="16" alt="Add"/>
                            Minus button: <img src="./pics_icons/lsiconcircle-minus-outline.svg" width="16" height="16" alt="Minus"/>
                          -->
                          <button class="qty-btn qty-add" type="button" title="Add">
                            <i class="bi bi-plus-circle"></i>
                          </button>
                          <span class="qty-val">0</span>
                          <button class="qty-btn qty-minus" type="button" title="Minus">
                            <i class="bi bi-dash-circle"></i>
                          </button>
                        </div>
                      </div>
                      <span class="col-price col-price-val">₱ <?= number_format((float)$p['retail_price'], 2) ?></span>
                      <div class="col-actions actions-cell">
                        <button class="trash-btn" type="button" title="Remove">
                          <!--
                            NOTE: You can replace the <i> below with an <img> pointing to your trash icon.
                            e.g. <img src="./pics_icons/iconamoon-trash.svg" width="17" height="19" alt="Delete"/>
                          -->
                          <i class="bi bi-trash3"></i>
                        </button>
                      </div>
                    </div>
                    <div class="row-divider"></div>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div><!-- /tbody -->

            </div><!-- /table2 -->
          </div><!-- /table-inner -->
          <div class="table-scroll-indicator"></div>
        </div><!-- /table-wrap -->

        <!-- Pagination -->
        <div class="pagination">
          <button class="btn-page" id="btn-prev" type="button">
            <!--
              NOTE: You can replace the <i> below with an <img> pointing to your left arrow icon.
              e.g. <img src="./pics_icons/arrow-left.svg" width="18" height="18" alt="Prev"/>
            -->
            <i class="bi bi-arrow-left"></i>
            <span>Prev</span>
          </button>
          <button class="btn-page" id="btn-next" type="button">
            <span>Next</span>
            <!--
              NOTE: You can replace the <i> below with an <img> pointing to your right arrow icon.
              e.g. <img src="./pics_icons/arrow-right.svg" width="18" height="18" alt="Next"/>
            -->
            <i class="bi bi-arrow-right"></i>
          </button>
        </div>
      </div><!-- /table-section -->

    </div><!-- /overview -->

    <!-- ── RIGHT PANEL: Checkout ── -->
    <section class="co">

      <!-- Total display -->
      <div class="total-view">
        <span class="total-label">TOTAL</span>
        <div class="total-amount-wrapper">
          <h2 class="total-amount" id="display-total">₱ 0.00</h2>
        </div>
      </div>

      <!-- Subtotal + Payment panel -->
      <div class="subtotal-panel">

        <!-- Subtotal row -->
        <div class="subtotal-row">
          <span>Subtotal</span>
          <span id="display-items">( 0 items )</span>
        </div>

        <!-- Payment box -->
        <div class="payment-box">

          <span class="payment-label">Payment method</span>

          <!-- Cash / G-Cash / Utang -->
          <div class="pay-method-btns">
            <button class="btn-cash active" id="pay-cash" type="button">Cash</button>
            <div class="pay-other-btns">
              <button class="btn-pay-other" id="pay-gcash" type="button">G-Cash</button>
              <button class="btn-pay-other" id="pay-utang" type="button">Utang</button>
            </div>
          </div>

          <!-- Amount Tendered -->
          <span class="amount-tendered-label">Amount Tendered</span>
          <div class="amount-tendered-btns">
            <div class="tendered-display" id="display-tendered">0.00</div>
            <div class="tendered-quick">
              <button class="btn-quick" type="button" data-amount="100">₱ 100</button>
              <button class="btn-quick" type="button" data-amount="500">₱ 500</button>
              <button class="btn-quick" type="button" data-amount="1000">₱ 1,000</button>
              <button class="btn-exact" type="button" id="btn-exact">Exact</button>
            </div>
          </div>

          <!-- Change Due -->
          <span class="change-label">Change Due</span>
          <div class="change-display" id="display-change">₱ 0.00</div>

        </div><!-- /payment-box -->
      </div><!-- /subtotal-panel -->

      <!-- Cancel / Check out -->
      <div class="checkout-actions">
        <button class="btn-cancel" id="btn-cancel" type="button">Cancel</button>
        <button class="btn-checkout" id="btn-checkout" type="button">Check out</button>
      </div>

    </section><!-- /co -->

  </div><!-- /main-body -->
</div><!-- /page-wrapper -->

<script>
/* ============================================================
   POS — JavaScript
   All state is kept in memory; submit to process_sale.php via
   AJAX when checkout is confirmed.
   ============================================================ */

'use strict';

/* ── State ─────────────────────────────────────────────────── */
let tendered    = 0;
let payMethod   = 'cash';
let currentPage = 1;
const ROWS_PER_PAGE = 10;

/* ── DOM refs ──────────────────────────────────────────────── */
const tbody         = document.getElementById('pos-tbody');
const displayTotal  = document.getElementById('display-total');
const displayItems  = document.getElementById('display-items');
const displayTend   = document.getElementById('display-tendered');
const displayChange = document.getElementById('display-change');

/* ── Helpers ───────────────────────────────────────────────── */
function fmt(n) {
  return '₱ ' + n.toFixed(2);
}

function getTotal() {
  let t = 0;
  tbody.querySelectorAll('.tbody-row').forEach(function(row) {
    const price = parseFloat(row.dataset.price) || 0;
    const qty   = parseInt(row.querySelector('.qty-val').textContent, 10) || 0;
    t += price * qty;
  });
  return t;
}

function getItemCount() {
  let c = 0;
  tbody.querySelectorAll('.tbody-row').forEach(function(row) {
    c += parseInt(row.querySelector('.qty-val').textContent, 10) || 0;
  });
  return c;
}

function updateSummary() {
  const total  = getTotal();
  const items  = getItemCount();
  const change = tendered - total;

  displayTotal.textContent  = fmt(total);
  displayItems.textContent  = '( ' + items + ' item' + (items !== 1 ? 's' : '') + ' )';
  displayTend.textContent   = tendered.toFixed(2);
  displayChange.textContent = fmt(Math.max(0, change));
}

/* ── Qty controls & trash (event delegation) ────────────────── */
tbody.addEventListener('click', function(e) {
  const addBtn   = e.target.closest('.qty-add');
  const minusBtn = e.target.closest('.qty-minus');
  const trashBtn = e.target.closest('.trash-btn');

  if (addBtn) {
    const row   = addBtn.closest('.tbody-row');
    const valEl = row.querySelector('.qty-val');
    const stock = parseInt(row.dataset.stock, 10) || 0;
    let qty = parseInt(valEl.textContent, 10) || 0;
    if (qty < stock) {
      valEl.textContent = qty + 1;
      updateSummary();
    }
  }

  if (minusBtn) {
    const row   = minusBtn.closest('.tbody-row');
    const valEl = row.querySelector('.qty-val');
    let qty = parseInt(valEl.textContent, 10) || 0;
    if (qty > 0) {
      valEl.textContent = qty - 1;
      updateSummary();
    }
  }

  if (trashBtn) {
    const row = trashBtn.closest('.tbody-row');
    // Reset qty and hide the row (keep it in DOM so it can be re-searched)
    row.querySelector('.qty-val').textContent = '0';
    row.dataset.hidden = '1';
    updateSummary();
    renderPage();
  }
});

/* ── Clear ──────────────────────────────────────────────────── */
document.getElementById('btn-clear').addEventListener('click', function() {
  tbody.querySelectorAll('.tbody-row').forEach(function(row) {
    row.querySelector('.qty-val').textContent = '0';
    row.dataset.hidden = '';
  });
  tendered = 0;
  currentPage = 1;
  updateSummary();
  renderPage();
});

/* ── Payment method ─────────────────────────────────────────── */
function selectPayMethod(method) {
  payMethod = method;
  document.getElementById('pay-cash').classList.toggle('active',  method === 'cash');
  document.getElementById('pay-gcash').classList.toggle('active', method === 'gcash');
  document.getElementById('pay-utang').classList.toggle('active', method === 'utang');
}

document.getElementById('pay-cash').addEventListener('click',  function() { selectPayMethod('cash'); });
document.getElementById('pay-gcash').addEventListener('click', function() { selectPayMethod('gcash'); });
document.getElementById('pay-utang').addEventListener('click', function() { selectPayMethod('utang'); });

/* ── Tendered quick-amount buttons ──────────────────────────── */
document.querySelectorAll('.btn-quick').forEach(function(btn) {
  btn.addEventListener('click', function() {
    tendered += parseFloat(this.dataset.amount) || 0;
    updateSummary();
  });
});

document.getElementById('btn-exact').addEventListener('click', function() {
  tendered = getTotal();
  updateSummary();
});

/* ── Cancel ─────────────────────────────────────────────────── */
document.getElementById('btn-cancel').addEventListener('click', function() {
  tbody.querySelectorAll('.tbody-row').forEach(function(row) {
    row.querySelector('.qty-val').textContent = '0';
    row.dataset.hidden = '';
  });
  tendered  = 0;
  payMethod = 'cash';
  selectPayMethod('cash');
  currentPage = 1;
  updateSummary();
  renderPage();
});

/* ── Checkout ───────────────────────────────────────────────── */
document.getElementById('btn-checkout').addEventListener('click', function() {
  const total = getTotal();
  const items = getItemCount();

  if (items === 0) {
    alert('No items added to the cart.');
    return;
  }
  if (payMethod !== 'utang' && tendered < total) {
    alert('Amount tendered (₱' + tendered.toFixed(2) + ') is less than the total (₱' + total.toFixed(2) + ').');
    return;
  }

  const change = Math.max(0, tendered - total);
  const msg = [
    'TOTAL:    ₱' + total.toFixed(2),
    'ITEMS:    ' + items,
    'PAYMENT:  ' + payMethod.toUpperCase(),
    'TENDERED: ₱' + tendered.toFixed(2),
    'CHANGE:   ₱' + change.toFixed(2),
    '',
    'Proceed with checkout?'
  ].join('\n');

  if (confirm(msg)) {
    /*
      TODO: Submit the sale to your backend.
      Collect cart data and POST to process_sale.php, e.g.:

      const cartItems = [];
      document.querySelectorAll('#pos-tbody .tbody-row').forEach(function(row) {
        const qty = parseInt(row.querySelector('.qty-val').textContent, 10) || 0;
        if (qty > 0) {
          cartItems.push({
            product_id: row.dataset.productId,
            qty:        qty,
            price:      row.dataset.price,
          });
        }
      });

      fetch('process_sale.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          items:      cartItems,
          total:      total,
          pay_method: payMethod,
          tendered:   tendered,
          change:     change,
        })
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          alert('Transaction complete! Receipt #' + data.sale_id);
          document.getElementById('btn-cancel').click();
        } else {
          alert('Error: ' + data.message);
        }
      });
    */
    alert('Transaction complete!');
    document.getElementById('btn-cancel').click();
  }
});

/* ── Search filter ──────────────────────────────────────────── */
document.getElementById('search-input').addEventListener('input', function() {
  const q = this.value.toLowerCase().trim();
  tbody.querySelectorAll('.tbody-row').forEach(function(row) {
    const name = (row.querySelector('.col-name-val') ? row.querySelector('.col-name-val').textContent : '').toLowerCase();
    const sku  = (row.querySelector('.col-sku-val')  ? row.querySelector('.col-sku-val').textContent  : '').toLowerCase();
    row.dataset.hidden = (q && !name.includes(q) && !sku.includes(q)) ? '1' : '';
  });
  currentPage = 1;
  renderPage();
});

/* ── Pagination ─────────────────────────────────────────────── */
function renderPage() {
  const allRows = Array.from(tbody.querySelectorAll('.tbody-row'));
  const visible = allRows.filter(function(r) { return r.dataset.hidden !== '1'; });
  const total   = visible.length;
  const maxPage = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
  if (currentPage > maxPage) currentPage = maxPage;

  const start = (currentPage - 1) * ROWS_PER_PAGE;
  const end   = start + ROWS_PER_PAGE;

  // Hide all rows first
  allRows.forEach(function(r) { r.style.display = 'none'; });

  // Show only current page of visible (non-hidden) rows
  visible.forEach(function(r, i) {
    r.style.display = (i >= start && i < end) ? '' : 'none';
  });

  document.getElementById('btn-prev').disabled = currentPage <= 1;
  document.getElementById('btn-next').disabled = currentPage >= maxPage;
}

document.getElementById('btn-prev').addEventListener('click', function() {
  if (currentPage > 1) { currentPage--; renderPage(); }
});
document.getElementById('btn-next').addEventListener('click', function() {
  currentPage++; renderPage();
});

/* ── Init ───────────────────────────────────────────────────── */
updateSummary();
renderPage();
</script>
</body>
</html>