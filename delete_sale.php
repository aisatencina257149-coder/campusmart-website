<?php
require_once 'includes/config.php';
requireLogin();

// Only administrators may delete transaction records.
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied. Only administrators can delete transactions.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Verify the request came from the logged-in admin session.
$postedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!$sessionToken || !hash_equals($sessionToken, $postedToken)) {
    http_response_code(403);
    exit('Invalid security token. Refresh the Sales History page and try again.');
}

$saleId = filter_input(INPUT_POST, 'sale_id', FILTER_VALIDATE_INT);
if (!$saleId || $saleId < 1) {
    http_response_code(400);
    exit('Invalid transaction ID.');
}

try {
    $pdo->beginTransaction();

    // Lock and verify the transaction before changing stock.
    $check = $pdo->prepare('SELECT id FROM sales WHERE id = ? FOR UPDATE');
    $check->execute([$saleId]);
    if (!$check->fetchColumn()) {
        $pdo->rollBack();
        header('Location: /pos_system/sales.php');
        exit;
    }

    // Return sold quantities to stock. Grouping avoids problems if an item
    // appears more than once in the sale_items table.
    $restore = $pdo->prepare("
        UPDATE products p
        INNER JOIN (
            SELECT product_id, SUM(quantity) AS total_quantity
            FROM sale_items
            WHERE sale_id = ?
            GROUP BY product_id
        ) si ON si.product_id = p.id
        SET p.stock_quantity = p.stock_quantity + si.total_quantity
    ");
    $restore->execute([$saleId]);

    // Delete child rows before the parent sale row.
    $deleteItems = $pdo->prepare('DELETE FROM sale_items WHERE sale_id = ?');
    $deleteItems->execute([$saleId]);

    $deleteSale = $pdo->prepare('DELETE FROM sales WHERE id = ?');
    $deleteSale->execute([$saleId]);

    $pdo->commit();
    header('Location: /pos_system/sales.php?deleted=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete sale failed: ' . $e->getMessage());
    http_response_code(500);
    exit('The transaction could not be deleted. Check the PHP error log or database structure.');
}
