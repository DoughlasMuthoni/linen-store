<?php
// /linen-closet/includes/MpesaPayment.php

require_once 'mpesa_config.php';

class MpesaPayment {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get access token from M-Pesa API
     */
    private function getAccessToken() {
        try {
            $consumerKey = MpesaConfig::getConsumerKey();
            $consumerSecret = MpesaConfig::getConsumerSecret();
            $authUrl = MpesaConfig::getAuthUrl();
            
            error_log("=== Getting Access Token ===");
            error_log("Consumer Key: " . substr($consumerKey, 0, 10) . "...");
            error_log("Auth URL: $authUrl");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $authUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Cache-Control: no-cache'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ':' . $consumerSecret);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            error_log("Access token HTTP Status: $httpCode");
            
            if ($curlError) {
                error_log("Access token cURL Error: " . $curlError);
                return false;
            }
            
            if ($httpCode == 200) {
                $result = json_decode($response, true);
                if (isset($result['access_token'])) {
                    error_log("✓ Access token obtained successfully");
                    error_log("Token expires in: " . ($result['expires_in'] ?? 'N/A') . " seconds");
                    return $result['access_token'];
                }
            }
            
            error_log("✗ Failed to get access token. Response: " . $response);
            return false;
            
        } catch (Exception $e) {
            error_log("Access token exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initiate STK Push payment
     */
    public function initiateSTKPush($phone, $amount, $orderId, $orderNumber) {
        try {
            error_log("=== M-Pesa Payment Attempt ===");
            error_log("Order: #$orderNumber");
            error_log("Phone: $phone");
            error_log("Amount: $amount");
            
            // Format phone number
            $phone = $this->formatPhoneNumber($phone);
            error_log("Formatted phone: $phone");
            
            // Get access token
            error_log("Getting access token...");
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                error_log("Failed to get access token");
                return ['success' => false, 'message' => 'Failed to authenticate with M-Pesa. Please try again.'];
            }
            error_log("Access token obtained");
            
            // Generate timestamp
            $timestamp = date('YmdHis');
            $shortcode = MpesaConfig::getShortcode();
            $passkey = MpesaConfig::getPasskey();
            
            // Generate password
            $password = base64_encode($shortcode . $passkey . $timestamp);
            
            // Generate unique reference
            $reference = 'LINEN' . $orderId . time();
            
            // Get callback URL
            $callbackUrl = MpesaConfig::getCallbackUrl();
            error_log("Callback URL: $callbackUrl");
            
            // Prepare request data
          $data = [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => intval($amount),  // <-- FIXED: Use intval() to ensure it's an integer
                'PartyA' => $phone,
                'PartyB' => $shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => $orderNumber,
                'TransactionDesc' => 'Payment for Order #' . $orderNumber
            ];
            
            error_log("Request data: " . json_encode($data));
            
            // Log the payment attempt
            $this->logPaymentAttempt($orderId, $phone, $amount, $reference);
            
            // Make API request
            $stkPushUrl = MpesaConfig::getStkPushUrl();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $stkPushUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            error_log("Making API request to: $stkPushUrl");
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            error_log("HTTP Status: $httpCode");
            error_log("Response: " . $response);
            
            if ($curlError) {
                error_log("cURL Error: " . $curlError);
                return ['success' => false, 'message' => 'Network error: ' . $curlError];
            }
            
            $result = json_decode($response, true);
            
            if (isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
                // Success - STK Push sent
                $checkoutId = $result['CheckoutRequestID'];
                
                // Update payment attempt with checkout ID
                $this->updatePaymentAttempt($reference, $checkoutId);
                
                error_log("✓ STK Push successful! Checkout ID: $checkoutId");
                
                return [
                    'success' => true,
                    'message' => 'Payment request sent to your phone. Please check your M-Pesa to complete payment.',
                    'checkout_id' => $checkoutId,
                    'merchant_request_id' => $result['MerchantRequestID']
                ];
            } else {
                $errorMsg = isset($result['errorMessage']) ? $result['errorMessage'] : 
                           (isset($result['ResultDesc']) ? $result['ResultDesc'] : 'Failed to initiate payment');
                
                error_log("✗ M-Pesa Error: " . $errorMsg);
                
                return [
                    'success' => false,
                    'message' => $errorMsg
                ];
            }
            
        } catch (Exception $e) {
            error_log("M-Pesa Exception: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'System error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Format phone number
     */
    private function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with 254
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        // If it's 9 digits, add 254
        if (strlen($phone) === 9) {
            return '254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Log payment attempt to database
     */
    private function logPaymentAttempt($orderId, $phone, $amount, $reference) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payment_attempts 
                (order_id, phone, amount, reference, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            return $stmt->execute([$orderId, $phone, $amount, $reference]);
        } catch (Exception $e) {
            error_log("Failed to log payment attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update payment attempt with checkout ID
     */
    private function updatePaymentAttempt($reference, $checkoutId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE payment_attempts 
                SET checkout_id = ?, updated_at = NOW() 
                WHERE reference = ?
            ");
            return $stmt->execute([$checkoutId, $reference]);
        } catch (Exception $e) {
            error_log("Failed to update payment attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Query transaction status
     */
    public function queryTransaction($checkoutId) {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return false;
            }
            
            $data = [
                'BusinessShortCode' => MpesaConfig::getShortcode(),
                'Password' => base64_encode(MpesaConfig::getShortcode() . MpesaConfig::getPasskey() . date('YmdHis')),
                'Timestamp' => date('YmdHis'),
                'CheckoutRequestID' => $checkoutId
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, MpesaConfig::getQueryUrl());
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            return json_decode($response, true);
            
        } catch (Exception $e) {
            error_log("Query transaction error: " . $e->getMessage());
            return false;
        }
    }


    /**
 * Check if payment is successful by polling M-Pesa API
 */
    public function checkPaymentStatus($checkoutId) {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return ['success' => false, 'message' => 'Authentication failed'];
            }
            
            $timestamp = date('YmdHis');
            $shortcode = MpesaConfig::getShortcode();
            $passkey = MpesaConfig::getPasskey();
            $password = base64_encode($shortcode . $passkey . $timestamp);
            
            $data = [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutId
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, MpesaConfig::getQueryUrl());
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                $result = json_decode($response, true);
                
                if (isset($result['ResultCode']) && $result['ResultCode'] == '0') {
                    return [
                        'success' => true,
                        'paid' => true,
                        'receipt' => $result['MpesaReceiptNumber'] ?? '',
                        'message' => $result['ResultDesc'] ?? 'Payment successful'
                    ];
                } else {
                    return [
                        'success' => false,
                        'paid' => false,
                        'message' => $result['ResultDesc'] ?? 'Payment failed or pending'
                    ];
                }
            }
            
            return ['success' => false, 'message' => 'Failed to check payment status'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get payment attempt details
     */
    public function getPaymentAttempt($checkoutId) {
        try {
            $stmt = $this->db->prepare("
                SELECT pa.*, o.order_number, o.total_amount, o.payment_status
                FROM payment_attempts pa
                LEFT JOIN orders o ON pa.order_id = o.id
                WHERE pa.checkout_id = ?
            ");
            $stmt->execute([$checkoutId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get payment attempt error: " . $e->getMessage());
            return false;
        }
    }
}
?>