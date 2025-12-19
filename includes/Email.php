<?php
// /linen-closet/includes/Email.php

// Include PHPMailer manually (adjust path based on your installation)
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configureMailer();
    }
    
    /**
     * Configure mailer settings
     */
    private function configureMailer() {
        try {
            // === CONFIGURE THESE SETTINGS ===
            
            // Method 1: Use SMTP (Recommended for reliability)
            // Uncomment and configure if you have SMTP credentials
            
            // $this->mail->isSMTP();
            // $this->mail->Host = 'smtp.gmail.com'; // Your SMTP host
            // $this->mail->SMTPAuth = true;
            // $this->mail->Username = 'githuiddoughlas8@gmail.com'; // Your email
            // $this->mail->Password = '123@githui'; // App password (not regular password)
            // $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            // $this->mail->Port = 587;
            
            
            // Method 2: Use PHP's mail() function (Simpler, less reliable)
            $this->mail->isMail(); // Uses PHP's mail() function
            
            // Email settings
            $this->mail->setFrom('githuiddoughlas8@gmail.com', 'Linen Closet');
            $this->mail->addReplyTo('support@linencloset.com', 'Linen Closet Support');
            
            // Enable debugging if needed (0 = off, 1 = client messages, 2 = client and server messages)
            // $this->mail->SMTPDebug = 2;
            
        } catch (Exception $e) {
            error_log("PHPMailer Configuration Error: " . $e->getMessage());
        }
    }
    
    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation($orderData, $customerEmail, $customerName = '') {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            // Add recipient
            $this->mail->addAddress($customerEmail, $customerName);
            
            // Set email content
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Order Confirmation - #' . ($orderData['order_number'] ?? '');
            
            // Generate email content
            $this->mail->Body = $this->generateOrderEmailHTML($orderData);
            $this->mail->AltBody = $this->generateOrderEmailText($orderData);
            
            // Send email
            $sent = $this->mail->send();
            
            if ($sent) {
                error_log("Order confirmation email sent to: " . $customerEmail);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate HTML email template
     */
    private function generateOrderEmailHTML($order) {
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
    
    /**
     * Generate plain text email
     */
    private function generateOrderEmailText($order) {
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
    
    /**
     * Simple test email function
     */
    public function sendTestEmail($toEmail, $toName = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail, $toName);
            
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Test Email from Linen Closet';
            $this->mail->Body = '<h1>Test Email</h1><p>This is a test email from your Linen Closet website.</p>';
            $this->mail->AltBody = 'Test Email - This is a test email from your Linen Closet website.';
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Test Email Error: " . $e->getMessage());
            return false;
        }
    }
}
?>