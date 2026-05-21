<?php
// ============================================================
//  restock.php
//  Requirements: Prepared statements, try-catch, session guard
//  NOTE: New schema — Restock_Transaction + Restock_Item tables,
//        Product.quantity (not stock_quantity), user_id filter
//        Trigger trg_restock_item_add_stock auto-updates stock.
// ============================================================
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once './utils/lhdb.php';

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error   = '';

// ── Handle restock POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id  = (int)   ($_POST['product_id']  ?? 0);
    $qty_added   = (int)   ($_POST['qty_added']   ?? 0);
    $cost_at_rest = (float) ($_POST['cost_price']  ?? 0);

    if ($product_id > 0 && $qty_added > 0) {
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            // Create a restock transaction header
            $txStmt = $pdo->prepare(
                "INSERT INTO Restock_Transaction (restock_date, total_cost)
                 VALUES (NOW(), :total_cost)"
            );
            $txStmt->execute([':total_cost' => $cost_at_rest * $qty_added]);
            $restock_id = (int) $pdo->lastInsertId();

            // Get current cost_price if not provided
            if ($cost_at_rest <= 0) {
                $priceStmt = $pdo->prepare(
                    "SELECT cost_price FROM Product WHERE product_id = :id AND user_id = :uid"
                );
                $priceStmt->execute([':id' => $product_id, ':uid' => $user_id]);
                $pRow = $priceStmt->fetch();
                $cost_at_rest = (float)($pRow['cost_price'] ?? 0);
            }

            // Insert restock item — trigger trg_restock_item_add_stock fires automatically
            $itemStmt = $pdo->prepare(
                "INSERT INTO Restock_Item (restock_id, product_id, quantity_added, cost_price_at_restock)
                 VALUES (:restock_id, :product_id, :qty, :cost)"
            );
            $itemStmt->execute([
                ':restock_id' => $restock_id,
                ':product_id' => $product_id,
                ':qty'        => $qty_added,
                ':cost'       => $cost_at_rest,
            ]);

            $pdo->commit();
            $message = 'Stock updated successfully.';

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log("Restock error: " . $e->getMessage());
            $error = 'A database error occurred. Please try again.';
        }
    } else {
        $error = 'Please select a product and enter a valid quantity.';
    }
}

// ── Fetch product list for display ──────────────────────────
$tab    = $_GET['tab']    ?? 'all';
$search = trim($_GET['search'] ?? '');

try {
    $pdo = getPDO();

    $sql = "SELECT p.product_id, p.product_name, p.sku, p.quantity,
                   p.cost_price, p.retail_price, p.expiration_date,
                   p.status, p.low_stock_threshold,
                   COALESCE(c.category_name, 'Uncategorized') AS category_name,
                   COALESCE(SUM(ri.quantity_added), 0) AS total_restocked
            FROM Product p
            LEFT JOIN Category c ON c.category_id = p.category_id
            LEFT JOIN Restock_Item ri ON ri.product_id = p.product_id
            WHERE p.user_id = :user_id";

    $params = [':user_id' => $user_id];

    if ($tab === 'low') {
        $sql .= " AND p.status = 'Low Stock'";
    } elseif ($tab === 'out') {
        $sql .= " AND p.status = 'Out of Stock'";
    }

    if (!empty($search)) {
        $sql .= " AND (p.product_name LIKE :search OR p.sku LIKE :search2)";
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    $sql .= " GROUP BY p.product_id ORDER BY p.product_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Counts for tab badges using COUNT() in DB
    $countStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Low Stock'    THEN 1 ELSE 0 END) AS low,
            SUM(CASE WHEN status = 'Out of Stock' THEN 1 ELSE 0 END) AS out_of_stock
         FROM Product WHERE user_id = :user_id"
    );
    $countStmt->execute([':user_id' => $user_id]);
    $counts = $countStmt->fetch();

} catch (PDOException $e) {
    error_log("Restock fetch error: " . $e->getMessage());
    $products = [];
    $counts   = ['total' => 0, 'low' => 0, 'out_of_stock' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Restock – ListaHub</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root { --sidebar-bg:#4a4a4a; --sidebar-width:240px; --main-bg:#f4f5f7; --nav-item:#6b6b6b; --nav-text:#ddd; --nav-active:#e8e8e8; }
    body { font-family:'Sora',sans-serif; display:flex; min-height:100vh; background:var(--main-bg); }
    aside { width:var(--sidebar-width); background:var(--sidebar-bg); display:flex; flex-direction:column; padding:20px 16px; gap:8px; position:fixed; top:0; left:0; bottom:0; border-radius:0 16px 16px 0; z-index:10; }
    .sidebar-logo { background:#6b6b6b; color:#eee; text-align:center; font-weight:700; font-size:15px; padding:12px; border-radius:12px; margin-bottom:10px; }
    .sidebar-section-label { color:#aaa; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.8px; padding:8px 8px 2px; }
    .sidebar-item { background:var(--nav-item); color:var(--nav-text); border-radius:12px; padding:11px 16px; font-size:14px; font-weight:600; cursor:pointer; border:none; text-align:left; width:100%; transition:background .2s; text-decoration:none; display:block; }
    .sidebar-item:hover { background:#7a7a7a; }
    .sidebar-item.active { background:var(--nav-active); color:#111; }
    .sidebar-logout { margin-top:auto; background:#616161; color:#ddd; border-radius:12px; padding:11px 16px; font-size:14px; font-weight:600; cursor:pointer; border:none; width:100%; transition:background .2s; }
    .sidebar-logout:hover { background:#7a7a7a; }
    .main-content { margin-left:var(--sidebar-width); flex:1; padding:36px 40px; display:flex; flex-direction:column; gap:24px; }
    .topbar { display:flex; justify-content:flex-end; }
    .user-card { background:#e8e8e8; border-radius:12px; padding:10px 16px; display:flex; align-items:center; gap:12px; }
    .user-info { text-align:right; }
    .user-name { font-weight:700; font-size:14px; color:#222; }
    .user-store { font-size:12px; color:#666; }
    .user-avatar { width:40px; height:40px; background:#888; border-radius:50%; }
    .page-title { font-size:26px; font-weight:800; color:#111; }
    .alert { padding:12px 16px; border-radius:10px; font-size:13px; font-weight:600; }
    .alert-success { background:#d1fae5; color:#065f46; }
    .alert-error   { background:#fee2e2; color:#991b1b; }
    .restock-section { background:#d8dadd; border-radius:16px; padding:18px 20px; display:flex; flex-direction:column; gap:14px; }
    .restock-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .search-box { display:flex; align-items:center; background:#fff; border-radius:50px; padding:8px 16px; gap:8px; flex:1; max-width:300px; }
    .search-box input { border:none; outline:none; font-family:'Sora',sans-serif; font-size:13px; width:100%; }
    .filter-tabs { display:flex; gap:8px; }
    .tab { background:#ccc; border:none; border-radius:50px; padding:8px 20px; font-family:'Sora',sans-serif; font-size:13px; font-weight:600; cursor:pointer; color:#333; text-decoration:none; transition:background .2s; }
    .tab.active { background:#6b6b6b; color:#eee; }
    .tab:hover:not(.active) { background:#bbb; }
    .search-btn { background:#6b6b6b; color:#fff; border:none; border-radius:50px; padding:8px 18px; font-family:'Sora',sans-serif; font-size:13px; font-weight:600; cursor:pointer; }
    table { width:100%; border-collapse:collapse; background:#e8eaed; border-radius:10px; overflow:hidden; }
    thead th { padding:12px 14px; text-align:left; font-size:13px; font-weight:700; color:#222; background:#e0e2e6; }
    tbody tr { border-top:1px solid #d0d2d6; }
    tbody tr:hover { background:#dfe1e5; }
    tbody td { padding:12px 14px; font-size:13px; color:#333; }
    .restock-qty { width:70px; background:#c8c8c8; border:none; border-radius:8px; padding:7px 10px; font-family:'Sora',sans-serif; font-size:13px; text-align:center; }
    .btn-restock { background:#4a4a4a; color:#fff; border:none; border-radius:8px; padding:7px 14px; font-family:'Sora',sans-serif; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; }
    .btn-restock:hover { background:#333; }
    .status-low  { color:#b45309; font-weight:600; }
    .status-out  { color:#b91c1c; font-weight:600; }
    .status-in   { color:#15803d; font-weight:600; }
    .empty-state { text-align:center; color:#aaa; padding:40px 0; font-size:14px; }
  </style>
</head>
<body>
<aside>
  <div class="sidebar-logo">Logo</div>
  <a class="sidebar-item" href="dashboard.php">Dashboard</a>
  <div class="sidebar-section-label">Inventory</div>
  <a class="sidebar-item" href="manage-products.php">Manage Products</a>
  <a class="sidebar-item active" href="restock.php">Restock</a>
  <div class="sidebar-section-label">Sales</div>
  <a class="sidebar-item" href="sales.php">Sales Analytics</a>
  <div class="sidebar-section-label">Customer Credit</div>
  <a class="sidebar-item" href="customers.php">Customers</a>
  <form method="post" action="logout.php" style="margin-top:auto;">
    <button class="sidebar-logout" type="submit">Log out</button>
  </form>
</aside>

<div class="main-content">
  <div class="topbar">
    <div class="user-card">
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
        <div class="user-store"><?= htmlspecialchars($_SESSION['store_name'] ?? '') ?></div>
      </div>
      <div class="user-avatar"></div>
    </div>
  </div>

  <div class="page-title">Restock</div>

  <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="restock-section">
    <div class="restock-toolbar">
      <form method="get" style="display:flex;align-items:center;gap:8px;flex:1;max-width:320px;">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"/>
        <div class="search-box">
          <span><i class="bi bi-box-seam"></i></span>
          <input type="text" name="search" placeholder="Search…" value="<?= htmlspecialchars($search) ?>"/>
        </div>
        <button type="submit" class="search-btn">Go</button>
      </form>
      <div class="filter-tabs">
        <a class="tab <?= $tab==='all'?'active':'' ?>" href="restock.php?tab=all<?= !empty($search)?'&search='.urlencode($search):'' ?>">
          All Products (<?= (int)$counts['total'] ?>)
        </a>
        <a class="tab <?= $tab==='low'?'active':'' ?>" href="restock.php?tab=low<?= !empty($search)?'&search='.urlencode($search):'' ?>">
          <i class="bi bi-exclamation-triangle"></i> Low Stock (<?= (int)$counts['low'] ?>)
        </a>
        <a class="tab <?= $tab==='out'?'active':'' ?>" href="restock.php?tab=out<?= !empty($search)?'&search='.urlencode($search):'' ?>">
          <i class="bi bi-exclamation-triangle-fill"></i> Out of Stock (<?= (int)$counts['out_of_stock'] ?>)
        </a>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Product Name</th><th>SKU</th><th>Category</th>
          <th>Current Stock</th><th>Status</th><th>Add Qty</th><th>Total Restocked</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr><td colspan="7" class="empty-state">No products found.</td></tr>
        <?php else: ?>
          <?php foreach ($products as $p):
            $sClass = match($p['status']) {
                'Out of Stock' => 'status-out',
                'Low Stock'    => 'status-low',
                default        => 'status-in',
            };
          ?>
          <tr>
            <td><?= htmlspecialchars($p['product_name']) ?></td>
            <td><?= htmlspecialchars($p['sku']) ?></td>
            <td><?= htmlspecialchars($p['category_name']) ?></td>
            <td><?= (int)$p['quantity'] ?> pcs</td>
            <td><span class="<?= $sClass ?>"><?= htmlspecialchars($p['status']) ?></span></td>
            <td>
              <form method="post" style="display:flex;align-items:center;gap:8px;">
                <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>"/>
                <input type="hidden" name="cost_price"  value="<?= (float)$p['cost_price'] ?>"/>
                <input class="restock-qty" type="number" name="qty_added" min="1" value="1" required/>
                <button type="submit" class="btn-restock">Restock</button>
              </form>
            </td>
            <td><?= (int)$p['total_restocked'] ?> pcs</td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>