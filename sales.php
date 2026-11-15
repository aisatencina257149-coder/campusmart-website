<?php
require_once 'includes/config.php';
requireLogin();

// CSRF protection for admin-only transaction deletion
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

$page = 'sales';
$pageTitle = 'Sales History';

// View single sale
$viewSale = null;
$viewItems = [];
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name as cashier_name 
        FROM sales s JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?
    ");
    $stmt->execute([(int)$_GET['id']]);
    $viewSale = $stmt->fetch();
    if ($viewSale) {
        $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $stmt->execute([$viewSale['id']]);
        $viewItems = $stmt->fetchAll();
    }
}

// Filters
$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['to'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT s.*, u.full_name as cashier_name 
    FROM sales s 
    JOIN users u ON s.user_id = u.id 
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
";
$params = [$dateFrom, $dateTo];

if ($search) {
    $sql .= " AND (s.invoice_number LIKE ? OR s.customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY s.sale_date DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Totals for period
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as total FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
$stmt->execute([$dateFrom, $dateTo]);
$periodStats = $stmt->fetch();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-receipt"></i> Sales History</h2>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-2">
                <label class="form-label small mb-0">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice or customer..." value="<?php echo sanitize($search); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-3 d-flex align-items-end justify-content-end">
                <div class="text-end">
                    <small class="text-muted d-block">Period Total</small>
                    <strong class="text-success"><?php echo formatCurrency($periodStats['total']); ?></strong>
                    <small class="text-muted">(<?php echo $periodStats['cnt']; ?> sales)</small>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="<?php echo $viewSale ? 'col-lg-7' : 'col-12'; ?>">
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Transaction deleted successfully and product stock was restored.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Cashier</th>
                                <th>Customer</th>
                                <th class="text-end">Total</th>
                                <th>Payment</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No sales found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sales as $s): ?>
                                <tr class="<?php echo ($viewSale && $viewSale['id'] == $s['id']) ? 'table-primary' : ''; ?>">
                                    <td><code><?php echo sanitize($s['invoice_number']); ?></code></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($s['sale_date'])); ?></td>
                                    <td><?php echo sanitize($s['cashier_name']); ?></td>
                                    <td><?php echo sanitize($s['customer_name']); ?></td>
                                    <td class="text-end fw-semibold"><?php echo formatCurrency($s['total_amount']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($s['payment_method']); ?></span></td>
                                    <td class="text-nowrap">
                                        <a href="?id=<?php echo (int)$s['id']; ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>" class="btn btn-sm btn-outline-primary" title="View transaction">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($isAdmin): ?>
                                            <form method="POST" action="/pos_system/delete_sale.php" class="d-inline"
                                                  onsubmit="return confirm('Delete this transaction? Its sold quantities will be returned to inventory. This cannot be undone.');">
                                                <input type="hidden" name="sale_id" value="<?php echo (int)$s['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete transaction">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($viewSale): ?>
    <div class="col-lg-5">
        <div class="card sticky-top" style="top:80px;">
            <div class="card-header d-flex justify-content-between">
                <span>Sale Details</span>
                <a href="/pos_system/sales.php?from=<?php echo $dateFrom; ?>&to=<?php echo $dateTo; ?>" class="btn btn-sm btn-outline-secondary">Close</a>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Invoice:</strong> <?php echo sanitize($viewSale['invoice_number']); ?><br>
                    <strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($viewSale['sale_date'])); ?><br>
                    <strong>Cashier:</strong> <?php echo sanitize($viewSale['cashier_name']); ?><br>
                    <strong>Customer:</strong> <?php echo sanitize($viewSale['customer_name']); ?>
                </div>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viewItems as $item): ?>
                        <tr>
                            <td><?php echo sanitize($item['product_name']); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end"><?php echo formatCurrency($item['subtotal']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <hr>
                <div class="d-flex justify-content-between"><span>Subtotal</span><span><?php echo formatCurrency($viewSale['subtotal']); ?></span></div>
                <div class="d-flex justify-content-between"><span>Discount</span><span>-<?php echo formatCurrency($viewSale['discount_amount']); ?></span></div>
                <div class="d-flex justify-content-between"><span>Tax</span><span><?php echo formatCurrency($viewSale['tax_amount']); ?></span></div>
                <div class="d-flex justify-content-between fs-5 fw-bold text-primary mt-2">
                    <span>Total</span><span><?php echo formatCurrency($viewSale['total_amount']); ?></span>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span>Paid (<?php echo ucfirst($viewSale['payment_method']); ?>)</span>
                    <span><?php echo formatCurrency($viewSale['amount_paid']); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Change</span>
                    <span><?php echo formatCurrency($viewSale['change_amount']); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
