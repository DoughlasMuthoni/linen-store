<?php
// /linen-closet/admin/dashboard.php

// Include the admin layout function
require_once __DIR__ . '/layout.php';

// Include necessary files (already included in layout.php, but keep for clarity)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin (already done in layout.php, but keep as backup)
if (!$app->isLoggedIn() || !$app->isAdmin()) {
    $app->redirect('auth/login');
}

// Get dashboard stats
$stats = [
    'total_orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'total_products' => $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn(),
    'total_customers' => $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn(),
    'total_revenue' => $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn(),
    'pending_orders' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'low_stock_products' => $db->query("
        SELECT COUNT(DISTINCT p.id) 
        FROM products p 
        LEFT JOIN product_variants pv ON p.id = pv.product_id 
        WHERE p.is_active = 1 
        AND (p.stock_quantity <= 10 OR pv.stock_quantity <= 10)
    ")->fetchColumn(),
];

// Get recent orders
$recentOrders = $db->query("
    SELECT 
        o.id,
        o.order_number,
        o.total_amount,
        o.status,
        o.payment_status,
        o.created_at,
        u.first_name,
        u.last_name,
        u.email
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll();

// Get top selling products
$topProducts = $db->query("
    SELECT 
        p.id,
        p.name,
        p.slug,
        p.price,
        p.stock_quantity,
        pi.image_url,
        COALESCE(SUM(oi.quantity), 0) as total_sold,
        COALESCE(SUM(oi.total_price), 0) as revenue
    FROM products p
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'paid'
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();

// Get sales data for chart (last 7 days)
$salesData = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as orders,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND payment_status = 'paid'
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll();

// Prepare data for chart
$chartLabels = [];
$chartOrders = [];
$chartRevenue = [];

foreach ($salesData as $data) {
    $chartLabels[] = date('M j', strtotime($data['date']));
    $chartOrders[] = $data['orders'];
    $chartRevenue[] = $data['revenue'];
}

// Build the content (as a string, not echoed)
$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Dashboard Overview</h1>
            <p class="text-muted mb-0">Welcome back, ' . $_SESSION['first_name'] . '! Here\'s what\'s happening with your store today.</p>
        </div>
        <div>
            <button class="btn btn-dark">
                <i class="fas fa-download me-2"></i> Export Report
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Revenue
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Ksh ' . number_format($stats['total_revenue'], 2) . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Orders
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['total_orders'] . '
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-danger mr-2">
                                    <i class="fas fa-clock"></i> ' . $stats['pending_orders'] . ' Pending
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Products
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['total_products'] . '
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-warning mr-2">
                                    <i class="fas fa-exclamation-triangle"></i> ' . $stats['low_stock_products'] . ' Low Stock
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Customers
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['total_customers'] . '
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-success mr-2">
                                    <i class="fas fa-user-plus"></i> 5 New Today
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sales Overview (Last 7 Days)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="salesChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Selling Products</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Sales</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>';
foreach ($topProducts as $product) {
    $content .= '
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="' . SITE_URL . ($product['image_url'] ?: 'assets/images/placeholder.jpg') . '" 
                                                 class="rounded me-2" 
                                                 style="width: 30px; height: 30px; object-fit: cover;">
                                            <div>
                                                <small class="d-block">' . htmlspecialchars($product['name']) . '</small>
                                                <small class="text-muted">Ksh ' . number_format($product['price'], 2) . '</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>' . $product['total_sold'] . '</td>
                                    <td>Ksh ' . number_format($product['revenue'], 2) . '</td>
                                </tr>';
}
$content .= '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
            <a href="' . SITE_URL . 'admin/orders" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
foreach ($recentOrders as $order) {
    $statusColors = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger'
    ];
    
    $paymentColors = [
        'pending' => 'warning',
        'paid' => 'success',
        'failed' => 'danger'
    ];
    
    $content .= '
                        <tr>
                            <td>
                                <a href="' . SITE_URL . 'admin/orders/view/' . $order['id'] . '" class="text-decoration-none">
                                    ' . htmlspecialchars($order['order_number']) . '
                                </a>
                            </td>
                            <td>
                                <div>
                                    <div>' . htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) . '</div>
                                    <small class="text-muted">' . htmlspecialchars($order['email']) . '</small>
                                </div>
                            </td>
                            <td>' . date('M j, Y', strtotime($order['created_at'])) . '</td>
                            <td>Ksh ' . number_format($order['total_amount'], 2) . '</td>
                            <td>
                                <span class="badge bg-' . ($statusColors[$order['status']] ?? 'secondary') . '">
                                    ' . ucfirst($order['status']) . '
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-' . ($paymentColors[$order['payment_status']] ?? 'secondary') . '">
                                    ' . ucfirst($order['payment_status']) . '
                                </span>
                            </td>
                            <td>
                                <a href="' . SITE_URL . 'admin/orders/view/' . $order['id'] . '" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>';
}
$content .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <div class="text-center">
                        <i class="fas fa-plus-circle fa-2x mb-3"></i>
                        <h5 class="card-title">Add Product</h5>
                        <p class="card-text">Add new product to your store</p>
                        <a href="' . SITE_URL . 'admin/products/add" class="btn btn-light">Go</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card bg-success text-white shadow">
                <div class="card-body">
                    <div class="text-center">
                        <i class="fas fa-chart-bar fa-2x mb-3"></i>
                        <h5 class="card-title">View Reports</h5>
                        <p class="card-text">Detailed sales analytics</p>
                        <a href="' . SITE_URL . 'admin/reports" class="btn btn-light">Go</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card bg-info text-white shadow">
                <div class="card-body">
                    <div class="text-center">
                        <i class="fas fa-tags fa-2x mb-3"></i>
                        <h5 class="card-title">Categories</h5>
                        <p class="card-text">Manage product categories</p>
                        <a href="' . SITE_URL . 'admin/categories" class="btn btn-light">Go</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card bg-warning text-white shadow">
                <div class="card-body">
                    <div class="text-center">
                        <i class="fas fa-cog fa-2x mb-3"></i>
                        <h5 class="card-title">Settings</h5>
                        <p class="card-text">Store configuration</p>
                        <a href="' . SITE_URL . 'admin/settings" class="btn btn-light">Go</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sales Chart
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("salesChart");
    if (ctx) {
        var salesChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: ' . json_encode($chartLabels) . ',
                datasets: [{
                    label: "Orders",
                    data: ' . json_encode($chartOrders) . ',
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "rgba(78, 115, 223, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    tension: 0.3
                }, {
                    label: "Revenue (Ksh)",
                    data: ' . json_encode($chartRevenue) . ',
                    backgroundColor: "rgba(28, 200, 138, 0.05)",
                    borderColor: "rgba(28, 200, 138, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(28, 200, 138, 1)",
                    pointBorderColor: "rgba(28, 200, 138, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(28, 200, 138, 1)",
                    pointHoverBorderColor: "rgba(28, 200, 138, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxTicksLimit: 7
                        }
                    },
                    y: {
                        ticks: {
                            maxTicksLimit: 5,
                            padding: 10
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            drawBorder: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: "top"
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        titleColor: "#858796",
                        borderColor: "#dddfeb",
                        borderWidth: 1,
                        padding: 15,
                        displayColors: false,
                        intersect: false,
                        mode: "index",
                        caretPadding: 10
                    }
                }
            }
        });
    }
});
</script>';

// Output the layout with the content
echo adminLayout($content, 'Dashboard');
?>