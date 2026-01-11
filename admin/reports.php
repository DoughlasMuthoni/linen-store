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
        // Get ALL active products with their sales data
        $stmt = $db->prepare("
            SELECT 
                p.id,
                p.name,
                p.sku,
                COALESCE(c.name, 'Uncategorized') as category,  -- Ensure category is never NULL
                COALESCE(SUM(oi.quantity), 0) as total_sold,
                COALESCE(SUM(oi.total_price), 0) as revenue,
                COUNT(DISTINCT oi.order_id) as order_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id 
                AND o.created_at BETWEEN ? AND ?
                AND o.payment_status = 'paid'
            WHERE p.is_active = 1
            GROUP BY p.id, p.name, p.sku, c.name
            ORDER BY total_sold DESC, p.name ASC
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        $results = $stmt->fetchAll();
        
        return $results;
        
    } catch (Exception $e) {
        error_log("fetchProductReport error: " . $e->getMessage());
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
        // Get top-level categories (parent_id IS NULL or 0) and their sales
        $stmt = $db->prepare("
            SELECT 
                COALESCE(parent.name, c.name) as category_name,  -- CHANGED: category_name NOT category
                COALESCE(SUM(oi.total_price), 0) as sales
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN products p ON oi.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN categories parent ON c.parent_id = parent.id  -- Get parent category if exists
            WHERE o.created_at BETWEEN ? AND ?
            AND o.payment_status = 'paid'
            AND (c.is_active = 1 OR c.is_active IS NULL)
            GROUP BY COALESCE(parent.id, c.id), COALESCE(parent.name, c.name)
            ORDER BY sales DESC
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("fetchCategorySalesData error: " . $e->getMessage());
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
    
    // DEBUG: Add this code
    error_log("=== PRODUCT REPORT DEBUG ===");
    error_log("Date range: $date_from to $date_to");
    error_log("Report data count: " . count($report_data));
    
    // Check database directly
    try {
        // Test 1: Check active products
        $products_count = $db->query("SELECT COUNT(*) as cnt FROM products WHERE is_active = 1")->fetch();
        error_log("Active products: " . $products_count['cnt']);
        
        // Test 2: Check sales in date range
        $sales_count = $db->prepare("
            SELECT COUNT(DISTINCT oi.product_id) as products_with_sales
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at BETWEEN ? AND ?
            AND o.payment_status = 'paid'
        ");
        $sales_count->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        $sales_result = $sales_count->fetch();
        error_log("Products with sales in range: " . ($sales_result['products_with_sales'] ?? 0));
        
        // Test 3: Run the exact query to see what it returns
        $test_query = $db->prepare("
            SELECT 
                p.id,
                p.name,
                p.sku,
                COALESCE(c.name, 'Uncategorized') as category,
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
            LIMIT 5
        ");
        $test_query->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        $test_results = $test_query->fetchAll();
        error_log("Test query returned: " . count($test_results) . " rows");
        
        if (!empty($test_results)) {
            error_log("Sample data: " . print_r($test_results[0], true));
        }
        
    } catch (Exception $e) {
        error_log("Debug query error: " . $e->getMessage());
    }
    
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
    // Use 'category_name' instead of 'category'
    $category_labels[] = isset($cat['category_name']) && !empty($cat['category_name']) 
        ? $cat['category_name'] 
        : 'Uncategorized';
    $category_data[] = floatval($cat['sales'] ?? 0);
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
    <!-- Debug output (hidden) -->
<div style="display: none;">
    <?php
    // Debug: Check what data we have
    echo '<div id="debugData">';
    echo 'Report type: ' . $report_type . '<br>';
    echo 'Data count: ' . count($report_data) . '<br>';
    if (!empty($report_data)) {
        echo 'First row: ' . print_r($report_data[0], true) . '<br>';
    }
    echo '</div>';
    ?>
</div>
   <!-- Report Data Table -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Report Data</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="reportTable">
                <thead>
                    <tr>
                        <?php
                        // Dynamic table headers based on report type
                        // Also track column count for each report type
                        $colspan = 0; // Initialize variable
                        
                        switch($report_type) {
                            case 'sales':
                                echo '
                                <th>Date</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                                <th>Avg. Order</th>
                                <th>Customers</th>';
                                $colspan = 5;
                                break;
                            case 'products':
                                echo '
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                                <th>Orders</th>';
                                $colspan = 6; // FIXED: 6 columns, not 7
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
                                $colspan = 7;
                                break;
                        }
                        ?>
                    </tr>
                </thead>
               <tbody>
    <?php
    if (empty($report_data)) {
        // Dynamic colspan based on report type
        $colspan = 0;
        switch($report_type) {
            case 'sales': $colspan = 5; break;
            case 'products': $colspan = 6; break;
            case 'customers': $colspan = 7; break;
        }
        echo '<tr><td colspan="' . $colspan . '" class="text-center text-muted py-4">No data found for the selected period.</td></tr>';
    } else {
        foreach($report_data as $row) {
            echo '<tr>';
            switch($report_type) {
                case 'sales':
                    echo '
                        <td>' . (isset($row['order_date']) ? date('M j, Y', strtotime($row['order_date'])) : 'N/A') . '</td>
                        <td>' . ($row['total_orders'] ?? 0) . '</td>
                        <td>Ksh ' . number_format($row['total_revenue'] ?? 0, 2) . '</td>
                        <td>Ksh ' . number_format($row['avg_order_value'] ?? 0, 2) . '</td>
                        <td>' . ($row['unique_customers'] ?? 0) . '</td>';
                    break;
                case 'products':
                    echo '
                        <td>' . htmlspecialchars($row['name'] ?? 'Unknown') . '</td>
                        <td>' . htmlspecialchars($row['sku'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars(isset($row['category']) && !empty($row['category']) ? $row['category'] : 'Uncategorized') . '</td>
                        <td>' . intval($row['total_sold'] ?? 0) . '</td>
                        <td>Ksh ' . number_format(floatval($row['revenue'] ?? 0), 2) . '</td>
                        <td>' . intval($row['order_count'] ?? 0) . '</td>';
                    break;
                case 'customers':
                    $last_order = isset($row['last_order_date']) && !empty($row['last_order_date']) ? 
                        date('M j, Y', strtotime($row['last_order_date'])) : 'No orders';
                    echo '
                        <td>' . htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . '</td>
                        <td>' . htmlspecialchars($row['email'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['phone'] ?? '') . '</td>
                        <td>' . (isset($row['joined_date']) ? date('M j, Y', strtotime($row['joined_date'])) : 'N/A') . '</td>
                        <td>' . intval($row['total_orders'] ?? 0) . '</td>
                        <td>Ksh ' . number_format(floatval($row['total_spent'] ?? 0), 2) . '</td>
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
 <!-- jQuery MUST come first -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- DataTables (depends on jQuery) -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    // DEBUG: Check what data PHP sent
    console.log('=== DEBUG REPORT DATA ===');
    console.log('Report type from PHP:', '<?php echo addslashes($report_type); ?>');
    console.log('Report data count from PHP:', <?php echo count($report_data); ?>);
    
    // Check the hidden debug div
    var debugDiv = document.getElementById('debugData');
    if (debugDiv) {
        console.log('Debug data available');
    }
    
    // Log the actual table HTML structure
    var $table = $('#reportTable');
    
    // Check table rows and cells
    console.log('Total table rows:', $table.find('tr').length);
    console.log('Header cells:', $table.find('thead th').length);
    
    var $tbodyRows = $table.find('tbody tr');
    console.log('Tbody rows:', $tbodyRows.length);
    
    // Only initialize DataTables if we have actual data rows
    var hasDataRows = false;
    $tbodyRows.each(function(i) {
        var $row = $(this);
        var $cells = $row.find('td');
        var hasColspan = $cells.attr('colspan') || $cells.find('[colspan]').length > 0;
        
        if ($cells.length > 0 && !hasColspan) {
            hasDataRows = true;
        }
    });
    
    if (hasDataRows) {
        console.log('Table has data, initializing DataTable');
        try {
            $table.DataTable({
                pageLength: 25,
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    lengthMenu: "_MENU_ records per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No data available in table",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });
        } catch (error) {
            console.error('DataTable error:', error);
        }
    } else {
        console.log('Table has no data rows, skipping DataTable');
        $table.addClass('table-striped');
    }
    
    // Sales Chart - FIXED: Properly escape PHP data
    <?php
    // Ensure chart data is properly JSON encoded
    $chart_labels_js = json_encode($chart_labels);
    $chart_data_js = json_encode($chart_data);
    $category_labels_js = json_encode($category_labels);
    $category_data_js = json_encode($category_data);
    ?>
    
    if (typeof Chart !== 'undefined') {
        // Sales Chart
        var salesChartCanvas = document.getElementById('salesChart');
        if (salesChartCanvas) {
            var salesChart = new Chart(salesChartCanvas.getContext('2d'), {
                type: "line",
                data: {
                    labels: <?php echo $chart_labels_js; ?>,
                    datasets: [{
                        label: "Sales Revenue (Ksh)",
                        data: <?php echo $chart_data_js; ?>,
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
        }
        
        // Category Chart
        var categoryChartCanvas = document.getElementById('categoryChart');
        if (categoryChartCanvas) {
            var categoryChart = new Chart(categoryChartCanvas.getContext('2d'), {
                type: "doughnut",
                data: {
                    labels: <?php echo $category_labels_js; ?>,
                    datasets: [{
                        data: <?php echo $category_data_js; ?>,
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
        }
    } else {
        console.error('Chart.js not loaded');
    }
});

function printReport() {
    window.print();
}

function exportToCSV() {
    let csv = [];
    let table = document.getElementById("reportTable");
    
    if (!table) {
        console.error('Table not found');
        return;
    }
    
    let rows = table.querySelectorAll("tr");
    
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
    link.setAttribute("download", "report_<?php echo addslashes($report_type); ?>_<?php echo date('Y-m-d'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
<?php
$content = ob_get_clean();
echo adminLayout($content, 'Reports & Analytics');
?>