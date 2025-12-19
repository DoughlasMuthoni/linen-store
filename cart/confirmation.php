<?php
// /linen-closet/cart/confirmation.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

session_start();

// Redirect if no order was placed
if (!isset($_SESSION['last_order'])) {
    header('Location: ' . SITE_URL . 'cart');
    exit;
}

$order = $_SESSION['last_order'];
$emailSent = $_SESSION['email_sent'] ?? false;
$emailError = $_SESSION['email_error'] ?? false;

// Clear session data after displaying
unset($_SESSION['last_order']);
unset($_SESSION['email_sent']);
unset($_SESSION['email_error']);

$pageTitle = "Order Confirmation";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                    <i class="fas fa-home me-1"></i> Home
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>cart" class="text-decoration-none">Cart</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Order Confirmation</li>
        </ol>
    </nav>
    
    <!-- Confirmation Content -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body p-5">
                    <!-- Success Icon -->
                    <div class="mb-4">
                        <div class="rounded-circle bg-success d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-check fa-2x text-white"></i>
                        </div>
                    </div>
                    
                    <!-- Success Message -->
                    <h1 class="display-6 fw-bold mb-3">Order Confirmed!</h1>
                    <p class="lead text-muted mb-4">
                        Thank you for your order. We've received it and will process it shortly.
                    </p>
                    
                    <!-- Order Details -->
                    <div class="card border mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6 class="fw-bold text-muted mb-2">Order Number</h6>
                                    <p class="fs-5 fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="fw-bold text-muted mb-2">Order Total</h6>
                                    <p class="fs-5 fw-bold">Ksh <?php echo number_format($order['total'], 2); ?></p>
                                </div>
                                <div class="col-md-12">
                                    <h6 class="fw-bold text-muted mb-2">Confirmation Email</h6>
                                    <?php if ($emailSent): ?>
                                        <p class="text-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Sent to <?php echo htmlspecialchars($order['customer_email']); ?>
                                        </p>
                                    <?php elseif ($emailError): ?>
                                        <p class="text-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Order placed, but email failed to send. Please save your order number.
                                        </p>
                                    <?php else: ?>
                                        <p>
                                            <i class="fas fa-envelope me-2"></i>
                                            Will be sent to <?php echo htmlspecialchars($order['customer_email']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Next Steps -->
                    <div class="mb-5">
                        <h5 class="fw-bold mb-3">What happens next?</h5>
                        <div class="row text-start">
                            <div class="col-md-4 mb-3">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">1</span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="fw-bold">Order Processing</h6>
                                        <p class="small text-muted mb-0">We'll prepare your items for shipping.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">2</span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="fw-bold">Shipping</h6>
                                        <p class="small text-muted mb-0">Your order will be shipped within 1-2 business days.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">3</span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="fw-bold">Delivery</h6>
                                        <p class="small text-muted mb-0">You'll receive your order in 3-5 business days.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                        <a href="<?php echo SITE_URL; ?>track-order" class="btn btn-dark btn-lg">
                            <i class="fas fa-search me-2"></i> Track Your Order
                        </a>
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-dark btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i> Continue Shopping
                        </a>
                    </div>
                    
                    <!-- Email Note -->
                    <div class="mt-4">
                        <p class="small text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            A confirmation email has been sent to <?php echo htmlspecialchars($order['customer_email']); ?> 
                            with your order details and tracking information.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Support Card -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body text-center">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-headset me-2"></i> Need Help?
                    </h5>
                    <p class="text-muted mb-4">
                        Have questions about your order or need assistance?<br>
                        We're here to help!
                    </p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex flex-column">
                                <i class="fas fa-phone fa-2x text-dark mb-2"></i>
                                <span class="fw-bold">Call Us</span>
                                <small class="text-muted">+254 700 000 000</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex flex-column">
                                <i class="fas fa-envelope fa-2x text-dark mb-2"></i>
                                <span class="fw-bold">Email Us</span>
                                <small class="text-muted">support@linencloset.com</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex flex-column">
                                <i class="fas fa-clock fa-2x text-dark mb-2"></i>
                                <span class="fw-bold">Hours</span>
                                <small class="text-muted">Mon-Fri: 9AM-6PM</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>