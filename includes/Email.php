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
        // === USE SMTP FOR PRODUCTION ===
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'githuiddoughlas8@gmail.com';
        $this->mail->Password = 'lcwuukirsttwxbce'; // Your E-commerce App password
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        
        // Enable debugging to see what's happening
        // $this->mail->SMTPDebug = 2;
        // $this->mail->Debugoutput = function($str, $level) {
        //     error_log("PHPMailer Debug: $str");
        // };
        
        // Email settings - USE CONSTANTS FROM YOUR CONFIG
        $this->mail->setFrom(
            defined('SITE_EMAIL') ? SITE_EMAIL : 'githuiddoughlas8@gmail.com',
            defined('SITE_NAME') ? SITE_NAME : 'Linen Closet'
        );
        
        $this->mail->addReplyTo(
            defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'githuiddoughlas8@gmail.com',
            defined('SITE_NAME') ? SITE_NAME . ' Support' : 'Linen Closet Support'
        );
        
    } catch (Exception $e) {
        error_log("PHPMailer Configuration Error: " . $e->getMessage());
        // Fallback to mail() if SMTP fails
        $this->mail->isMail();
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
        
        // Color scheme
        $primaryBlue = '#0d6efd';      // Primary blue
        $secondaryBlue = '#0dcaf0';    // Lighter blue
        $darkBlue = '#052c65';         // Dark blue
        $lightBlue = '#cfe2ff';        // Very light blue
        $textColor = '#333333';
        $borderColor = '#dee2e6';
        
        // Build items table if available
        $itemsTable = '';
        if (!empty($order['items'])) {
            $itemsTable = '<table style="width: 100%; border-collapse: separate; border-spacing: 0; margin: 25px 0; border: 1px solid ' . $borderColor . '; border-radius: 8px; overflow: hidden;">
                <thead>
                    <tr style="background-color: ' . $primaryBlue . ';">
                        <th style="padding: 14px 16px; text-align: left; color: white; font-weight: 600; border-bottom: 2px solid ' . $darkBlue . ';">Product</th>
                        <th style="padding: 14px 16px; text-align: center; color: white; font-weight: 600; border-bottom: 2px solid ' . $darkBlue . ';">Quantity</th>
                        <th style="padding: 14px 16px; text-align: right; color: white; font-weight: 600; border-bottom: 2px solid ' . $darkBlue . ';">Price</th>
                    </tr>
                </thead>
                <tbody>';
            
            $itemCount = 0;
            foreach ($order['items'] as $item) {
                $itemCount++;
                $bgColor = ($itemCount % 2 == 0) ? '#ffffff' : $lightBlue;
                $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                
                $itemsTable .= '
                <tr style="background-color: ' . $bgColor . ';">
                    <td style="padding: 14px 16px; border-bottom: 1px solid ' . $borderColor . ';">
                        <strong style="color: ' . $darkBlue . ';">' . 
                        htmlspecialchars($item['product_name'] ?? 'Product') . '</strong>
                    </td>
                    <td style="padding: 14px 16px; text-align: center; border-bottom: 1px solid ' . $borderColor . ';">
                        <span style="background-color: ' . $secondaryBlue . '; color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600;">' . 
                        ($item['quantity'] ?? 1) . '</span>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; border-bottom: 1px solid ' . $borderColor . ';">
                        <span style="color: ' . $darkBlue . '; font-weight: 600;">Ksh ' . 
                        number_format($itemTotal, 2) . '</span>
                    </td>
                </tr>';
            }
            
            $itemsTable .= '</tbody></table>';
        }
        
        $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Confirmation - ' . htmlspecialchars($order['order_number'] ?? '') . '</title>
        <style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
                line-height: 1.6; 
                color: ' . $textColor . '; 
                max-width: 650px; 
                margin: 0 auto; 
                padding: 0; 
                background-color: #f8f9fa;
            }
            .email-container {
                background-color: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                overflow: hidden;
                margin: 30px auto;
            }
            .email-header {
                background: linear-gradient(135deg, ' . $primaryBlue . ' 0%, ' . $darkBlue . ' 100%);
                padding: 40px 30px;
                text-align: center;
                color: white;
            }
            .email-header h1 {
                margin: 0 0 10px 0;
                font-size: 28px;
                font-weight: 700;
            }
            .email-header .order-number {
                background-color: rgba(255, 255, 255, 0.2);
                padding: 8px 20px;
                border-radius: 30px;
                display: inline-block;
                font-size: 16px;
                margin-top: 15px;
            }
            .email-content {
                padding: 40px 30px;
            }
            .email-footer {
                background-color: ' . $lightBlue . ';
                padding: 30px;
                text-align: center;
                color: ' . $darkBlue . ';
                font-size: 14px;
                border-top: 1px solid ' . $borderColor . ';
            }
            .greeting {
                font-size: 18px;
                margin-bottom: 25px;
                color: ' . $darkBlue . ';
            }
            .section-title {
                color: ' . $primaryBlue . ';
                font-size: 18px;
                font-weight: 600;
                margin: 30px 0 15px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid ' . $lightBlue . ';
            }
            .summary-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .summary-table td {
                padding: 12px 0;
                border-bottom: 1px dashed ' . $borderColor . ';
            }
            .summary-table .total-row td {
                font-weight: 700;
                font-size: 18px;
                color: ' . $primaryBlue . ';
                border-top: 2px solid ' . $primaryBlue . ';
                border-bottom: none;
                padding-top: 20px;
            }
            .status-badge {
                display: inline-block;
                background: linear-gradient(to right, ' . $primaryBlue . ', ' . $secondaryBlue . ');
                color: white;
                padding: 8px 20px;
                border-radius: 30px;
                font-weight: 600;
                font-size: 14px;
                box-shadow: 0 2px 8px rgba(13, 110, 253, 0.2);
            }
            .shipping-info {
                background-color: ' . $lightBlue . ';
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid ' . $primaryBlue . ';
                margin: 20px 0;
            }
            .action-button {
                display: inline-block;
                background: linear-gradient(to right, ' . $primaryBlue . ', ' . $secondaryBlue . ');
                color: white !important;
                padding: 16px 32px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 16px;
                text-align: center;
                box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
                transition: all 0.3s ease;
                margin: 20px 0;
            }
            .action-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4);
            }
            .contact-info {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid ' . $borderColor . ';
                color: #666;
                font-size: 14px;
            }
            .logo-placeholder {
                background-color: rgba(255, 255, 255, 0.1);
                width: 60px;
                height: 60px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                font-size: 28px;
            }
            @media (max-width: 600px) {
                .email-content, .email-header, .email-footer {
                    padding: 25px 20px;
                }
                .email-header h1 {
                    font-size: 24px;
                }
                .action-button {
                    padding: 14px 24px;
                    font-size: 14px;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Header -->
            <div class="email-header">
                <div class="logo-placeholder">
                    <i class="fas fa-tshirt" style="color: white;"></i>
                </div>
                <h1>Thank You for Your Order!</h1>
                <p style="margin: 0; opacity: 0.9; font-size: 18px;">We\'re preparing your items</p>
                <div class="order-number">Order #' . htmlspecialchars($order['order_number'] ?? '') . '</div>
            </div>
            
            <!-- Content -->
            <div class="email-content">
                <div class="greeting">
                    Hello <strong>' . htmlspecialchars($order['customer_name'] ?? 'Customer') . '</strong>,
                </div>
                
                <p style="margin-bottom: 25px; font-size: 16px; line-height: 1.7;">
                    Your order has been successfully received and is now being processed. 
                    You\'ll receive another email when your items ship. Here are your order details:
                </p>
                
                <!-- Order Summary -->
                <div class="section-title">
                    <i class="fas fa-shopping-bag" style="margin-right: 8px;"></i> Order Summary
                </div>
                
                ' . $itemsTable . '
                
                <!-- Order Totals -->
                <table class="summary-table">
                    <tr>
                        <td>Subtotal</td>
                        <td style="text-align: right; font-weight: 600;">Ksh ' . number_format($order['subtotal'] ?? 0, 2) . '</td>
                    </tr>
                    <tr>
                        <td>Shipping</td>
                        <td style="text-align: right; font-weight: 600;">' . 
                            (($order['shipping'] ?? 0) > 0 ? 'Ksh ' . number_format($order['shipping'] ?? 0, 2) : '<span style="color: #28a745; font-weight: 600;">FREE</span>') . 
                        '</td>
                    </tr>
                    <tr>
                        <td>Tax (16% VAT)</td>
                        <td style="text-align: right; font-weight: 600;">Ksh ' . number_format($order['tax'] ?? 0, 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td>Grand Total</td>
                        <td style="text-align: right;">Ksh ' . number_format($order['total'] ?? 0, 2) . '</td>
                    </tr>
                </table>
                
                <!-- Shipping Information -->
                <div class="section-title">
                    <i class="fas fa-map-marker-alt" style="margin-right: 8px;"></i> Shipping Information
                </div>
                
                <div class="shipping-info">
                    <div style="white-space: pre-line; line-height: 1.6;">' . htmlspecialchars($order['shipping_address'] ?? '') . '</div>
                </div>
                
                <!-- Order Status -->
                <div class="section-title">
                    <i class="fas fa-info-circle" style="margin-right: 8px;"></i> Order Status
                </div>
                
                <div style="margin-bottom: 30px;">
                    <span class="status-badge">
                        <i class="fas fa-clock" style="margin-right: 6px;"></i>
                        ' . ucfirst($order['status'] ?? 'pending') . '
                    </span>
                </div>
                
                <!-- Next Steps -->
                <div class="section-title">
                    <i class="fas fa-shipping-fast" style="margin-right: 8px;"></i> What Happens Next?
                </div>
                
                <ul style="margin: 20px 0 30px 0; padding-left: 20px; line-height: 1.7;">
                    <li style="margin-bottom: 10px;">We\'ll process and pack your order within 24 hours</li>
                    <li style="margin-bottom: 10px;">You\'ll receive a shipping confirmation email with tracking information</li>
                    <li style="margin-bottom: 10px;">Expected delivery: 3-5 business days</li>
                    <li>For any changes, please contact our support team</li>
                </ul>
                
                <!-- Track Order Button -->
<div style="text-align: center; margin: 40px 0;">
    <a href="' . $siteUrl . 'orders/track.php?order_id=' . ($order['id'] ?? '') . '" 
    class="action-button" 
    target="_blank"
    style="text-decoration: none; color: white !important;">
        <i class="fas fa-truck" style="margin-right: 8px;"></i> Track Your Order
    </a>
    <p style="margin-top: 15px; font-size: 14px; color: #666;">
        Can\'t click the button? Copy this link:<br>
        <code style="background: #f8f9fa; padding: 5px 10px; border-radius: 4px;">
            ' . $siteUrl . 'orders/track.php?order_id=' . ($order['id'] ?? '') . '
        </code>
    </p>
</div>
                
                <!-- Contact Information -->
                <div class="contact-info">
                    <p style="margin: 0 0 10px 0;"><strong>Need help with your order?</strong></p>
                    <p style="margin: 0 0 5px 0;">
                        <i class="fas fa-envelope" style="color: ' . $primaryBlue . '; margin-right: 8px;"></i>
                        Email: support@linencloset.com
                    </p>
                    <p style="margin: 0;">
                        <i class="fas fa-phone" style="color: ' . $primaryBlue . '; margin-right: 8px;"></i>
                        Phone: +254 700 000 000
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="email-footer">
                <div style="margin-bottom: 15px;">
                    <i class="fas fa-tshirt" style="font-size: 24px; color: ' . $primaryBlue . '; margin-bottom: 10px;"></i>
                    <h3 style="margin: 10px 0 5px 0; color: ' . $darkBlue . ';">' . $siteName . '</h3>
                    <p style="margin: 0; opacity: 0.8;">Premium Linens & Home Textiles</p>
                </div>
                <p style="margin: 20px 0 0 0; font-size: 12px; opacity: 0.7;">
                    &copy; ' . date('Y') . ' ' . $siteName . '. All rights reserved.<br>
                    Nairobi, Kenya
                </p>
            </div>
        </div>
    </body>
    </html>';
        
        return $html;
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