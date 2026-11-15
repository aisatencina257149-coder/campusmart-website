<?php
require_once 'includes/config.php';
requireLogin();
$page = 'pos';
$pageTitle = 'Point of Sale';

// Get products with stock
$products = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 AND p.stock_quantity > 0
    ORDER BY p.name
")->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

include 'includes/header.php';
?>

<div class="row g-3">
    <!-- Product Selection -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="productSearch" class="form-control" placeholder="Search product or barcode...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="categoryFilter" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="badge bg-secondary" id="productCount"><?php echo count($products); ?> items</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="product-grid" id="productGrid">
                    <?php foreach ($products as $p): ?>
                    <div class="card product-card" 
                         data-id="<?php echo $p['id']; ?>"
                         data-name="<?php echo sanitize($p['name']); ?>"
                         data-price="<?php echo $p['price']; ?>"
                         data-stock="<?php echo $p['stock_quantity']; ?>"
                         data-category="<?php echo $p['category_id']; ?>"
                         data-barcode="<?php echo sanitize($p['barcode'] ?? ''); ?>"
                         onclick="addToCart(this)">
                        <div class="card-body p-2 text-center">
                            <div class="small text-muted mb-1"><?php echo sanitize($p['barcode'] ?? ''); ?></div>
                            <div class="fw-semibold small text-truncate" title="<?php echo sanitize($p['name']); ?>">
                                <?php echo sanitize($p['name']); ?>
                            </div>
                            <div class="price mt-1"><?php echo formatCurrency($p['price']); ?></div>
                            <div class="small text-muted">Stock: <?php echo $p['stock_quantity']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($products)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-box-seam" style="font-size: 3rem;"></i>
                        <p class="mt-2">No products available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cart & Checkout -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart3"></i> Current Sale</span>
                <button class="btn btn-sm btn-outline-danger" onclick="clearCart()" title="Clear cart">
                    <i class="bi bi-trash"></i> Clear
                </button>
            </div>
            <div class="card-body d-flex flex-column p-0">
                <div class="table-responsive flex-grow-1" style="max-height: 320px; overflow-y: auto;">
                    <table class="table table-sm cart-table mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th>Item</th>
                                <th class="text-center" style="width:110px;">Qty</th>
                                <th class="text-end">Subtotal</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr id="emptyCartRow">
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-cart"></i> Cart is empty. Click a product to add.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cart-summary border-top p-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Subtotal</span>
                        <span id="subtotalDisplay">₱0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Discount</span>
                        <div class="input-group input-group-sm" style="width:120px;">
                            <span class="input-group-text">₱</span>
                            <input type="number" id="discountInput" class="form-control text-end" value="0" min="0" step="0.01" onchange="updateTotals()">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (<?php echo (TAX_RATE * 100); ?>%)</span>
                        <span id="taxDisplay">₱0.00</span>
                    </div>
                    <div class="d-flex justify-content-between fs-4 fw-bold text-primary mb-3">
                        <span>TOTAL</span>
                        <span id="totalDisplay">₱0.00</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Customer Name (optional)</label>
                        <input type="text" id="customerName" class="form-control form-control-sm" placeholder="Walk-in Customer">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small">Payment Method</label>
                            <select id="paymentMethod" class="form-select form-select-sm">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="gcash">GCash</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Amount Paid</label>
                            <input type="number" id="amountPaid" class="form-control form-control-sm" min="0" step="0.01" onchange="updateChange()">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-semibold">Change</span>
                        <span id="changeDisplay" class="fw-bold text-success">₱0.00</span>
                    </div>

                    <button id="checkoutBtn" class="btn btn-success w-100 btn-lg" onclick="processSale()" disabled>
                        <i class="bi bi-check-circle"></i> Complete Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt"></i> Sale Complete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="receipt" id="receiptContent">
                    <!-- Filled by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="clearCart()">
                    New Sale
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const TAX_RATE = <?php echo TAX_RATE; ?>;
let cart = []; // {id, name, price, qty, stock}

function addToCart(el) {
    const id = parseInt(el.dataset.id);
    const name = el.dataset.name;
    const price = parseFloat(el.dataset.price);
    const stock = parseInt(el.dataset.stock);

    const existing = cart.find(item => item.id === id);
    if (existing) {
        if (existing.qty >= stock) {
            showToast('Not enough stock!', 'error');
            return;
        }
        existing.qty++;
    } else {
        cart.push({ id, name, price, qty: 1, stock });
    }
    renderCart();
}

function changeQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        cart = cart.filter(i => i.id !== id);
    } else if (newQty > item.stock) {
        showToast('Not enough stock!', 'error');
        return;
    } else {
        item.qty = newQty;
    }
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function clearCart() {
    cart = [];
    document.getElementById('discountInput').value = 0;
    document.getElementById('amountPaid').value = '';
    document.getElementById('customerName').value = '';
    renderCart();
}

function renderCart() {
    const tbody = document.getElementById('cartBody');
    const emptyRow = document.getElementById('emptyCartRow');

    if (cart.length === 0) {
        tbody.innerHTML = `<tr id="emptyCartRow">
            <td colspan="4" class="text-center text-muted py-4">
                <i class="bi bi-cart"></i> Cart is empty. Click a product to add.
            </td>
        </tr>`;
        document.getElementById('checkoutBtn').disabled = true;
        updateTotals();
        return;
    }

    let html = '';
    cart.forEach(item => {
        const sub = item.price * item.qty;
        html += `
            <tr>
                <td>
                    <div class="fw-semibold small">${item.name}</div>
                    <div class="text-muted" style="font-size:0.75rem;">${formatMoney(item.price)} each</div>
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary qty-btn" onclick="changeQty(${item.id}, -1)">−</button>
                        <span class="btn btn-light disabled" style="min-width:36px;">${item.qty}</span>
                        <button class="btn btn-outline-secondary qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
                    </div>
                </td>
                <td class="text-end fw-semibold">${formatMoney(sub)}</td>
                <td>
                    <button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(${item.id})">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
    document.getElementById('checkoutBtn').disabled = false;
    updateTotals();
}

function updateTotals() {
    const subtotal = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxable = Math.max(0, subtotal - discount);
    const tax = taxable * TAX_RATE;
    const total = taxable + tax;

    document.getElementById('subtotalDisplay').textContent = formatMoney(subtotal);
    document.getElementById('taxDisplay').textContent = formatMoney(tax);
    document.getElementById('totalDisplay').textContent = formatMoney(total);

    // Auto-fill amount paid if empty
    const paidInput = document.getElementById('amountPaid');
    if (!paidInput.value || parseFloat(paidInput.value) < total) {
        paidInput.value = total.toFixed(2);
    }
    updateChange();
}

function updateChange() {
    const totalText = document.getElementById('totalDisplay').textContent.replace(/[₱,]/g, '');
    const total = parseFloat(totalText) || 0;
    const paid = parseFloat(document.getElementById('amountPaid').value) || 0;
    const change = Math.max(0, paid - total);
    document.getElementById('changeDisplay').textContent = formatMoney(change);
}

async function processSale() {
    if (cart.length === 0) return;

    const subtotal = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxable = Math.max(0, subtotal - discount);
    const tax = taxable * TAX_RATE;
    const total = taxable + tax;
    const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;

    if (amountPaid < total) {
        showToast('Amount paid is less than total!', 'error');
        return;
    }

    const payload = {
        items: cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.price, product_name: i.name })),
        customer_name: document.getElementById('customerName').value || 'Walk-in Customer',
        subtotal: subtotal,
        discount_amount: discount,
        tax_amount: tax,
        total_amount: total,
        payment_method: document.getElementById('paymentMethod').value,
        amount_paid: amountPaid,
        change_amount: amountPaid - total
    };

    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

    try {
        const res = await fetch('/pos_system/api/process_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showReceipt(data);
            // Update product stock displays
            cart.forEach(item => {
                const card = document.querySelector(`.product-card[data-id="${item.id}"]`);
                if (card) {
                    const newStock = item.stock - item.qty;
                    card.dataset.stock = newStock;
                    const stockEl = card.querySelector('.small.text-muted:last-child');
                    if (stockEl) stockEl.textContent = 'Stock: ' + newStock;
                    if (newStock <= 0) card.style.display = 'none';
                }
            });
            showToast('Sale completed successfully!');
        } else {
            showToast(data.message || 'Failed to process sale', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Sale';
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Sale';
    }
}

function showReceipt(data) {
    const itemsHtml = data.items.map(i => `
        <div class="receipt-item">
            <span>${i.qty} x ${i.name}</span>
            <span>${formatMoney(i.subtotal)}</span>
        </div>
    `).join('');

    document.getElementById('receiptContent').innerHTML = `
        <div class="receipt-header">
            <strong><?php echo APP_NAME; ?></strong><br>
            School Canteen / Campus Store<br>
            <small>${new Date().toLocaleString()}</small>
        </div>
        <div><strong>Invoice:</strong> ${data.invoice_number}</div>
        <div><strong>Cashier:</strong> <?php echo sanitize($_SESSION['full_name']); ?></div>
        <div><strong>Customer:</strong> ${data.customer_name}</div>
        <hr style="border-style:dashed;">
        ${itemsHtml}
        <div class="receipt-total">
            <div class="receipt-item"><span>Subtotal</span><span>${formatMoney(data.subtotal)}</span></div>
            <div class="receipt-item"><span>Discount</span><span>-${formatMoney(data.discount_amount)}</span></div>
            <div class="receipt-item"><span>Tax</span><span>${formatMoney(data.tax_amount)}</span></div>
            <div class="receipt-item"><span>TOTAL</span><span>${formatMoney(data.total_amount)}</span></div>
            <div class="receipt-item"><span>Paid (${data.payment_method})</span><span>${formatMoney(data.amount_paid)}</span></div>
            <div class="receipt-item"><span>Change</span><span>${formatMoney(data.change_amount)}</span></div>
        </div>
        <div class="text-center mt-3">
            <small>Thank you for your purchase!</small>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    modal.show();

    // Reset button after modal
    document.getElementById('checkoutBtn').disabled = false;
    document.getElementById('checkoutBtn').innerHTML = '<i class="bi bi-check-circle"></i> Complete Sale';
}

// Search & filter
document.getElementById('productSearch').addEventListener('input', filterProducts);
document.getElementById('categoryFilter').addEventListener('change', filterProducts);

function filterProducts() {
    const search = document.getElementById('productSearch').value.toLowerCase();
    const cat = document.getElementById('categoryFilter').value;
    const cards = document.querySelectorAll('.product-card');
    let visible = 0;

    cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const barcode = (card.dataset.barcode || '').toLowerCase();
        const category = card.dataset.category;
        const matchSearch = name.includes(search) || barcode.includes(search);
        const matchCat = !cat || category === cat;
        const show = matchSearch && matchCat && parseInt(card.dataset.stock) > 0;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('productCount').textContent = visible + ' items';
}

// Keyboard support for barcode scanners (enter on search)
document.getElementById('productSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const val = this.value.trim();
        const card = document.querySelector(`.product-card[data-barcode="${val}"]`);
        if (card && card.style.display !== 'none') {
            addToCart(card);
            this.value = '';
            filterProducts();
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
