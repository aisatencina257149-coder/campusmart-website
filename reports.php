<?php
require_once 'includes/config.php';
requireAdmin();
$page = 'reports';
$pageTitle = 'Reports';

$dateFrom = $_GET['from'] ?? date('Y-m-01'); // Start of month
$dateTo = $_GET['to'] ?? date('Y-m-d');

// Sales summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(discount_amount), 0) as total_discount,
        COALESCE(AVG(total_amount), 0) as avg_sale
    FROM sales 
    WHERE DATE(sale_date) BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$summary = $stmt->fetch();

// Daily sales
$stmt = $pdo->prepare("
    SELECT DATE(sale_date) as day, COUNT(*) as transactions, SUM(total_amount) as revenue
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
    GROUP BY DATE(sale_date)
    ORDER BY day
");
$stmt->execute([$dateFrom, $dateTo]);
$dailySales = $stmt->fetchAll();

// Top products
$stmt = $pdo->prepare("
    SELECT si.product_name, SUM(si.quantity) as qty, SUM(si.subtotal) as revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY si.product_id, si.product_name
    ORDER BY qty DESC
    LIMIT 10
");
$stmt->execute([$dateFrom, $dateTo]);
$topProducts = $stmt->fetchAll();

// Payment methods
$stmt = $pdo->prepare("
    SELECT payment_method, COUNT(*) as cnt, SUM(total_amount) as total
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
    GROUP BY payment_method
");
$stmt->execute([$dateFrom, $dateTo]);
$payments = $stmt->fetchAll();

// Sales by cashier
$stmt = $pdo->prepare("
    SELECT u.full_name, COUNT(*) as transactions, SUM(s.total_amount) as revenue
    FROM sales s
    JOIN users u ON s.user_id = u.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY s.user_id, u.full_name
    ORDER BY revenue DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$byCashier = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-graph-up"></i> Reports</h2>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-0">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Generate Report</button>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Revenue</h6>
                <h3 class="text-success mb-0"><?php echo formatCurrency($summary['total_revenue']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Transactions</h6>
                <h3 class="mb-0"><?php echo number_format($summary['total_transactions']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Average Sale</h6>
                <h3 class="mb-0"><?php echo formatCurrency($summary['avg_sale']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Discounts</h6>
                <h3 class="text-warning mb-0"><?php echo formatCurrency($summary['total_discount']); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Daily Sales -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Daily Sales</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="text-center">Transactions</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dailySales)): ?>
                                <tr><td colspan="3" class="text-center text-muted">No data</td></tr>
                            <?php else: ?>
                                <?php foreach ($dailySales as $d): ?>
                                <tr>
                                    <td><?php echo date('M d, Y (D)', strtotime($d['day'])); ?></td>
                                    <td class="text-center"><?php echo $d['transactions']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($d['revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Top Selling Products</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th class="text-center">Qty Sold</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topProducts)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                            <?php else: ?>
                                <?php foreach ($topProducts as $i => $p): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo sanitize($p['product_name']); ?></td>
                                    <td class="text-center"><?php echo $p['qty']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($p['revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Payment Methods</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th class="text-center">Count</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?php echo ucfirst($p['payment_method']); ?></td>
                                <td class="text-center"><?php echo $p['cnt']; ?></td>
                                <td class="text-end"><?php echo formatCurrency($p['total']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- By Cashier -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Sales by Cashier</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Cashier</th>
                                <th class="text-center">Transactions</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byCashier as $c): ?>
                            <tr>
                                <td><?php echo sanitize($c['full_name']); ?></td>
                                <td class="text-center"><?php echo $c['transactions']; ?></td>
                                <td class="text-end"><?php echo formatCurrency($c['revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
