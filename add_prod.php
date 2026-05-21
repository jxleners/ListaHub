<?php
// ============================================================
//  add_prod.php
//  Standalone popup page — Add a new product.
//  - X button / Cancel → closes popup or goes back to manage-products.php
//  - Complete          → saves the product, shows success flash,
//                        resets the form so more products can be added.
// ============================================================
session_start();
if (!isset($_SESSION['user_id'])) {
    echo '<script>
        if (window.opener && !window.opener.closed) { window.close(); }
        else { window.location.href = "index.php"; }
    </script>';
    exit;
}

require_once './utils/lhdb.php';

$user_id       = (int) $_SESSION['user_id'];
$flash_message = '';
$flash_type    = '';

// ── Handle POST: Add Product ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $product_name  = trim($_POST['product_name']    ?? '');
    $category_name = trim($_POST['category']        ?? '');
    $expiry_date   = $_POST['expiry_date']           ?? null;
    $no_expiry     = isset($_POST['no_expiry']);
    $quantity      = (int)   ($_POST['stock_quantity'] ?? 0);
    $cost_price    = (float) ($_POST['cost']           ?? 0);
    $retail_price  = (float) ($_POST['selling_price']  ?? 0);
    $notes         = trim($_POST['notes']           ?? '');

    if (empty($product_name)) {
        $flash_message = 'Product name is required.';
        $flash_type    = 'error';
    } else {
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            // Resolve or create category
            $target_cat = !empty($category_name) ? $category_name : 'Uncategorized';
            $catStmt = $pdo->prepare(
                "SELECT category_id FROM Category WHERE category_name = :name LIMIT 1"
            );
            $catStmt->execute([':name' => $target_cat]);
            $cat = $catStmt->fetch();
            if ($cat) {
                $cat_id = $cat['category_id'];
            } else {
                $insCAT = $pdo->prepare("INSERT INTO Category (category_name) VALUES (:name)");
                $insCAT->execute([':name' => $target_cat]);
                $cat_id = (int) $pdo->lastInsertId();
            }

            $final_expiry = ($no_expiry || empty($expiry_date)) ? null : $expiry_date;

            $ins = $pdo->prepare(
                "INSERT INTO Product
                    (user_id, category_id, product_name, quantity, cost_price, retail_price, expiration_date, notes)
                 VALUES
                    (:user_id, :category_id, :product_name, :quantity, :cost_price, :retail_price, :expiry, :notes)"
            );
            $ins->execute([
                ':user_id'      => $user_id,
                ':category_id'  => $cat_id,
                ':product_name' => $product_name,
                ':quantity'     => $quantity,
                ':cost_price'   => $cost_price,
                ':retail_price' => $retail_price,
                ':expiry'       => $final_expiry,
                ':notes'        => $notes,
            ]);

            $new_id = (int) $pdo->lastInsertId();
            $pdo->commit();

            // Fetch the auto-generated SKU (if your DB trigger generates one)
            $skuStmt = $pdo->prepare("SELECT sku FROM Product WHERE product_id = :id");
            $skuStmt->execute([':id' => $new_id]);
            $added_sku = $skuStmt->fetchColumn() ?: '';

            $flash_message = 'Product "' . htmlspecialchars($product_name) . '" added successfully!' .
                             ($added_sku ? ' (SKU: ' . htmlspecialchars($added_sku) . ')' : '');
            $flash_type    = 'success';

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log("Add product error: " . $e->getMessage());
            $flash_message = 'A database error occurred. Please try again.';
            $flash_type    = 'error';
        }
    }
}

// ── Fetch existing categories for autocomplete ───────────────
$categories = [];
try {
    $pdo = getPDO();
    $catListStmt = $pdo->prepare(
        "SELECT DISTINCT c.category_name
         FROM Category c
         JOIN Product p ON p.category_id = c.category_id
         WHERE p.user_id = :user_id
         ORDER BY c.category_name"
    );
    $catListStmt->execute([':user_id' => $user_id]);
    $categories = $catListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Add product fetch categories error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Product – ListaHub</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>

  <!--
    Load order:
      1. global_add_prod.css  — CSS variables
      2. add_prod.css         — page styles
  -->
  <link rel="stylesheet" href="global_add_prod.css"/>
  <link rel="stylesheet" href="add_prod.css"/>
</head>
<body>

<div class="add-modal-wrapper">

  <!-- ── Header ── -->
  <div class="modal-header">
    <h2 class="modal-title">ADD PRODUCTS</h2>

    <!--
      X Close button:
      - If opened as popup → window.close()
      - If opened directly → go back to manage-products.php
    -->
    <button class="btn-close" type="button" onclick="handleClose()" title="Close">
      <!--
        NOTE: Replace with <img>:
        <img src="./pics_icons/iconamoon-close-bold.svg" width="20" height="20" alt="Close"/>
      -->
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <!-- ── Flash message ── -->
  <?php if ($flash_message): ?>
  <div class="flash-message show flash-<?= $flash_type ?>">
    <?= htmlspecialchars($flash_message) ?>
  </div>
  <?php endif; ?>

  <!-- ── Form ── -->
  <form class="form-section" method="post" enctype="multipart/form-data" id="add-product-form">
    <input type="hidden" name="action" value="add"/>

    <!-- Row 1: Image Upload + Product Name & Category -->
    <div class="top-fields-row">

      <!-- Image upload -->
      <div class="img-upload-box" id="img-upload-box" title="Click to upload image">
        <!--
          NOTE: Replace <i> with your add-image icon:
          <img class="img-upload-icon" src="./pics_icons/mdi-image-add-outline.svg" width="93" alt="Add Image"/>
        -->
        <i class="bi bi-image-fill" style="font-size:64px;color:var(--1-brown);opacity:0.4;pointer-events:none;"></i>
        <img class="img-upload-preview" id="img-preview" src="" alt="Preview"/>
        <input
          type="file"
          name="product_image"
          id="product-image-input"
          accept="image/*"
          onchange="previewImage(this)"
          title="Upload product image"
        />
      </div>

      <!-- Product Name + Category (stacked on the right) -->
      <div class="right-fields">
        <div class="field-group" style="align-self:stretch;">
          <label class="field-label" for="product-name">Product Name</label>
          <div class="field-input">
            <input
              type="text"
              id="product-name"
              name="product_name"
              placeholder="Enter Here"
              required
              autocomplete="off"
            />
          </div>
        </div>

        <div class="field-group" style="align-self:stretch;">
          <label class="field-label" for="product-category">Category</label>
          <div class="field-input">
            <input
              type="text"
              id="product-category"
              name="category"
              placeholder="Enter Here"
              list="cat-list"
              autocomplete="off"
            />
          </div>
        </div>
      </div>
    </div><!-- /top-fields-row -->

    <!-- Row 2: SKU (auto) + Expiry Date -->
    <div class="fields-row">
      <div class="field-group">
        <label class="field-label">SKU</label>
        <div class="sku-field">
          <span>Auto-generated on save</span>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="expiry-date">Expiry Date</label>
        <div class="expiry-field-wrap">
          <div class="expiry-date-part">
            <!--
              NOTE: Replace <i> with <img>:
              <img src="./pics_icons/uiw-date.svg" width="16" height="16" alt=""/>
            -->
            <i class="bi bi-calendar3"></i>
            <input
              type="date"
              id="expiry-date"
              name="expiry_date"
            />
          </div>
          <div class="none-checkbox-part">
            <label for="no-expiry">None</label>
            <input
              type="checkbox"
              id="no-expiry"
              name="no_expiry"
              onchange="toggleExpiry(this)"
            />
          </div>
        </div>
      </div>
    </div><!-- /row 2 -->

    <!-- Row 3: Stock Quantity + Cost + Selling Price -->
    <div class="fields-row">
      <div class="field-group">
        <label class="field-label" for="stock-qty">Stock Quantity</label>
        <div class="field-input">
          <input
            type="number"
            id="stock-qty"
            name="stock_quantity"
            placeholder="Enter Here"
            min="0"
            value="0"
          />
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="cost-price">Cost</label>
        <div class="field-input">
          <input
            type="number"
            id="cost-price"
            name="cost"
            placeholder="₱"
            step="0.01"
            min="0"
            value="0"
          />
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="selling-price">Selling Price</label>
        <div class="field-input">
          <input
            type="number"
            id="selling-price"
            name="selling_price"
            placeholder="₱"
            step="0.01"
            min="0"
            value="0"
          />
        </div>
      </div>
    </div><!-- /row 3 -->

    <!-- Row 4: Additional Notes -->
    <div class="fields-row" style="align-items:flex-start;">
      <div class="field-group full-width">
        <label class="field-label" for="notes">Additional Notes</label>
        <div class="field-input textarea-wrap">
          <textarea
            id="notes"
            name="notes"
            placeholder="Enter Here"
          ></textarea>
        </div>
      </div>
    </div><!-- /row 4 -->

    <!-- Footer: Cancel + Complete -->
    <div class="form-footer">
      <button type="button" class="btn-cancel" onclick="handleClose()">Cancel</button>
      <button type="submit" class="btn-complete">Complete</button>
    </div>

  </form><!-- /form-section -->

</div><!-- /add-modal-wrapper -->

<!-- Category autocomplete datalist -->
<datalist id="cat-list">
  <?php foreach ($categories as $cat): ?>
    <option value="<?= htmlspecialchars($cat['category_name']) ?>">
  <?php endforeach; ?>
</datalist>

<script>
'use strict';

/**
 * handleClose:
 * If opened as a popup → window.close() (parent will reload).
 * If opened directly   → go back to manage-products.php.
 */
function handleClose() {
  if (window.opener && !window.opener.closed) {
    window.close();
  } else {
    window.location.href = 'manage-products.php';
  }
}

/**
 * toggleExpiry: disable/enable the date input based on the checkbox.
 */
function toggleExpiry(checkbox) {
  var dateInput = document.getElementById('expiry-date');
  dateInput.disabled = checkbox.checked;
  if (checkbox.checked) {
    dateInput.value = '';
  }
}

/**
 * previewImage: show selected image before submitting.
 */
function previewImage(input) {
  var preview = document.getElementById('img-preview');
  var icon    = document.querySelector('.img-upload-box > i');
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function (e) {
      preview.src          = e.target.result;
      preview.style.display = 'block';
      if (icon) icon.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

<?php if ($flash_type === 'success'): ?>
/* ── After successful add: reset form, keep page open ── */
(function () {
  window.scrollTo(0, 0);

  var form = document.getElementById('add-product-form');
  if (form) {
    /* Reset all fields */
    form.reset();

    /* Restore upload icon, hide preview */
    var preview = document.getElementById('img-preview');
    var icon    = document.querySelector('.img-upload-box > i');
    if (preview) { preview.style.display = 'none'; preview.src = ''; }
    if (icon)    { icon.style.display = ''; }

    /* Re-enable expiry input (reset() unchecks checkbox but we must re-enable explicitly) */
    var expiryInput = document.getElementById('expiry-date');
    if (expiryInput) expiryInput.disabled = false;
  }

  /* Auto-hide flash after 4 s */
  setTimeout(function () {
    var flash = document.querySelector('.flash-message');
    if (flash) { flash.style.opacity = '0'; flash.style.transition = 'opacity 0.5s'; }
  }, 4000);
  setTimeout(function () {
    var flash = document.querySelector('.flash-message');
    if (flash) { flash.style.display = 'none'; }
  }, 4600);
})();
<?php endif; ?>
</script>
</body>
</html>