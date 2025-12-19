<?php
// /linen-closet/cart/checkout-process.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/Email.php'; // We'll create this

session_start();

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: ' . SITE_URL . 'cart');
    exit;
}

// Process the form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;
    $orderId = null;
    
    try {
        // Validate input
        $shippingAddress = $_POST['new_shipping_address'] ?? [];
        $shippingMethod = $_POST['shipping_method'] ?? 'standard';
        $paymentMethod = $_POST['payment_method'] ?? 'mpesa';
        
        // Validate required fields
        $requiredFields = ['full_name', 'phone', 'address_line1', 'city', 'state', 'postal_code', 'country'];
        foreach ($requiredFields as $field) {
            if (empty(trim($shippingAddress[$field] ?? ''))) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Validate email from session or form
        $customerEmail = $_SESSION['customer_email'] ?? $_POST['email'] ?? '';
        if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email address is required';
        }
        
        // Get current user if logged in
        $userId = $_SESSION['user_id'] ?? null;
        
        // Calculate totals
        $cart = $_SESSION['cart'] ?? [];
        $subtotal = 0;
        
        // Get product details for calculation
        $app = new App();
        $db = $app->getDB();
        
        $productIds = array_keys($cart);
        if (!empty($productIds)) {
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
            $stmt = $db->prepare("SELECT id, price, stock_quantity FROM products WHERE id IN ($placeholders) AND is_active = 1");
            $stmt->execute($productIds);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $productPrices = [];
            $outOfStockItems = [];
            
            foreach ($products as $product) {
                $productPrices[$product['id']] = $product['price'];
                
                // Check stock
                $cartQuantity = $cart[$product['id']]['quantity'] ?? 1;
                if ($cartQuantity > $product['stock_quantity']) {
                    $outOfStockItems[] = $product['id'];
                }
            }
            
            if (!empty($outOfStockItems)) {
                $errors[] = 'Some items in your cart are out of stock or quantity is not available';
            }
            
            // Calculate subtotal
            foreach ($cart as $id => $item) {
                if (isset($productPrices[$id])) {
                    $subtotal += $productPrices[$id] * ($item['quantity'] ?? 1);
                }
            }
        }
        
        // Calculate shipping
        $shippingCost = 0;
        if ($shippingMethod === 'standard') {
            $shippingCost = ($subtotal >= 5000) ? 0 : 300;
        } elseif ($shippingMethod === 'express') {
            $shippingCost = 700;
        }
        
        // Calculate tax (16% VAT)
        $tax = $subtotal * 0.16;
        $total = $subtotal + $shippingCost + $tax;
        
        // If no errors, proceed with order creation
        if (empty($errors)) {
            // Start transaction
            $db->beginTransaction();
            
            try {
                // 1. Create customer if not exists
                if ($userId) {
                    $customerId = $userId;
                } else {
                    // Create guest customer
                    $stmt = $db->prepare("
                        INSERT INTO customers (first_name, last_name, email, phone, is_guest, created_at) 
                        VALUES (?, ?, ?, ?, 1, NOW())
                    ");
                    
                    $nameParts = explode(' ', $shippingAddress['full_name'], 2);
                    $firstName = $nameParts[0];
                    $lastName = $nameParts[1] ?? '';
                    
                    $stmt->execute([
                        $firstName,
                        $lastName,
                        $customerEmail,
                        $shippingAddress['phone']
                    ]);
                    
                    $customerId = $db->lastInsertId();
                }
                
                // 2. Create shipping address
                $stmt = $db->prepare("
                    INSERT INTO addresses 
                    (customer_id, address_type, full_name, phone, address_line1, address_line2, city, state, postal_code, country, created_at)
                    VALUES (?, 'shipping', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $customerId,
                    $shippingAddress['full_name'],
                    $shippingAddress['phone'],
                    $shippingAddress['address_line1'],
                    $shippingAddress['address_line2'] ?? '',
                    $shippingAddress['city'],
                    $shippingAddress['state'],
                    $shippingAddress['postal_code'],
                    $shippingAddress['country']
                ]);
                
                $addressId = $db->lastInsertId();
                
                // 3. Get payment method ID
                $stmt = $db->prepare("SELECT id FROM payment_methods WHERE code = ? AND is_active = 1");
                $stmt->execute([$paymentMethod]);
                $paymentMethodId = $stmt->fetchColumn();
                
                if (!$paymentMethodId) {
                    // Default to M-Pesa
                    $paymentMethodId = 1;
                }
                
                // 4. Generate order number
                $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
                
                // 5. Create order
                $stmt = $db->prepare("
                    INSERT INTO orders 
                    (order_number, customer_id, shipping_address_id, payment_method_id, shipping_method, 
                     shipping_cost, tax_amount, subtotal_amount, total_amount, status, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', '', NOW())
                ");
                
                $stmt->execute([
                    $orderNumber,
                    $customerId,
                    $addressId,
                    $paymentMethodId,
                    $shippingMethod,
                    $shippingCost,
                    $tax,
                    $subtotal,
                    $total
                ]);
                
                $orderId = $db->lastInsertId();
                
                // 6. Create order items and update stock
                foreach ($cart as $productId => $item) {
                    $quantity = $item['quantity'] ?? 1;
                    $price = $productPrices[$productId] ?? 0;
                    
                    // Insert order item
                    $stmt = $db->prepare("
                        INSERT INTO order_items 
                        (order_id, product_id, quantity, price, size, color, material, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $orderId,
                        $productId,
                        $quantity,
                        $price,
                        $item['size'] ?? null,
                        $item['color'] ?? null,
                        $item['material'] ?? null
                    ]);
                    
                    // Update product stock
                    $stmt = $db->prepare("
                        UPDATE products 
                        SET stock_quantity = stock_quantity - ?,
                            updated_at = NOW()
                        WHERE id = ? AND is_active = 1
                    ");
                    
                    $stmt->execute([$quantity, $productId]);
                }
                
                // 7. Commit transaction
                $db->commit();
                
                // 8. Send confirmation email
                if (defined('SEND_ORDER_CONFIRMATION') && SEND_ORDER_CONFIRMATION) {
                    $email = new Email();
                    $emailSent = $email->sendOrderConfirmation($orderId, $customerEmail);
                    
                    if ($emailSent) {
                        $_SESSION['email_sent'] = true;
                    } else {
                        $_SESSION['email_error'] = 'Order was placed but confirmation email failed to send.';
                    }
                }
                
                // 9. Clear cart
                $_SESSION['cart'] = [];
                
                // 10. Store order info in session for confirmation page
                $_SESSION['last_order'] = [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'customer_email' => $customerEmail,
                    'total' => $total
                ];
                
                // Redirect to confirmation page
                header('Location: ' . SITE_URL . 'cart/confirmation');
                exit;
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        error_log("Checkout Error: " . $e->getMessage());
        $errors[] = 'An error occurred while processing your order. Please try again.';
    }
}

// If we get here, there was an error or form wasn't submitted
// Redirect back to checkout page with errors
if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_form_data'] = $_POST;
}

header('Location: ' . SITE_URL . 'cart/checkout');
exit;
?>