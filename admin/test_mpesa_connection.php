<?php
// /linen-closet/admin/test_mpesa_connection.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mpesa_config.php';

echo "<h2>Testing M-Pesa API Connection</h2>";
echo "<style>
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; }
</style>";

// Test 1: Check if cURL is available
echo "<h3>1. Checking cURL</h3>";
if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "<span class='success'>✓</span> cURL is available (Version: {$curl_version['version']})<br>";
    echo "SSL Support: " . ($curl_version['features'] & CURL_VERSION_SSL ? "Yes" : "No") . "<br>";
} else {
    echo "<span class='error'>✗</span> cURL is NOT available. You need to enable cURL in PHP.<br>";
    exit();
}

// Test 2: Test access token
echo "<h3>2. Testing Access Token</h3>";
try {
    $consumerKey = MpesaConfig::getConsumerKey();
    $consumerSecret = MpesaConfig::getConsumerSecret();
    
    echo "Consumer Key: " . substr($consumerKey, 0, 10) . "...<br>";
    echo "Consumer Secret: " . substr($consumerSecret, 0, 10) . "...<br>";
    echo "Environment: " . MpesaConfig::ENVIRONMENT . "<br>";
    echo "Base URL: " . MpesaConfig::getBaseUrl() . "<br>";
    
    // Try to get access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, MpesaConfig::getAuthUrl());
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cache-Control: no-cache'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ':' . $consumerSecret);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode<br>";
    
    if ($curlError) {
        echo "<span class='error'>✗</span> cURL Error: $curlError<br>";
    } else {
        $result = json_decode($response, true);
        
        if (isset($result['access_token'])) {
            echo "<span class='success'>✓</span> Success! Access token obtained<br>";
            echo "Token (first 20 chars): " . substr($result['access_token'], 0, 20) . "...<br>";
            echo "Expires in: " . ($result['expires_in'] ?? 'N/A') . " seconds<br>";
        } else {
            echo "<span class='error'>✗</span> Failed to get access token<br>";
            echo "Response: " . htmlspecialchars($response) . "<br>";
            
            if (isset($result['errorMessage'])) {
                echo "Error: " . $result['errorMessage'] . "<br>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>✗</span> Exception: " . $e->getMessage() . "<br>";
}

// Test 3: Check callback URL
echo "<h3>3. Checking Callback URL</h3>";
$callbackUrl = MpesaConfig::getCallbackUrl();
echo "Callback URL: $callbackUrl<br>";

// Check if URL is accessible
$parsed = parse_url($callbackUrl);
if (isset($parsed['host']) && ($parsed['host'] == 'localhost' || $parsed['host'] == '127.0.0.1')) {
    echo "<span class='warning'>⚠️</span> Warning: Callback URL is localhost. M-Pesa cannot reach this.<br>";
    echo "For testing, you need to use a public URL (ngrok or similar).<br>";
} else if (isset($parsed['host'])) {
    echo "<span class='success'>✓</span> Callback URL is not localhost<br>";
    
    // Test if URL is accessible
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $callbackUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    $accessible = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        echo "<span class='success'>✓</span> Callback URL is accessible (HTTP $httpCode)<br>";
    } else {
        echo "<span class='warning'>⚠️</span> Callback URL returned HTTP $httpCode (should be 200)<br>";
    }
}

// Test 4: Database and Table Setup
echo "<h3>4. Database Setup</h3>";
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<span class='success'>✓</span> Database connection successful<br>";
    
    // Check if payment_attempts table exists
    $stmt = $db->query("SHOW TABLES LIKE 'payment_attempts'");
    if ($stmt->rowCount() > 0) {
        echo "<span class='success'>✓</span> payment_attempts table exists<br>";
    } else {
        echo "<span class='warning'>⚠️</span> payment_attempts table does not exist. Creating...<br>";
        
        // Create the table
        $sql = "CREATE TABLE payment_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT,
            phone VARCHAR(20),
            amount DECIMAL(10,2),
            reference VARCHAR(50),
            checkout_id VARCHAR(50),
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME,
            updated_at DATETIME,
            INDEX idx_order_id (order_id),
            INDEX idx_reference (reference),
            INDEX idx_checkout_id (checkout_id)
        )";
        
        if ($db->exec($sql) !== false) {
            echo "<span class='success'>✓</span> Table created successfully<br>";
        } else {
            echo "<span class='error'>✗</span> Failed to create table<br>";
        }
    }
} catch (PDOException $e) {
    echo "<span class='error'>✗</span> Database Error: " . $e->getMessage() . "<br>";
}

// Test 5: Create test STK Push
echo "<h3>5. Testing STK Push</h3>";
echo '<form method="POST">
    Phone: <input type="text" name="phone" value="254708374149" style="width: 200px;"><br>
    Amount: <input type="number" name="amount" value="1" style="width: 100px;" min="1" max="1000"><br>
    <input type="submit" name="test_stk" value="Test STK Push">
</form>';

if (isset($_POST['test_stk'])) {
    require_once __DIR__ . '/../includes/MpesaPayment.php';
    
    try {
        if (!isset($db)) {
            $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        $mpesa = new MpesaPayment($db);
        echo "<h4>STK Push Result:</h4>";
        
        $result = $mpesa->initiateSTKPush(
            trim($_POST['phone']),
            $_POST['amount'],
            999, // test order ID
            'TEST-' . time()
        );
        
        echo "<div style='margin: 20px 0; padding: 15px; background: " . ($result['success'] ? '#d4edda' : '#f8d7da') . "; border: 1px solid " . ($result['success'] ? '#c3e6cb' : '#f5c6cb') . ";'>";
        if ($result['success']) {
            echo "<span class='success'>✓</span> <strong>Success!</strong><br>";
            echo "Message: " . $result['message'] . "<br>";
            echo "Checkout ID: " . $result['checkout_id'] . "<br>";
            echo "Merchant Request ID: " . $result['merchant_request_id'] . "<br>";
        } else {
            echo "<span class='error'>✗</span> <strong>Failed!</strong><br>";
            echo "Error: " . $result['message'] . "<br>";
        }
        echo "</div>";
        
        echo "<h4>Raw Response:</h4>";
        echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>";
        
    } catch (Exception $e) {
        echo "<div style='margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb;'>";
        echo "<span class='error'>✗</span> <strong>Exception:</strong> " . $e->getMessage() . "<br>";
        echo "Trace: " . $e->getTraceAsString() . "</div>";
    }
}

// Test 6: Check PHP error log
echo "<h3>6. PHP Error Log</h3>";
$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile)) {
    echo "Error log: $logFile<br>";
    echo "File size: " . filesize($logFile) . " bytes<br>";
    
    // Get last 20 lines
    $lines = [];
    if ($handle = fopen($logFile, 'r')) {
        fseek($handle, -1, SEEK_END);
        $pos = ftell($handle);
        $linesFound = 0;
        
        while ($pos > 0 && $linesFound < 20) {
            $char = fgetc($handle);
            if ($char === "\n") {
                $linesFound++;
            }
            fseek($handle, --$pos);
        }
        
        $lines = explode("\n", fread($handle, 10000));
        fclose($handle);
        $lines = array_reverse($lines);
    }
    
    echo "Last 20 lines:<br>";
    echo "<pre style='max-height: 300px; overflow: auto;'>";
    foreach ($lines as $line) {
        if (strpos($line, 'M-Pesa') !== false) {
            echo "<strong>" . htmlspecialchars($line) . "</strong>\n";
        } else {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "Error log not found or not readable<br>";
    echo "Temporary error log:<br>";
    echo "<pre>";
    echo htmlspecialchars(shell_exec("php -r 'echo ini_get(\"error_log\");'"));
    echo "</pre>";
}

// Test 7: System Information
echo "<h3>7. System Information</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . " seconds<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? "Enabled" : "Disabled") . "<br>";
echo "Allow URL fopen: " . (ini_get('allow_url_fopen') ? "Yes" : "No") . "<br>";

// Test 8: Validate M-Pesa Credentials
echo "<h3>8. M-Pesa Credentials Validation</h3>";
$errors = MpesaConfig::validateCredentials();
if (empty($errors)) {
    echo "<span class='success'>✓</span> All credentials are properly configured<br>";
} else {
    echo "<span class='warning'>⚠️</span> Configuration issues:<br>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . $error . "</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<p><strong>Note:</strong> For testing with real M-Pesa STK Push, you need to:</p>";
echo "<ol>
    <li>Use ngrok or similar to expose your localhost to the internet</li>
    <li>Update the callback URL in mpesa_config.php to your ngrok URL</li>
    <li>Ensure your phone number is registered with M-Pesa sandbox (use 254708374149 for testing)</li>
    <li>Use the test PIN: 174379 (for sandbox testing)</li>
</ol>";

// Quick ngrok test suggestion
echo "<p><strong>Quick Test:</strong> Run this in terminal: <code>ngrok http 80</code> then update your callback URL.</p>";
?>