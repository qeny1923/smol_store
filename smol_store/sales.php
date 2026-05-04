<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }

$conn = new mysqli("localhost", "root", "", "store_tracker");
if ($conn->connect_error) { die("Database Connection Failed."); }

$msg        = "";
$alert_type = "success";

// ── ACTION: EXECUTE SALE ──────────────────────────────────────────────────────
if (isset($_POST['add_order'])) {
    $p_id = intval($_POST['product_id']);
    $qty  = intval($_POST['qty']);

    if ($qty <= 0) {
        $msg = "Quantity must be at least 1.";
        $alert_type = "error";
    } else {
        // Fetch current stock + price in one query
        $p_check = $conn->query("
            SELECT product_name, price, stock_quantity
            FROM Products
            WHERE product_id = $p_id
        ")->fetch_assoc();

        if (!$p_check) {
            $msg = "Product not found.";
            $alert_type = "error";
        } elseif ($p_check['stock_quantity'] < $qty) {
            $msg = "Insufficient stock. Available: " . $p_check['stock_quantity'] . " units.";
            $alert_type = "error";
        } else {
            $total = $p_check['price'] * $qty;

            $conn->begin_transaction();
            try {
                // Insert order record
                $conn->query("INSERT INTO Orders (user_id, total_amount) VALUES (1, $total)");
                $order_id = $conn->insert_id;

                // Deduct stock
                $conn->query("UPDATE Products SET stock_quantity = stock_quantity - $qty WHERE product_id = $p_id");

                $conn->commit();
                $msg = "Sale posted — {$qty}× " . htmlspecialchars($p_check['product_name']) . " | Total: ₱" . number_format($total, 2);
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Transaction failed. Please try again.";
                $alert_type = "error";
            }
        }
    }
}

// ── DATA FETCH ────────────────────────────────────────────────────────────────
// Only show products with available stock
$available_products = $conn->query("
    SELECT product_id, product_name, price, stock_quantity
    FROM Products
    WHERE stock_quantity > 0
    ORDER BY product_name ASC
");

// Recent orders for the log table (last 15)
$recent_orders = $conn->query("
    SELECT 
        o.order_id,
        o.total_amount,
        o.order_date,
        u.username
    FROM Orders o
    LEFT JOIN Users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC
    LIMIT 15
");

// Today's totals
$today = $conn->query("
    SELECT 
        COUNT(*)               AS tx_count,
        COALESCE(SUM(total_amount), 0) AS today_total
    FROM Orders
    WHERE DATE(order_date) = CURDATE()
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales — CornerStop</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* POS-feel product info bar */
        #product-info-bar {
            display: none;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 18px;
            margin-bottom: 18px;
            font-size: 0.8rem;
            gap: 24px;
            align-items: center;
        }
        #product-info-bar.visible { display: flex; }
        #product-info-bar .pib-label { color: var(--text-3); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; }
        #product-info-bar .pib-val   { color: var(--text-1); font-weight: 500; font-size: 0.9rem; }

        /* Running total display */
        #total-display {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--accent);
            transition: all 0.2s;
        }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main">

    <div class="page-header">
        <h1>Sales Terminal</h1>
        <p>Today: <span style="color: var(--accent);"><?php echo $today['tx_count']; ?> transactions</span> &nbsp;·&nbsp; ₱<?php echo number_format($today['today_total'], 2); ?> revenue</p>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo ($alert_type === 'error') ? 'error' : 'success'; ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <?php if ($alert_type === 'success'): ?>
                <polyline points="20 6 9 17 4 12"/>
            <?php else: ?>
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            <?php endif; ?>
        </svg>
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <div class="two-col">

        <!-- ── SALE FORM ────────────────────────────────── -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Execute Sale</span>
                <span class="panel-badge" style="background: var(--accent-dim); color: var(--accent);">POS</span>
            </div>
            <div class="panel-body">
                <form method="POST">

                    <!-- Live product info bar (JS-driven) -->
                    <div id="product-info-bar">
                        <div>
                            <div class="pib-label">Unit Price</div>
                            <div class="pib-val" id="pib-price">—</div>
                        </div>
                        <div>
                            <div class="pib-label">In Stock</div>
                            <div class="pib-val" id="pib-stock">—</div>
                        </div>
                        <div style="margin-left: auto; text-align: right;">
                            <div class="pib-label">Order Total</div>
                            <div id="total-display">₱0.00</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label class="form-label">Select Product</label>
                        <select name="product_id" id="product-select" class="form-control" required onchange="updateProductInfo()">
                            <option value="" disabled selected>Choose item...</option>
                            <?php while ($p = $available_products->fetch_assoc()): ?>
                                <option
                                    value="<?php echo $p['product_id']; ?>"
                                    data-price="<?php echo $p['price']; ?>"
                                    data-stock="<?php echo $p['stock_quantity']; ?>">
                                    <?php echo htmlspecialchars($p['product_name']); ?> — ₱<?php echo number_format($p['price'], 2); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 28px;">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="qty" id="qty-input" min="1"
                               class="form-control" placeholder="Enter qty" required
                               oninput="updateTotal()">
                    </div>

                    <button type="submit" name="add_order" class="btn btn-accent" style="width: 100%; justify-content: center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Authorize Sale
                    </button>
                </form>
            </div>
        </div>

        <!-- ── RECENT TRANSACTIONS LOG ──────────────────── -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Recent Transactions</span>
                <span class="panel-badge">Last 15</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date & Time</th>
                        <th>Cashier</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                        <?php while ($order = $recent_orders->fetch_assoc()): ?>
                        <tr>
                            <td class="text-muted">#<?php echo str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td class="text-muted" style="font-size: 0.75rem;">
                                <?php echo date('M j, Y', strtotime($order['order_date'])); ?><br>
                                <span style="color: var(--text-3);"><?php echo date('g:i A', strtotime($order['order_date'])); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($order['username'] ?? 'system'); ?></td>
                            <td style="color: var(--accent); font-weight: 500;">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-3); padding: 40px;">
                                No transactions recorded yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /.two-col -->

</div><!-- /.main -->

<script>
// ── Live product info & total calculator ─────────────────
function updateProductInfo() {
    const sel   = document.getElementById('product-select');
    const opt   = sel.options[sel.selectedIndex];
    const bar   = document.getElementById('product-info-bar');

    if (sel.value) {
        const price = parseFloat(opt.dataset.price);
        const stock = parseInt(opt.dataset.stock);

        document.getElementById('pib-price').textContent = '₱' + price.toFixed(2);
        document.getElementById('pib-stock').textContent = stock + ' units';
        bar.classList.add('visible');

        // Apply red tint if low stock
        const stockEl = document.getElementById('pib-stock');
        stockEl.style.color = (stock < 10) ? 'var(--warn)' : 'var(--text-1)';

        // Update total if qty already entered
        updateTotal();
    } else {
        bar.classList.remove('visible');
    }
}

function updateTotal() {
    const sel = document.getElementById('product-select');
    const qty = parseInt(document.getElementById('qty-input').value) || 0;

    if (sel.value && qty > 0) {
        const price = parseFloat(sel.options[sel.selectedIndex].dataset.price);
        const total = price * qty;
        document.getElementById('total-display').textContent = '₱' + total.toFixed(2);
    } else {
        document.getElementById('total-display').textContent = '₱0.00';
    }
}
</script>
</body>
</html>
