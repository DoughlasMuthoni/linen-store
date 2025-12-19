<?php
// /linen-closet/admin/reports.php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    $app->redirect('admin/login');
}

// Report filters with validation
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'sales';

// Validate dates
if (!strtotime($date_from) || !strtotime($date_to)) {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-t');
}

// Ensure date_from is before date_to
if (strtotime($date_from) > strtotime($date_to)) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Helper functions for reports
function fetchSalesReport($db, $date_from, $date_to) {
    try {
        $stmt = $db->prepare("
            SELECT 
                DATE(created_at) as order_date,
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_order_value,
                COUNT(DISTINCT user_id) as unique_customers
            FROM orders 
            WHERE created_at BETWEEN ? AND ?
            AND payment_status = 'paid'
            GROUP BY DATE(created_at)
            ORDER BY order_date DESC
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function fetchProductReport($db, $date_from, $date_to) {
    try {
        $stmt = $db->prepare("
            SELECT 
                p.id,
                p.name,
                p.sku,
                c.name as category,
                COALESCE(SUM(oi.quantity), 0) as total_sold,
                COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
                COUNT(DISTINCT oi.order_id) as order_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id 
                AND o.created_at BETWEEN ? AND ?
                AND o.payment_status = 'paid'
            WHERE p.is_active = 1
            GROUP BY p.id
            ORDER BY total_sold DESC
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function fetchCustomerReport($db, $date_from, $date_to) {
    try {
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.created_at as joined_date,
                COUNT(o.id) as total_orders,
                COALESCE(SUM(o.total_amount), 0) as total_spent,
                MAX(o.created_at) as last_order_date
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id 
                AND o.created_at BETWEEN ? AND ?
                AND o.payment_status = 'paid'
            WHERE u.is_admin = 0
            GROUP BY u.id
            ORDER BY total_spent DESC
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function fetchDailySalesData($db, $date_from, $date_to) {
    try {
        $stmt = $db->prepare("
            SELECT 
                DATE(created_at) as date,
                COALESCE(SUM(total_amount), 0) as sales
            FROM orders 
            WHERE created_at BETWEEN ? AND ?
            AND payment_status = 'paid'
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function fetchCategorySalesData($db, $date_from, $date_to) {
    try {
        $stmt = $db->prepare("
            SELECT 
                c.name as category,
                COALESCE(SUM(oi.quantity * oi.price), 0) as sales
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id 
                AND o.created_at BETWEEN ? AND ?
                AND o.payment_status = 'paid'
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY sales DESC
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get report data
switch($report_type) {
    case 'sales':
        $report_data = fetchSalesReport($db, $date_from, $date_to);
        $chart_title = 'Daily Sales Report';
        break;
    case 'products':
        $report_data = fetchProductReport($db, $date_from, $date_to);
        $chart_title = 'Product Performance Report';
        break;
    case 'customers':
        $report_data = fetchCustomerReport($db, $date_from, $date_to);
        $chart_title = 'Customer Analytics Report';
        break;
    default:
        $report_data = fetchSalesReport($db, $date_from, $date_to);
        $chart_title = 'Daily Sales Report';
}

// Get chart data
$daily_sales = fetchDailySalesData($db, $date_from, $date_to);
$category_sales = fetchCategorySalesData($db, $date_from, $date_to);

// Prepare chart data
$chart_labels = [];
$chart_data = [];
foreach($daily_sales as $day) {
    $chart_labels[] = date('M j', strtotime($day['date']));
    $chart_data[] = floatval($day['sales']);
}

// If no data, show placeholder
if (empty($chart_labels)) {
    $chart_labels[] = date('M j', strtotime($date_from));
    $chart_data[] = 0;
}

$category_labels = [];
$category_data = [];
$category_colors = [
    '#667eea', '#764ba2', '#f093fb', '#f5576c', 
    '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
    '#fa709a', '#fee140', '#a8edea'
];

foreach($category_sales as $index => $cat) {
    $category_labels[] = $cat['category'] ?: 'Uncategorized';
    $category_data[] = floatval($cat['sales']);
}

if (empty($category_labels)) {
    $category_labels[] = 'No Data';
    $category_data[] = 100;
}

// Calculate stats
$total_revenue = 0;
$total_orders = 0;

if ($report_type == 'sales') {
    foreach($report_data as $row) {
        $total_revenue += floatval($row['total_revenue']);
        $total_orders += intval($row['total_orders']);
    }
} else {
    // Get total revenue from daily sales
    foreach($daily_sales as $day) {
        $total_revenue += floatval($day['sales']);
    }
    // Get total orders count
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_orders 
            FROM orders 
            WHERE created_at BETWEEN ? AND ?
            AND payment_status = 'paid'
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        $result = $stmt->fetch();
        $total_orders = $result ? intval($result['total_orders']) : 0;
    } catch (Exception $e) {
        $total_orders = 0;
    }
}

$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

// Start output
ob_start();
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4>Reports & Analytics</h4>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="printReport()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <button class="btn btn-success" onclick="exportToCSV()">
                        <i class="fas fa-download me-2"></i>Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Revenue</h6>
                            <h3 class="mb-0">Ksh <?php echo number_format($total_revenue, 2); ?></h3>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Orders</h6>
                            <h3 class="mb-0"><?php echo number_format($total_orders); ?></h3>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Avg. Order Value</h6>
                            <h3 class="mb-0">Ksh <?php echo number_format($avg_order_value, 2); ?></h3>
                        </div>
                        <i class="fas fa-chart-line fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Date Range</h6>
                            <h6 class="mb-0"><?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?></h6>
                        </div>
                        <i class="fas fa-calendar-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="type" class="form-select">
                        <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                        <option value="products" <?php echo $report_type == 'products' ? 'selected' : ''; ?>>Product Report</option>
                        <option value="customers" <?php echo $report_type == 'customers' ? 'selected' : ''; ?>>Customer Report</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Report Charts -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><?php echo $chart_title; ?></h6>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Sales by Category</h6>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Data Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Report Data</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered data-table">
                    <thead>
                        <tr>
                            <?php
                            // Dynamic table headers based on report type
                            switch($report_type) {
                                case 'sales':
                                    echo '
                                    <th>Date</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                    <th>Avg. Order</th>
                                    <th>Customers</th>';
                                    break;
                                case 'products':
                                    echo '
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Sold</th>
                                    <th>Revenue</th>
                                    <th>Orders</th>';
                                    break;
                                case 'customers':
                                    echo '
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Joined</th>
                                    <th>Orders</th>
                                    <th>Total Spent</th>
                                    <th>Last Order</th>';
                                    break;
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (empty($report_data)) {
                            echo '<tr><td colspan="7" class="text-center text-muted py-4">No data found for the selected period.</td></tr>';
                        } else {
                            foreach($report_data as $row) {
                                echo '<tr>';
                                switch($report_type) {
                                    case 'sales':
                                        echo '
                                            <td>' . date('M j, Y', strtotime($row['order_date'])) . '</td>
                                            <td>' . $row['total_orders'] . '</td>
                                            <td>Ksh ' . number_format($row['total_revenue'], 2) . '</td>
                                            <td>Ksh ' . number_format($row['avg_order_value'], 2) . '</td>
                                            <td>' . $row['unique_customers'] . '</td>';
                                        break;
                                    case 'products':
                                        echo '
                                            <td>' . htmlspecialchars($row['name']) . '</td>
                                            <td>' . htmlspecialchars($row['sku']) . '</td>
                                            <td>' . htmlspecialchars($row['category'] ?? 'Uncategorized') . '</td>
                                            <td>' . $row['total_sold'] . '</td>
                                            <td>Ksh ' . number_format($row['revenue'], 2) . '</td>
                                            <td>' . $row['order_count'] . '</td>';
                                        break;
                                    case 'customers':
                                        $last_order = $row['last_order_date'] ? date('M j, Y', strtotime($row['last_order_date'])) : 'No orders';
                                        echo '
                                            <td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>
                                            <td>' . htmlspecialchars($row['email']) . '</td>
                                            <td>' . htmlspecialchars($row['phone']) . '</td>
                                            <td>' . date('M j, Y', strtotime($row['joined_date'])) . '</td>
                                            <td>' . $row['total_orders'] . '</td>
                                            <td>Ksh ' . number_format($row['total_spent'], 2) . '</td>
                                            <td>' . $last_order . '</td>';
                                        break;
                                }
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Sales Chart
    var salesChart = new Chart($("#salesChart")[0].getContext("2d"), {
        type: "line",
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: "Sales Revenue (Ksh)",
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: "#667eea",
                backgroundColor: "rgba(102, 126, 234, 0.1)",
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: "top"
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        borderDash: [2]
                    },
                    ticks: {
                        callback: function(value) {
                            return "Ksh " + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Category Chart
    var categoryChart = new Chart($("#categoryChart")[0].getContext("2d"), {
        type: "doughnut",
        data: {
            labels: <?php echo json_encode($category_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($category_data); ?>,
                backgroundColor: <?php echo json_encode(array_slice($category_colors, 0, count($category_labels))); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: "right",
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || "";
                            let value = context.parsed || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = Math.round((value / total) * 100);
                            return label + ": Ksh " + value.toLocaleString() + " (" + percentage + "%)";
                        }
                    }
                }
            }
        }
    });
});

function printReport() {
    window.print();
}

function exportToCSV() {
    let csv = [];
    let rows = document.querySelectorAll("table.data-table tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText;
            data = data.replace(/"/g, '""'); // Escape double quotes
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(","));
    }

    // Download CSV file
    let csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    let encodedUri = encodeURI(csvContent);
    let link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "report_<?php echo $report_type; ?>_<?php echo date('Y-m-d'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
<?php
$content = ob_get_clean();
echo adminLayout($content, 'Reports & Analytics');
?>