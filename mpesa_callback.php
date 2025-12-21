<?php
// /test_callback.php (Root directory for easy ngrok access)

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Updated callback URL for root directory
$callbackUrl = 'https://semifictionalized-naomi-multiaxial.ngrok-free.dev/mpesa_callback.php';

echo "<h2>Testing M-Pesa Callback URL</h2>";
echo "<style>
    .success { color: green; }
    .error { color: red; }
    pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; }
</style>";

echo "URL: <code>$callbackUrl</code><br><br>";

// Test 1: Simple HTTP request
echo "<h3>1. Basic URL Test</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $callbackUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true); // Include headers

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "HTTP Status: <strong>$httpCode</strong><br>";

if ($httpCode == 200) {
    echo "<span class='success'>✓</span> URL is accessible<br>";
} elseif ($httpCode == 404) {
    echo "<span class='error'>✗</span> File not found (404)<br>";
    echo "Make sure mpesa_callback.php exists in the root directory<br>";
} elseif ($httpCode == 0) {
    echo "<span class='error'>✗</span> Cannot connect to URL<br>";
    echo "Check if ngrok is running: <code>ngrok http 80</code><br>";
} else {
    echo "<span class='error'>✗</span> HTTP Error: $httpCode<br>";
}

echo "<h4>Response Headers:</h4>";
echo "<pre>" . htmlspecialchars($headers) . "</pre>";

echo "<h4>Response Body:</h4>";
echo "<pre>" . htmlspecialchars($body) . "</pre>";

// Test 2: Send test callback data (simulating M-Pesa)
echo "<h3>2. M-Pesa Callback Simulation</h3>";

// Create a realistic test data
$testData = [
    'Body' => [
        'stkCallback' => [
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_21122025110927496707264913',
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'CallbackMetadata' => [
                'Item' => [
                    ['Name' => 'Amount', 'Value' => 1],
                    ['Name' => 'MpesaReceiptNumber', 'Value' => 'PGT345GTYV'],
                    ['Name' => 'Balance' , 'Value' => 45900],
                    ['Name' => 'TransactionDate', 'Value' => date('YmdHis')],
                    ['Name' => 'PhoneNumber', 'Value' => 254708374149]
                ]
            ]
        ]
    ]
];

echo "<h4>Test Data Sent:</h4>";
echo "<pre>" . htmlspecialchars(json_encode($testData, JSON_PRETTY_PRINT)) . "</pre>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $callbackUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: Daraja-Callback-Simulator'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "Callback Simulation HTTP Status: <strong>$httpCode</strong><br>";

if ($httpCode == 200) {
    echo "<span class='success'>✓</span> Callback accepted by server<br>";
} else {
    echo "<span class='error'>✗</span> Callback rejected (HTTP $httpCode)<br>";
}

echo "<h4>Response Headers:</h4>";
echo "<pre>" . htmlspecialchars($headers) . "</pre>";

echo "<h4>Response Body:</h4>";
echo "<pre>" . htmlspecialchars($body) . "</pre>";

// Parse JSON response
$jsonResponse = json_decode($body, true);
if ($jsonResponse && isset($jsonResponse['ResultCode']) && $jsonResponse['ResultCode'] == 0) {
    echo "<span class='success'>✓</span> M-Pesa accepted the callback response!<br>";
} elseif ($jsonResponse) {
    echo "<span class='error'>✗</span> Invalid callback response format<br>";
}

// Test 3: Check log directory and files
echo "<h3>3. Log Directory Check</h3>";

// Try different log directory paths
$possibleLogDirs = [
    __DIR__ . '/logs/',
    __DIR__ . '/../logs/',
    __DIR__ . '/../linen-closet/logs/',
    '/tmp/',
    sys_get_temp_dir()
];

$logFound = false;
foreach ($possibleLogDirs as $logDir) {
    $logFile = $logDir . 'mpesa_callback_' . date('Y-m-d') . '.log';
    
    if (file_exists($logFile)) {
        echo "<span class='success'>✓</span> Found log file: <code>$logFile</code><br>";
        echo "File size: " . filesize($logFile) . " bytes<br>";
        echo "Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "<br>";
        
        $logFound = true;
        
        // Read last 50 lines of log
        echo "<h4>Recent Log Entries:</h4>";
        $lines = [];
        if ($handle = fopen($logFile, 'r')) {
            $currentLine = '';
            while (($char = fgetc($handle)) !== false) {
                $currentLine .= $char;
                if ($char === "\n") {
                    $lines[] = $currentLine;
                    $currentLine = '';
                    if (count($lines) > 50) {
                        array_shift($lines);
                    }
                }
            }
            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
            fclose($handle);
        }
        
        echo "<pre style='max-height: 400px; overflow: auto;'>";
        foreach ($lines as $line) {
            if (strpos($line, 'M-Pesa') !== false || strpos($line, 'Callback') !== false) {
                echo "<strong>" . htmlspecialchars($line) . "</strong>";
            } else {
                echo htmlspecialchars($line);
            }
        }
        echo "</pre>";
        
        break;
    }
}

if (!$logFound) {
    echo "<span class='error'>✗</span> No log file found in expected locations<br>";
    
    // Test if we can create a log file
    echo "<h4>Testing Log Directory Permissions:</h4>";
    
    $testDirs = [
        __DIR__ . '/logs/' => 'Root logs directory',
        __DIR__ . '/../logs/' => 'Parent logs directory',
        sys_get_temp_dir() . '/' => 'System temp directory'
    ];
    
    foreach ($testDirs as $dir => $desc) {
        echo "Testing: $desc (<code>$dir</code>)<br>";
        
        if (!file_exists($dir)) {
            if (@mkdir($dir, 0755, true)) {
                echo "<span class='success'>✓</span> Created directory<br>";
            } else {
                echo "<span class='error'>✗</span> Failed to create directory<br>";
                continue;
            }
        }
        
        $testFile = $dir . 'test_' . time() . '.log';
        if (@file_put_contents($testFile, "Test log entry at " . date('Y-m-d H:i:s') . "\n")) {
            echo "<span class='success'>✓</span> Can write to directory<br>";
            unlink($testFile);
        } else {
            echo "<span class='error'>✗</span> Cannot write to directory<br>";
        }
        
        echo "<br>";
    }
}

// Test 4: Check if the callback file exists
echo "<h3>4. Callback File Check</h3>";

$callbackFile = __DIR__ . '/mpesa_callback.php';
if (file_exists($callbackFile)) {
    echo "<span class='success'>✓</span> Callback file exists: <code>$callbackFile</code><br>";
    echo "File size: " . filesize($callbackFile) . " bytes<br>";
    
    // Check file permissions
    $perms = fileperms($callbackFile);
    echo "Permissions: " . substr(sprintf('%o', $perms), -4) . "<br>";
    
    // Check file content briefly
    $content = file_get_contents($callbackFile);
    if (strpos($content, 'M-Pesa') !== false || strpos($content, 'Callback') !== false) {
        echo "<span class='success'>✓</span> File appears to be a valid callback handler<br>";
    } else {
        echo "<span class='error'>✗</span> File doesn't appear to handle M-Pesa callbacks<br>";
    }
    
    // Check if it has proper headers
    if (strpos($content, 'Content-Type: application/json') !== false) {
        echo "<span class='success'>✓</span> File sets proper JSON headers<br>";
    }
} else {
    echo "<span class='error'>✗</span> Callback file not found in root directory<br>";
    echo "Create <code>mpesa_callback.php</code> in the root directory<br>";
}

// Test 5: Directory listing for debugging
echo "<h3>5. Directory Structure</h3>";
echo "Current directory: <code>" . __DIR__ . "</code><br>";

$files = scandir(__DIR__);
echo "Files in root directory:<br>";
echo "<ul>";
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $filePath = __DIR__ . '/' . $file;
    $type = is_dir($filePath) ? '📁 Directory' : '📄 File';
    $size = is_file($filePath) ? ' (' . filesize($filePath) . ' bytes)' : '';
    echo "<li>$type: <code>$file</code>$size</li>";
}
echo "</ul>";

// Test 6: Simple echo test file
echo "<h3>6. Quick Test</h3>";
echo "Create a simple test file and try to access it:<br>";

$testPhpFile = __DIR__ . '/test_simple.php';
file_put_contents($testPhpFile, '<?php echo "Test OK: " . date("Y-m-d H:i:s"); ?>');

$testUrl = str_replace('mpesa_callback.php', 'test_simple.php', $callbackUrl);
echo "Test URL: <code>$testUrl</code><br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$testResponse = curl_exec($ch);
$testHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response: HTTP $testHttpCode - " . htmlspecialchars($testResponse) . "<br>";

// Clean up
unlink($testPhpFile);

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>
    <li>Make sure <code>mpesa_callback.php</code> exists in your root directory</li>
    <li>Update your MpesaConfig.php to point to: <code>$callbackUrl</code></li>
    <li>Ensure ngrok is running: <code>ngrok http 80</code></li>
    <li>Test the full flow with STK Push</li>
</ol>";

echo "<p><strong>Note:</strong> If you moved files to root, make sure to update all paths in your code.</p>";
?>