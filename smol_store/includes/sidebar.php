<?php
// Get current page for active state highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">CornerStop</div>
            <div class="brand-sub">Operations Suite</div>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
        <div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <div class="user-role">Administrator</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            <span>Dashboard</span>
            <?php if($current_page == 'dashboard.php'): ?><div class="nav-pip"></div><?php endif; ?>
        </a>

        <div class="nav-label" style="margin-top: 18px;">Operations</div>
        <a href="inventory.php" class="nav-item <?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
            <span>Inventory</span>
            <?php if($current_page == 'inventory.php'): ?><div class="nav-pip"></div><?php endif; ?>
        </a>
        <a href="sales.php" class="nav-item <?php echo ($current_page == 'sales.php') ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
            <span>Sales</span>
            <?php if($current_page == 'sales.php'): ?><div class="nav-pip"></div><?php endif; ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Sign Out
        </a>
    </div>
</aside>
