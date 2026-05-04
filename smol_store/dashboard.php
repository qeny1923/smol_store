<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }
 
$conn = new mysqli("localhost", "root", "", "store_tracker");
if ($conn->connect_error) { die("Database Connection Failed."); }
 
// ── KPI Queries ──────────────────────────────────────────────────────────────
 
// 1. Total revenue (all time)
$revenue = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM Orders
")->fetch_assoc()['total_revenue'];
 
// 2. Today's transaction count + today's revenue
$today_stats = $conn->query("
    SELECT 
        COUNT(order_id)         AS tx_count,
        COALESCE(SUM(total_amount), 0) AS today_rev
    FROM Orders
    WHERE DATE(order_date) = CURDATE()
")->fetch_assoc();
 
// 3. Low-stock alerts (items with qty < 10)
$low_stock_count = $conn->query("
    SELECT COUNT(*) AS cnt FROM Products WHERE stock_quantity < 10
")->fetch_assoc()['cnt'];
 
// 4. Total active product SKUs
$sku_count = $conn->query("
    SELECT COUNT(*) AS cnt FROM Products
")->fetch_assoc()['cnt'];
 
// 5. Top 5 products — uses Order_Items if it exists, otherwise falls back
$table_check = $conn->query("
    SELECT COUNT(*) AS cnt 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = 'store_tracker' 
    AND TABLE_NAME = 'Order_Items'
")->fetch_assoc();

if ($table_check['cnt'] > 0) {
    $top_selling = $conn->query("
        SELECT 
            p.product_name,
            p.price,
            p.stock_quantity,
            COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM Products p
        LEFT JOIN Order_Items oi ON p.product_id = oi.product_id
        GROUP BY p.product_id, p.product_name, p.price, p.stock_quantity
        ORDER BY total_sold DESC
        LIMIT 5
    ");
} else {
    // Fallback: just show recently added products
    $top_selling = $conn->query("
        SELECT product_name, price, stock_quantity, 0 AS total_sold
        FROM Products
        ORDER BY product_id DESC
        LIMIT 5
    ");
}
 
// 6. Low-stock items list (up to 5)
$low_stock_items = $conn->query("
    SELECT p.product_name, p.stock_quantity, c.category_name
    FROM Products p
    LEFT JOIN Categories c ON p.category_id = c.category_id
    WHERE p.stock_quantity < 10
    ORDER BY p.stock_quantity ASC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CornerStop</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Subtle animated gradient on accent KPI card value */
        @keyframes countUp {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .kpi-value { animation: countUp 0.4s ease both; }
        .kpi-card:nth-child(1) .kpi-value { animation-delay: 0.05s; }
        .kpi-card:nth-child(2) .kpi-value { animation-delay: 0.10s; }
        .kpi-card:nth-child(3) .kpi-value { animation-delay: 0.15s; }
        .kpi-card:nth-child(4) .kpi-value { animation-delay: 0.20s; }
 
        .progress-bar-wrap {
            height: 4px;
            background: var(--surface-3);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-bar {
            height: 100%;
            background: var(--accent);
            border-radius: 4px;
            transition: width 0.8s cubic-bezier(.4,0,.2,1);
        }
        .progress-bar.warn   { background: var(--warn); }
        .progress-bar.danger { background: var(--danger); }
    </style>
</head>
<body>
 
<?php include 'includes/sidebar.php'; ?>
 
<div class="main">
 
    <div class="page-header">
        <h1>Operations Dashboard</h1>
        <p><?php echo date('l, F j, Y'); ?> &nbsp;·&nbsp; <?php echo date('g:i A'); ?></p>
    </div>
 
    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="kpi-grid">
 
        <div class="kpi-card accent">
            <div class="kpi-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="kpi-label">Total Revenue</div>
            <div class="kpi-value">₱<?php echo number_format($revenue, 0); ?></div>
            <div class="kpi-sub">All-time gross sales</div>
        </div>
 
        <div class="kpi-card info">
            <div class="kpi-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                </svg>
            </div>
            <div class="kpi-label">Today's Transactions</div>
            <div class="kpi-value"><?php echo $today_stats['tx_count']; ?></div>
            <div class="kpi-sub">₱<?php echo number_format($today_stats['today_rev'], 2); ?> earned today</div>
        </div>
 
        <div class="kpi-card <?php echo ($low_stock_count > 0) ? 'danger' : 'warn'; ?>">
            <div class="kpi-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div class="kpi-label">Low Stock Alerts</div>
            <div class="kpi-value"><?php echo $low_stock_count; ?></div>
            <div class="kpi-sub"><?php echo ($low_stock_count == 0) ? 'All items healthy' : 'Items need restocking'; ?></div>
        </div>
 
        <div class="kpi-card warn">
            <div class="kpi-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
            </div>
            <div class="kpi-label">Active SKUs</div>
            <div class="kpi-value"><?php echo $sku_count; ?></div>
            <div class="kpi-sub">Registered products</div>
        </div>
 
    </div>
 
    <!-- ── BOTTOM ROW: Top Selling + Low Stock ───────────── -->
    <div class="two-col">
 
        <!-- Top Selling Products -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Top Selling Products</span>
                <span class="panel-badge">by units sold</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Sold</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    $rank_classes = ['gold', 'silver', 'bronze'];
                    while($row = $top_selling->fetch_assoc()):
                        $rc = $rank_classes[min($rank - 1, 2)];
                        $stock_class = ($row['stock_quantity'] == 0) ? 'stock-empty' : (($row['stock_quantity'] < 10) ? 'stock-low' : 'stock-ok');
                    ?>
                    <tr>
                        <td><span class="rank-num <?php echo $rc; ?>"><?php echo $rank; ?></span></td>
                        <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                        <td class="text-muted">₱<?php echo number_format($row['price'], 2); ?></td>
                        <td class="text-accent"><?php echo $row['total_sold']; ?> u</td>
                        <td class="<?php echo $stock_class; ?>"><?php echo $row['stock_quantity']; ?></td>
                    </tr>
                    <?php $rank++; endwhile; ?>
                </tbody>
            </table>
            <div style="padding: 14px 24px; border-top: 1px solid var(--border);">
                <a href="inventory.php" class="btn btn-ghost btn-sm">View Full Inventory →</a>
            </div>
        </div>
 
        <!-- Low Stock Alerts Panel -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Low Stock Alerts</span>
                <span class="panel-badge" style="background: var(--danger-dim); color: var(--danger);"><?php echo $low_stock_count; ?> items</span>
            </div>
 
            <?php if($low_stock_count == 0): ?>
            <div class="panel-body" style="text-align: center; padding: 48px 24px;">
                <div style="color: var(--accent); font-size: 2rem; margin-bottom: 10px;">✓</div>
                <div style="color: var(--text-2); font-size: 0.82rem;">All stock levels are healthy.</div>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Qty Left</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = $low_stock_items->fetch_assoc()):
                        $stock = $item['stock_quantity'];
                        $status = ($stock == 0) ? 'OUT' : 'LOW';
                        $cls    = ($stock == 0) ? 'stock-empty' : 'stock-low';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                        <td class="text-muted"><?php echo htmlspecialchars($item['category_name'] ?? '—'); ?></td>
                        <td class="<?php echo $cls; ?>"><?php echo $stock; ?> units</td>
                        <td><span style="font-size:0.65rem; padding: 2px 8px; border-radius: 20px; background: <?php echo ($stock == 0) ? 'var(--danger-dim)' : 'var(--warn-dim)'; ?>; color: <?php echo ($stock == 0) ? 'var(--danger)' : 'var(--warn)'; ?>;"><?php echo $status; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div style="padding: 14px 24px; border-top: 1px solid var(--border);">
                <a href="inventory.php" class="btn btn-warn btn-sm">Go to Restock →</a>
            </div>
            <?php endif; ?>
        </div>
 
    </div><!-- /.two-col -->
 
</div><!-- /.main -->
</body>
</html>
 
