<?php
// migrate_stock_to_variants.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin
if (!$app->isLoggedIn() || !$app->isAdmin()) {
    die("Access denied");
}

echo "<h2>Stock Migration to Variants</h2>";
echo "<pre>";

// 1. Check products without variants
$productsWithoutVariants = $db->query("
    SELECT p.id, p.name, p.sku, p.price, p.stock_quantity 
    FROM products p 
    LEFT JOIN product_variants pv ON p.id = pv.product_id 
    WHERE pv.id IS NULL AND p.stock_quantity > 0
")->fetchAll();

echo "Products without variants: " . count($productsWithoutVariants) . "\n";

foreach ($productsWithoutVariants as $product) {
    echo "Creating default variant for: {$product['name']} (Stock: {$product['stock_quantity']})\n";
    
    $stmt = $db->prepare("
        INSERT INTO product_variants 
        (product_id, sku, price, stock_quantity, is_default, size, color)
        VALUES (?, ?, ?, ?, 1, 'Default', 'Default')
    ");
    
    $variantSku = $product['sku'] ? $product['sku'] . '-DEFAULT' : 'PROD-' . $product['id'] . '-DEFAULT';
    
    $stmt->execute([
        $product['id'],
        $variantSku,
        $product['price'] ?? 0,
        $product['stock_quantity']
    ]);
}

// 2. Check products with variants but product stock > 0
$productsWithStock = $db->query("
    SELECT p.id, p.name, p.stock_quantity, 
           COUNT(pv.id) as variant_count,
           SUM(pv.stock_quantity) as total_variant_stock
    FROM products p 
    LEFT JOIN product_variants pv ON p.id = pv.product_id 
    WHERE p.stock_quantity > 0
    GROUP BY p.id
    HAVING variant_count > 0
")->fetchAll();

echo "\nProducts with both product stock and variants: " . count($productsWithStock) . "\n";

foreach ($productsWithStock as $product) {
    echo "Migrating stock for: {$product['name']}\n";
    echo "  Product stock: {$product['stock_quantity']}\n";
    echo "  Total variant stock before: " . ($product['total_variant_stock'] ?? 0) . "\n";
    
    // Add product stock to the default variant or first variant
    $defaultVariant = $db->prepare("
        SELECT id FROM product_variants 
        WHERE product_id = ? 
        ORDER BY is_default DESC, id ASC 
        LIMIT 1
    ");
    $defaultVariant->execute([$product['id']]);
    $variantId = $defaultVariant->fetchColumn();
    
    if ($variantId) {
        $update = $db->prepare("
            UPDATE product_variants 
            SET stock_quantity = stock_quantity + ? 
            WHERE id = ?
        ");
        $update->execute([$product['stock_quantity'], $variantId]);
        echo "  Added stock to variant ID: {$variantId}\n";
    }
}

echo "\nMigration completed!\n";
echo "</pre>";

// 3. Verify migration
echo "<h3>Verification:</h3>";
$verification = $db->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(p.stock_quantity) as total_product_stock,
        SUM(pv.stock_quantity) as total_variant_stock,
        SUM(CASE WHEN p.stock_quantity > 0 THEN 1 ELSE 0 END) as products_with_stock
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
")->fetch();

echo "<pre>";
print_r($verification);
echo "</pre>";

echo "<p><strong>Note:</strong> After verifying the migration is correct, run this SQL to remove the product stock column:</p>";
echo "<code>ALTER TABLE products DROP COLUMN stock_quantity;</code>";
?>