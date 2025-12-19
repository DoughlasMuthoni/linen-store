<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Email.php';

echo "<h1>Testing Email System</h1>";

$email = new Email();

// Test with your email
$testEmail = "youremail@gmail.com"; // Change this to your email
$testName = "Test User";

echo "<p>Sending test email to: $testEmail</p>";

if ($email->sendTestEmail($testEmail, $testName)) {
    echo "<p style='color: green;'><strong>✅ Test email sent successfully!</strong></p>";
    echo "<p>Check your email inbox (and spam folder).</p>";
} else {
    echo "<p style='color: red;'><strong>❌ Failed to send test email.</strong></p>";
    echo "<p>Check your server's error logs for more details.</p>";
}

echo "<hr>";
echo "<h3>PHP Mail Configuration:</h3>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Mail Function: " . (function_exists('mail') ? 'Available' : 'Not Available') . "\n";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "</pre>";
?>