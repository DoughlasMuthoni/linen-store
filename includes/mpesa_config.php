<?php
// /linen-closet/includes/mpesa_config.php

class MpesaConfig {
    // ====================
    // SANDBOX CREDENTIALS (FOR TESTING)
    // ====================
    
    // These are PUBLIC test credentials from Safaricom
    const SANDBOX_CONSUMER_KEY = '2J8Ky1WAHdNs099UTMDw7CgGXHZxYISVX453SUU7cX8SUYYt';
    const SANDBOX_CONSUMER_SECRET = 'M6FX8HzRhlj4YTGoqlRCom6WYYbEPGd72kG8QVYp8DCHa5es7Ev4j7aXV81mfGfr';
    const SANDBOX_SHORTCODE = '174379'; // Test Lipa Na M-Pesa shortcode
    const SANDBOX_PASSKEY = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    
    // ====================
    // PRODUCTION CREDENTIALS (WHEN LIVE)
    // ====================
    const PRODUCTION_CONSUMER_KEY = 'YourProductionConsumerKeyHere';
    const PRODUCTION_CONSUMER_SECRET = 'YourProductionConsumerSecretHere';
    const PRODUCTION_SHORTCODE = 'YourBusinessShortcodeHere'; // e.g., 123456
    const PRODUCTION_PASSKEY = 'YourProductionPasskeyHere';
    
    // ====================
    // CONFIGURATION
    // ====================
    const ENVIRONMENT = 'sandbox'; // Change to 'production' when going live
    
    // Security - Encrypt these if storing in database
    const ENCRYPTION_KEY = 'Your32CharacterEncryptionKeyHere!';
    
    // ====================
    // GETTER METHODS
    // ====================
    
    public static function getConsumerKey() {
        return self::ENVIRONMENT === 'sandbox' 
            ? self::SANDBOX_CONSUMER_KEY 
            : self::PRODUCTION_CONSUMER_KEY;
    }
    
    public static function getConsumerSecret() {
        return self::ENVIRONMENT === 'sandbox' 
            ? self::SANDBOX_CONSUMER_SECRET 
            : self::PRODUCTION_CONSUMER_SECRET;
    }
    
    public static function getShortcode() {
        return self::ENVIRONMENT === 'sandbox' 
            ? self::SANDBOX_SHORTCODE 
            : self::PRODUCTION_SHORTCODE;
    }
    
    public static function getPasskey() {
        return self::ENVIRONMENT === 'sandbox' 
            ? self::SANDBOX_PASSKEY 
            : self::PRODUCTION_PASSKEY;
    }
    
    // ====================
    // API ENDPOINTS
    // ====================
    
    public static function getBaseUrl() {
        return self::ENVIRONMENT === 'sandbox' 
            ? 'https://sandbox.safaricom.co.ke/' 
            : 'https://api.safaricom.co.ke/';
    }
    
    public static function getAuthUrl() {
        return self::getBaseUrl() . 'oauth/v1/generate?grant_type=client_credentials';
    }
    
    public static function getStkPushUrl() {
        return self::getBaseUrl() . 'mpesa/stkpush/v1/processrequest';
    }
    
    public static function getQueryUrl() {
        return self::getBaseUrl() . 'mpesa/stkpushquery/v1/query';
    }
    
    // ====================
    // CALLBACK URLS
    // ====================
    
    public static function getCallbackUrl() {
        return 'https://semifictionalized-naomi-multiaxial.ngrok-free.dev/linen-closet/mpesa_callback.php';
    }
    
    // ====================
    // VALIDATION METHODS
    // ====================
    
    public static function validateCredentials() {
        $errors = [];
        
        if (self::ENVIRONMENT === 'sandbox') {
            if (empty(self::SANDBOX_CONSUMER_KEY) || self::SANDBOX_CONSUMER_KEY === '2J8Ky1WAHdNs099UTMDw7CgGXHZxYISVX453SUU7cX8SUYYt') {
                $errors[] = 'Sandbox Consumer Key is not set';
            }
            if (empty(self::SANDBOX_CONSUMER_SECRET) || self::SANDBOX_CONSUMER_SECRET === 'M6FX8HzRhlj4YTGoqlRCom6WYYbEPGd72kG8QVYp8DCHa5es7Ev4j7aXV81mfGfr') {
                $errors[] = 'Sandbox Consumer Secret is not set';
            }
            if (empty(self::SANDBOX_PASSKEY) || self::SANDBOX_PASSKEY === 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919') {
                $errors[] = 'Sandbox Passkey is not set';
            }
        } else {
            if (empty(self::PRODUCTION_CONSUMER_KEY) || self::PRODUCTION_CONSUMER_KEY === 'YourProductionConsumerKeyHere') {
                $errors[] = 'Production Consumer Key is not set';
            }
            if (empty(self::PRODUCTION_CONSUMER_SECRET) || self::PRODUCTION_CONSUMER_SECRET === 'YourProductionConsumerSecretHere') {
                $errors[] = 'Production Consumer Secret is not set';
            }
            if (empty(self::PRODUCTION_SHORTCODE) || self::PRODUCTION_SHORTCODE === 'YourBusinessShortcodeHere') {
                $errors[] = 'Production Shortcode is not set';
            }
            if (empty(self::PRODUCTION_PASSKEY) || self::PRODUCTION_PASSKEY === 'YourProductionPasskeyHere') {
                $errors[] = 'Production Passkey is not set';
            }
        }
        
        return $errors;
    }
    
    // ====================
    // ENCRYPTION METHODS (Optional but recommended)
    // ====================
    
    public static function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', self::ENCRYPTION_KEY, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    public static function decrypt($data) {
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', self::ENCRYPTION_KEY, 0, $iv);
    }
}
?>