<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }

$conn = new mysqli("localhost", "root", "", "store_tracker");
if ($conn->connect_error) { die("Database Connection Failed."); }

$msg        = "";
$alert_type = "success";

// ── ACTION: ADD NEW PRODUCT ───────────────────────────────────────────────────
if (isset($_POST['save_product'])) {
    $name   = $conn->real_escape_string(trim($_POST['p_name']));
    $cat_id = intval($_POST['cat_id']);
    $price  = floatval($_POST['p_price']);
    $stock  = intval($_POST['p_stock']);

    if (empty($name) || $price <= 0 || $stock < 0) {
        $msg = "Validation failed. Please check all fields.";
        $alert_type = "error";
    } else {
        try {
            $sql = "INSERT INTO Products (product_name, category_id, price, stock_quantity)
                    VALUES ('$name', $cat_id, $price, $stock)";
            if ($conn->query($sql)) {
                $msg = "Product '$name' registered successfully.";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $msg = "Conflict: A product with that name already exists.";
                $alert_type = "error";
            } else {
                $msg = "Database error: " . $e->getMessage();
                $alert_type = "error";
            }
        }
    }
}

// ── ACTION: RESTOCK ───────────────────────────────────────────────────────────
if (isset($_POST['stock_up'])) {
    $p_id      = intval($_POST['product_id']);
    $added_qty = intval($_POST['added_qty']);

    if ($added_qty <= 0) {
        $msg = "Quantity must be at least 1.";
        $alert_type = "error";
    } else {
        $sql = "UPDATE Products SET stock_quantity = stock_quantity + $added_qty WHERE product_id = $p_id";
        if ($conn->query($sql) && $conn->affected_rows > 0) {
            $msg = "Inventory updated: +$added_qty units added.";
        }
    }
}

// ── ACTION: DELETE PRODUCT ────────────────────────────────────────────────────
if (isset($_POST['delete_product'])) {
    $p_id = intval($_POST['product_id']);
    $conn->query("DELETE FROM Products WHERE product_id = $p_id");
    $msg = "Product record removed.";
    $alert_type = "warn";
}

// ── DATA FETCH ────────────────────────────────────────────────────────────────
$categories_list = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");
$inventory       = $conn->query("
    SELECT p.*, c.category_name
    FROM Products p
    LEFT JOIN Categories c ON p.category_id = c.category_id
    ORDER BY p.product_id DESC
");
$products_list   = $conn->query("SELECT product_id, product_name FROM Products ORDER BY product_name ASC");
$total_products  = $conn->query("SELECT COUNT(*) AS cnt FROM Products")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory — CornerStop</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .tab-bar {
            display: flex;
            gap: 4px;
            background: var(--surface-2);
            border-radius: var(--radius);
            padding: 4px;
            margin-bottom: 24px;
            width: fit-content;
        }
        .tab-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 7px;
            background: transparent;
            color: var(--text-2);
            font-family: 'DM Mono', monospace;
            font-size: 0.75rem;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            transition: background 0.15s, color 0.15s;
        }
        .tab-btn.active {
            background: var(--surface);
            color: var(--accent);
            box-shadow: 0 1px 4px rgba(0,0,0,0.3);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main">

    <div class="page-header">
        <h1>Inventory Management</h1>
        <p><?php echo $total_products; ?> active SKUs registered in the system</p>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo ($alert_type === 'error') ? 'error' : (($alert_type === 'warn') ? 'warn' : 'success'); ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- ── TAB SWITCHER ─────────────────────────────── -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('add', this)">Add Product</button>
        <button class="tab-btn"        onclick="switchTab('restock', this)">Restock</button>
    </div>

    <!-- TAB: ADD PRODUCT ──────────────────────────────────── -->
    <div id="tab-add" class="tab-content active">
        <div class="panel" style="max-width: 720px;">
            <div class="panel-header">
                <span class="panel-title">Register New Product</span>
                <span class="panel-badge">Acquisition</span>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <div class="form-grid" style="grid-template-columns: 2fr 1fr; margin-bottom: 18px;">
                        <div class="form-group">
                            <label class="form-label">Item Name</label>
                            <input type="text" name="p_name" class="form-control" placeholder="e.g. Coca-Cola 1L" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="cat_id" class="form-control" required>
                                <option value="" disabled selected>Select...</option>
                                <?php while ($c = $categories_list->fetch_assoc()): ?>
                                    <option value="<?php echo $c['category_id']; ?>">
                                        <?php echo htmlspecialchars($c['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 24px;">
                        <div class="form-group">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" name="p_price" step="0.01" min="0.01" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Initial Stock Qty</label>
                            <input type="number" name="p_stock" min="0" class="form-control" placeholder="0" required>
                        </div>
                    </div>
                    <button type="submit" name="save_product" class="btn btn-accent">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Register Product
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB: RESTOCK ──────────────────────────────────────── -->
    <div id="tab-restock" class="tab-content">
        <div class="panel" style="max-width: 480px;">
            <div class="panel-header">
                <span class="panel-title">Restock Existing Item</span>
                <span class="panel-badge">Inventory Update</span>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label class="form-label">Select Product</label>
                        <select name="product_id" class="form-control" required>
                            <option value="" disabled selected>Choose item...</option>
                            <?php while ($p = $products_list->fetch_assoc()): ?>
                                <option value="<?php echo $p['product_id']; ?>">
                                    <?php echo htmlspecialchars($p['product_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label class="form-label">Quantity to Add</label>
                        <input type="number" name="added_qty" min="1" class="form-control" placeholder="e.g. 50" required>
                    </div>
                    <button type="submit" name="stock_up" class="btn btn-warn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                        Restock Item
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── FULL INVENTORY TABLE (read-capable, with delete) ── -->
    <div class="panel" style="margin-top: 32px;">
        <div class="panel-header">
            <span class="panel-title">Product Registry</span>
            <span class="panel-badge"><?php echo $total_products; ?> records</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit Price</th>
                    <th>Stock Level</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $inventory->fetch_assoc()):
                    $qty = $row['stock_quantity'];
                    if ($qty == 0)      { $status = 'OUT OF STOCK'; $cls = 'stock-empty'; }
                    elseif ($qty < 10)  { $status = 'LOW';          $cls = 'stock-low'; }
                    else                { $status = 'OK';            $cls = 'stock-ok'; }
                ?>
                <tr>
                    <td class="text-muted">ID-<?php echo $row['product_id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                    <td class="text-muted"><?php echo htmlspecialchars($row['category_name'] ?? '—'); ?></td>
                    <td>₱<?php echo number_format($row['price'], 2); ?></td>
                    <td class="<?php echo $cls; ?>"><?php echo $qty; ?> units</td>
                    <td>
                        <span style="font-size: 0.62rem; padding: 2px 8px; border-radius: 20px;
                            background: <?php echo ($qty == 0) ? 'var(--danger-dim)' : (($qty < 10) ? 'var(--warn-dim)' : 'var(--accent-dim)'); ?>;
                            color: <?php echo ($qty == 0) ? 'var(--danger)' : (($qty < 10) ? 'var(--warn)' : 'var(--accent)'); ?>;">
                            <?php echo $status; ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Remove this product record?');" style="display:inline;">
                            <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                            <button type="submit" name="delete_product" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.main -->

<script>
function switchTab(tab, el) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

    // Activate selected
    document.getElementById('tab-' + tab).classList.add('active');
    el.classList.add('active');
}
</script>
</body>
</html>
