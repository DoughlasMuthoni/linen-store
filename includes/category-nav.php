<?php
// /linen-closet/includes/category-nav.php

$app = new App();
$db = $app->getDB();

// Get main categories with their subcategories
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.name,
        c.slug,
        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as product_count
    FROM categories c
    WHERE c.parent_id IS NULL AND c.is_active = 1
    ORDER BY c.name
");

$stmt->execute();
$mainCategories = $stmt->fetchAll();

// Get subcategories for each main category
foreach ($mainCategories as &$category) {
    $subStmt = $db->prepare("
        SELECT 
            id,
            name,
            slug,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = categories.id AND p.is_active = 1) as product_count
        FROM categories
        WHERE parent_id = ? AND is_active = 1
        ORDER BY name
    ");
    $subStmt->execute([$category['id']]);
    $category['subcategories'] = $subStmt->fetchAll();
}
?>

<!-- Category Navigation -->
<div class="category-nav mb-5">
    <h3 class="h4 mb-4">Shop by Category</h3>
    <div class="row g-3">
        <?php foreach ($mainCategories as $category): ?>
            <div class="col-md-4">
                <div class="card category-card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <a href="<?php echo SITE_URL; ?>products?category=<?php echo $category['slug']; ?>" 
                               class="text-dark text-decoration-none">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                            <span class="badge bg-light text-dark ms-2"><?php echo $category['product_count']; ?></span>
                        </h5>
                        
                        <?php if (!empty($category['subcategories'])): ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($category['subcategories'] as $subcategory): ?>
                                    <li class="mb-1">
                                        <a href="<?php echo SITE_URL; ?>products?category=<?php echo $category['slug']; ?>&subcategory=<?php echo $subcategory['slug']; ?>"
                                           class="text-muted text-decoration-none">
                                            <?php echo htmlspecialchars($subcategory['name']); ?>
                                            <small class="text-muted">(<?php echo $subcategory['product_count']; ?>)</small>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>