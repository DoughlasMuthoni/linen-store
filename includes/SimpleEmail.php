<?php
// /linen-closet/includes/SimpleMailer.php

class SimpleMailer {
    /**
     * Send order confirmation email using PHP's built-in mail()
     */
    public static function sendOrderConfirmation($orderDetails, $customerEmail) {
        $to = $customerEmail;
        $subject = "Order Confirmation - #" . ($orderDetails['order_number'] ?? '');
        
        // HTML email content
        $htmlContent = self::generateOrderEmailHTML($orderDetails);
        
        // Plain text version
        $textContent = self::generateOrderEmailText($orderDetails);
        
        // Headers
        $headers = "From: Linen Closet <orders@linencloset.com>\r\n";
        $headers .= "Reply-To: support@linencloset.com\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"boundary\"\r\n";
        
        // Email body
        $message = "--boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $textContent . "\r\n\r\n";
        
        $message .= "--boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlContent . "\r\n\r\n";
        $message .= "--boundary--";
        
        // Send email
        return mail($to, $subject, $message, $headers);
    }
    
    /**
     * Generate HTML email
     */
    private static function generateOrderEmailHTML($order) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background-color: #f8f9fa; padding: 10px; text-align: left; }
                td { padding: 10px; border-bottom: 1px solid #eee; }
                .total-row { font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1 style="margin: 0; color: #333;">Thank You for Your Order!</h1>
                <p style="margin: 10px 0 0; color: #666;">Order #' . htmlspecialchars($order['order_number'] ?? '') . '</p>
            </div>
            
            <div class="content">
                <p>Hello ' . htmlspecialchars($order['customer_name'] ?? 'Customer') . ',</p>
                <p>Your order has been received and is being processed.</p>
                
                <h3>Order Summary</h3>
                <table>
                    <tr>
                        <td>Order Number</td>
                        <td>' . htmlspecialchars($order['order_number'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td>Order Date</td>
                        <td>' . htmlspecialchars($order['order_date'] ?? date('F j, Y')) . '</td>
                    </tr>
                    <tr>
                        <td>Order Total</td>
                        <td>Ksh ' . number_format($order['total'] ?? 0, 2) . '</td>
                    </tr>
                </table>
                
                <h3>Shipping Information</h3>
                <p>
                    ' . htmlspecialchars($order['shipping_address'] ?? '') . '
                </p>
                
                <p>You can track your order anytime by visiting:</p>
                <p><a href="' . SITE_URL . 'track-order">Track Your Order</a></p>
                
                <p>If you have any questions, please contact our customer support.</p>
            </div>
            
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Linen Closet. All rights reserved.</p>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Generate plain text email
     */
    private static function generateOrderEmailText($order) {
        return "THANK YOU FOR YOUR ORDER!\n\n" .
               "Hello " . ($order['customer_name'] ?? 'Customer') . ",\n\n" .
               "Your order has been received and is being processed.\n\n" .
               "ORDER DETAILS:\n" .
               "Order Number: " . ($order['order_number'] ?? '') . "\n" .
               "Order Date: " . ($order['order_date'] ?? date('F j, Y')) . "\n" .
               "Order Total: Ksh " . number_format($order['total'] ?? 0, 2) . "\n\n" .
               "Track your order: " . SITE_URL . "track-order\n\n" .
               "Thank you for shopping with us!\n" .
               "The Linen Closet Team";
    }
}
?>