<?php
require_once 'includes/config.php';
requireLogin();
$page = 'dashboard';
$pageTitle = 'Dashboard';

// Stats
$today = date('Y-m-d');

// Today's sales total
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count FROM sales WHERE DATE(sale_date) = ?");
$stmt->execute([$today]);
$todaySales = $stmt->fetch();

// Total products
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

// Low stock items
$lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level AND is_active = 1")->fetchColumn();

// Total sales all time
$allTimeSales = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales")->fetchColumn();

// Recent sales
$recentSales = $pdo->query("
    SELECT s.*, u.full_name as cashier_name 
    FROM sales s 
    JOIN users u ON s.user_id = u.id 
    ORDER BY s.sale_date DESC 
    LIMIT 8
")->fetchAll();

// Top products today
$topProducts = $pdo->prepare("
    SELECT si.product_name, SUM(si.quantity) as qty_sold, SUM(si.subtotal) as revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) = ?
    GROUP BY si.product_id, si.product_name
    ORDER BY qty_sold DESC
    LIMIT 5
");
$topProducts->execute([$today]);
$topProducts = $topProducts->fetchAll();

// Low stock list
$lowStockItems = $pdo->query("
    SELECT p.name, p.stock_quantity, p.min_stock_level, c.name as category
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock_quantity <= p.min_stock_level AND p.is_active = 1
    ORDER BY p.stock_quantity ASC
    LIMIT 5
")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Dashboard</h2>
        <small class="text-muted">Welcome back, <?php echo sanitize($_SESSION['full_name']); ?>!</small>
    </div>
    <a href="/pos_system/pos.php" class="btn btn-primary btn-lg">
        <i class="bi bi-cart-plus"></i> New Sale
    </a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Today's Sales</h6>
                    <h3 class="mb-0 text-success"><?php echo formatCurrency($todaySales['total']); ?></h3>
                    <small class="text-muted"><?php echo $todaySales['count']; ?> transactions</small>
                </div>
                <div class="stat-icon text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">All-Time Sales</h6>
                    <h3 class="mb-0 text-primary"><?php echo formatCurrency($allTimeSales); ?></h3>
                    <small class="text-muted">Total revenue</small>
                </div>
                <div class="stat-icon text-primary">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Active Products</h6>
                    <h3 class="mb-0"><?php echo $totalProducts; ?></h3>
                    <small class="text-muted">In inventory</small>
                </div>
                <div class="stat-icon text-info">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Low Stock Items</h6>
                    <h3 class="mb-0 <?php echo $lowStock > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo $lowStock; ?>
                    </h3>
                    <small class="text-muted">Need attention</small>
                </div>
                <div class="stat-icon <?php echo $lowStock > 0 ? 'text-danger' : 'text-success'; ?>">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Sales -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history"></i> Recent Sales</span>
                <a href="/pos_system/sales.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Cashier</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No sales yet today.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><a href="/pos_system/sales.php?id=<?php echo $sale['id']; ?>"><?php echo sanitize($sale['invoice_number']); ?></a></td>
                                    <td><?php echo sanitize($sale['cashier_name']); ?></td>
                                    <td class="fw-semibold"><?php echo formatCurrency($sale['total_amount']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($sale['payment_method']); ?></span></td>
                                    <td><?php echo date('M d, h:i A', strtotime($sale['sale_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Side panels -->
    <div class="col-lg-5">
        <!-- Top Products -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-trophy"></i> Top Products Today</div>
            <div class="card-body p-0">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted text-center py-3 mb-0">No sales data yet.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topProducts as $i => $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <span class="badge bg-primary me-2"><?php echo $i + 1; ?></span>
                                <?php echo sanitize($p['product_name']); ?>
                            </span>
                            <span>
                                <span class="badge bg-success"><?php echo $p['qty_sold']; ?> sold</span>
                                <small class="text-muted ms-1"><?php echo formatCurrency($p['revenue']); ?></small>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="card">
            <div class="card-header text-danger"><i class="bi bi-exclamation-circle"></i> Low Stock Alert</div>
            <div class="card-body p-0">
                <?php if (empty($lowStockItems)): ?>
                    <p class="text-success text-center py-3 mb-0"><i class="bi bi-check-circle"></i> All items sufficiently stocked.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($lowStockItems as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo sanitize($item['name']); ?></strong><br>
                                <small class="text-muted"><?php echo sanitize($item['category'] ?? 'Uncategorized'); ?></small>
                            </div>
                            <span class="badge bg-danger">
                                <?php echo $item['stock_quantity']; ?> left
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
