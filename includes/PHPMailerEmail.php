<?php
// /linen-closet/includes/PHPMailerEmail.php

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
        // Same implementation as in Email class
        // ...
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
        // Use same template as Email class
        // ...
    }
    
    private function getOrderConfirmationText($order) {
        // Use same template as Email class
        // ...
    }
}
?>