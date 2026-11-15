<?php
require_once 'includes/config.php';
requireLogin();
$page = 'products';
$pageTitle = 'Products';

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $barcode = trim($_POST['barcode'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
        $price = (float)($_POST['price'] ?? 0);
        $cost_price = (float)($_POST['cost_price'] ?? 0);
        $stock = (int)($_POST['stock_quantity'] ?? 0);
        $min_stock = (int)($_POST['min_stock_level'] ?? 5);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || $price <= 0) {
            $message = 'Name and valid price are required.';
            $messageType = 'danger';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (barcode, name, category_id, price, cost_price, stock_quantity, min_stock_level, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$barcode ?: null, $name, $category_id, $price, $cost_price, $stock, $min_stock, $is_active]);
                    $message = 'Product added successfully.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE products SET barcode=?, name=?, category_id=?, price=?, cost_price=?, 
                               stock_quantity=?, min_stock_level=?, is_active=?
                        WHERE id=?
                    ");
                    $stmt->execute([$barcode ?: null, $name, $category_id, $price, $cost_price, $stock, $min_stock, $is_active, $id]);
                    $message = 'Product updated successfully.';
                }
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error: ' . ($e->getCode() == 23000 ? 'Barcode already exists.' : $e->getMessage());
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Soft delete
        $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Product deactivated.';
        $messageType = 'success';
    } elseif ($action === 'add_category') {
        $catName = trim($_POST['cat_name'] ?? '');
        if ($catName) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$catName]);
                $message = 'Category added.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Category already exists.';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch data
$search = trim($_GET['search'] ?? '');
$catFilter = (int)($_GET['category'] ?? 0);

$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catFilter) {
    $sql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}
$sql .= " ORDER BY p.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// For edit modal
$editProduct = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editProduct = $stmt->fetch();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-box-seam"></i> Products</h2>
    <div>
        <button class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#categoryModal">
            <i class="bi bi-tags"></i> Categories
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add Product
        </button>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
    <?php echo sanitize($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or barcode..." value="<?php echo sanitize($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $catFilter == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="/pos_system/products.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Barcode</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><code><?php echo sanitize($p['barcode'] ?? '—'); ?></code></td>
                            <td>
                                <strong><?php echo sanitize($p['name']); ?></strong>
                                <?php if ($p['stock_quantity'] <= $p['min_stock_level']): ?>
                                    <span class="badge bg-danger ms-1">Low</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitize($p['category_name'] ?? '—'); ?></td>
                            <td class="text-end"><?php echo formatCurrency($p['price']); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $p['stock_quantity'] <= $p['min_stock_level'] ? 'bg-danger' : 'bg-success'; ?>">
                                    <?php echo $p['stock_quantity']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($p['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="?edit=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#productModal"
                                   onclick='loadEdit(<?php echo json_encode($p); ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($p['is_active']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this product?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="productId" value="">
                    <div class="mb-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="barcode" class="form-control" placeholder="Optional">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Selling Price *</label>
                            <input type="number" name="price" id="price" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Cost Price</label>
                            <input type="number" name="cost_price" id="cost_price" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock_quantity" id="stock_quantity" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Min Stock Level</label>
                            <input type="number" name="min_stock_level" id="min_stock_level" class="form-control" min="0" value="5">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" class="mb-3">
                    <input type="hidden" name="action" value="add_category">
                    <div class="input-group">
                        <input type="text" name="cat_name" class="form-control" placeholder="New category name" required>
                        <button class="btn btn-primary" type="submit">Add</button>
                    </div>
                </form>
                <ul class="list-group">
                    <?php foreach ($categories as $c): ?>
                        <li class="list-group-item"><?php echo sanitize($c['name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value = 'add';
    document.getElementById('productId').value = '';
    document.getElementById('barcode').value = '';
    document.getElementById('name').value = '';
    document.getElementById('category_id').value = '';
    document.getElementById('price').value = '';
    document.getElementById('cost_price').value = '';
    document.getElementById('stock_quantity').value = '0';
    document.getElementById('min_stock_level').value = '5';
    document.getElementById('is_active').checked = true;
}

function loadEdit(p) {
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('productId').value = p.id;
    document.getElementById('barcode').value = p.barcode || '';
    document.getElementById('name').value = p.name;
    document.getElementById('category_id').value = p.category_id || '';
    document.getElementById('price').value = p.price;
    document.getElementById('cost_price').value = p.cost_price;
    document.getElementById('stock_quantity').value = p.stock_quantity;
    document.getElementById('min_stock_level').value = p.min_stock_level;
    document.getElementById('is_active').checked = p.is_active == 1;
}

<?php if ($editProduct): ?>
document.addEventListener('DOMContentLoaded', function() {
    loadEdit(<?php echo json_encode($editProduct); ?>);
    new bootstrap.Modal(document.getElementById('productModal')).show();
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
