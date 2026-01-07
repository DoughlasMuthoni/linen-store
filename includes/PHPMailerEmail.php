<?php
// /linen-closet/includes/PHPMailerEmail.php
// Include PHPMailer manually (same as Email.php)
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PHPMailerEmail {
    private $db;
    private $mail;
    
    public function __construct() {
        $app = new App();
        $this->db = $app->getDB();
        
        // Initialize PHPMailer
        $this->mail = new PHPMailer(true);
        $this->configureMailer();
    }
    
    private function configureMailer() {
        try {
            // Server settings
            $this->mail->isSMTP();  // Set mailer to use SMTP
            
            if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
                // Use SMTP
                $this->mail->Host = SMTP_HOST;
                $this->mail->SMTPAuth = true;
                $this->mail->Username = SMTP_USERNAME;
                $this->mail->Password = SMTP_PASSWORD;
                $this->mail->SMTPSecure = SMTP_SECURE;
                $this->mail->Port = SMTP_PORT;
            } else {
                // Use PHP mail() function
                $this->mail->isMail();
            }
            
            // Sender info
            $this->mail->setFrom(SITE_EMAIL, SITE_NAME);
            $this->mail->addReplyTo(SUPPORT_EMAIL, SITE_NAME . ' Support');
            
            // Debug mode (only in development)
            // $this->mail->SMTPDebug = 2;
            
        } catch (Exception $e) {
            error_log("PHPMailer Configuration Error: " . $e->getMessage());
        }
    }
    
    /**
     * Send order confirmation with PHPMailer
     */
    public function sendOrderConfirmation($orderId, $customerEmail, $customerName = '') {
        try {
            // Get order details
            $order = $this->getOrderDetails($orderId);
            
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Set recipient
            $this->mail->clearAddresses();
            $this->mail->addAddress($customerEmail, $customerName ?: $order['first_name'] . ' ' . $order['last_name']);
            
            // Set email content
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Order Confirmation - #' . $order['order_number'];
            $this->mail->Body = $this->getOrderConfirmationTemplate($order);
            $this->mail->AltBody = $this->getOrderConfirmationText($order);
            
            // Add BCC to admin
            $this->mail->addBCC(ADMIN_EMAIL);
            
            // Send email
            $sent = $this->mail->send();
            
            if ($sent) {
                // Log email sent
                $this->logEmailSent($orderId, 'order_confirmation', $customerEmail);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get order details (same as previous Email class)
     */
   private function getOrderDetails($orderId) {
    // Get order details
    $stmt = $this->db->prepare("
        SELECT o.*, 
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               a.address_line1, a.address_line2, a.city, a.state, a.postal_code, a.country
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN addresses a ON o.shipping_address_id = a.address_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        return null;
    }
    
    // Get order items
    $stmt = $this->db->prepare("
        SELECT oi.*, p.product_name, p.price as unit_price
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $order['items'] = $items;
    
    // Format shipping address
    $order['shipping_address'] = $this->formatShippingAddress($order);
    
    return $order;
}

private function formatShippingAddress($order) {
    $address = '';
    if (!empty($order['address_line1'])) {
        $address .= $order['address_line1'] . "\n";
    }
    if (!empty($order['address_line2'])) {
        $address .= $order['address_line2'] . "\n";
    }
    if (!empty($order['city'])) {
        $address .= $order['city'];
    }
    if (!empty($order['state'])) {
        $address .= ', ' . $order['state'];
    }
    if (!empty($order['postal_code'])) {
        $address .= ' ' . $order['postal_code'];
    }
    if (!empty($order['country'])) {
        $address .= "\n" . $order['country'];
    }
    
    return trim($address);
}
    
    /**
     * Log email sent to database
     */
    private function logEmailSent($orderId, $emailType, $recipient) {
        $stmt = $this->db->prepare("
            INSERT INTO email_logs 
            (order_id, email_type, recipient, sent_at) 
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$orderId, $emailType, $recipient]);
    }
    
    /**
     * Template methods (same as Email class)
     */
    
     private function getOrderConfirmationTemplate($order) {
        $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost/linen-closet/';
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Linen Closet';
        
        // Build items table if available
        $itemsTable = '';
        if (!empty($order['items'])) {
            $itemsTable = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background-color: #f8f9fa;">
                        <th style="padding: 10px; text-align: left;">Product</th>
                        <th style="padding: 10px; text-align: left;">Quantity</th>
                        <th style="padding: 10px; text-align: right;">Price</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($order['items'] as $item) {
                $itemsTable .= '
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">' . 
                        htmlspecialchars($item['product_name'] ?? 'Product') . '</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">' . 
                        ($item['quantity'] ?? 1) . '</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">Ksh ' . 
                        number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2) . '</td>
                </tr>';
            }
            
            $itemsTable .= '</tbody></table>';
        }
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Order Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background-color: #f8f9fa; padding: 10px; text-align: left; }
                td { padding: 10px; border-bottom: 1px solid #eee; }
                .total-row { font-weight: bold; }
                .btn { display: inline-block; padding: 12px 24px; background-color: #000; color: #fff; text-decoration: none; border-radius: 4px; }
                .status-badge { display: inline-block; padding: 4px 12px; background-color: #28a745; color: white; border-radius: 20px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1 style="margin: 0; color: #333;">Thank You for Your Order!</h1>
                <p style="margin: 10px 0 0; color: #666;">Order #' . htmlspecialchars($order['order_number'] ?? '') . '</p>
            </div>
            
            <div class="content">
                <p>Hello ' . htmlspecialchars($order['customer_name'] ?? 'Customer') . ',</p>
                <p>Your order has been received and is being processed. Here are your order details:</p>
                
                <h3>Order Summary</h3>
                ' . $itemsTable . '
                
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td style="text-align: right;">Ksh ' . number_format($order['subtotal'] ?? 0, 2) . '</td>
                    </tr>
                    <tr>
                        <td>Shipping</td>
                        <td style="text-align: right;">' . 
                            (($order['shipping'] ?? 0) > 0 ? 'Ksh ' . number_format($order['shipping'] ?? 0, 2) : '<span style="color: #28a745;">FREE</span>') . 
                        '</td>
                    </tr>
                    <tr>
                        <td>Tax (16% VAT)</td>
                        <td style="text-align: right;">Ksh ' . number_format($order['tax'] ?? 0, 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td>Grand Total</td>
                        <td style="text-align: right;">Ksh ' . number_format($order['total'] ?? 0, 2) . '</td>
                    </tr>
                </table>
                
                <h3>Shipping Information</h3>
                <p>
                    ' . nl2br(htmlspecialchars($order['shipping_address'] ?? '')) . '
                </p>
                
                <h3>Order Status</h3>
                <span class="status-badge">' . ucfirst($order['status'] ?? 'pending') . '</span>
                
                <h3>What happens next?</h3>
                <p>We will notify you when your order has shipped. You can track your order status anytime by visiting:</p>
                <p style="text-align: center; margin: 30px 0;">
                    <a href="' . $siteUrl . 'track-order" class="btn">Track Your Order</a>
                </p>
                
                <p>If you have any questions, please contact our customer support.</p>
            </div>
            
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . $siteName . '. All rights reserved.</p>
                <p>' . $siteName . ' | Nairobi, Kenya</p>
            </div>
        </body>
        </html>';
    }
  
    private function getOrderConfirmationText($order) {
        $text = "THANK YOU FOR YOUR ORDER!\n";
        $text .= "================================\n\n";
        $text .= "Hello " . ($order['customer_name'] ?? 'Customer') . ",\n\n";
        $text .= "Your order has been received and is being processed.\n\n";
        $text .= "ORDER DETAILS:\n";
        $text .= "--------------\n";
        $text .= "Order Number: " . ($order['order_number'] ?? '') . "\n";
        $text .= "Order Date: " . ($order['order_date'] ?? date('F j, Y')) . "\n";
        $text .= "Status: " . ucfirst($order['status'] ?? 'pending') . "\n\n";
        
        if (!empty($order['items'])) {
            $text .= "ORDER ITEMS:\n";
            $text .= "------------\n";
            foreach ($order['items'] as $item) {
                $text .= ($item['product_name'] ?? 'Product') . " (Qty: " . ($item['quantity'] ?? 1) . 
                        ") - Ksh " . number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2) . "\n";
            }
            $text .= "\n";
        }
        
        $text .= "ORDER TOTALS:\n";
        $text .= "-------------\n";
        $text .= "Subtotal: Ksh " . number_format($order['subtotal'] ?? 0, 2) . "\n";
        $text .= "Shipping: Ksh " . number_format($order['shipping'] ?? 0, 2) . "\n";
        $text .= "Tax: Ksh " . number_format($order['tax'] ?? 0, 2) . "\n";
        $text .= "Grand Total: Ksh " . number_format($order['total'] ?? 0, 2) . "\n\n";
        
        $text .= "SHIPPING ADDRESS:\n";
        $text .= "-----------------\n";
        $text .= ($order['shipping_address'] ?? '') . "\n\n";
        
        $text .= "TRACK YOUR ORDER:\n";
        $text .= "-----------------\n";
        $text .= SITE_URL . "track-order\n\n";
        
        $text .= "Thank you for shopping with us!\n";
        $text .= "The Linen Closet Team\n";
        
        return $text;
    } 
}
?>