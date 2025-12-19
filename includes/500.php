<?php
// 500.php - Internal Server Error
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Internal Server Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            text-align: center;
            padding: 100px;
        }
        h1 {
            color: #dc3545;
            font-size: 72px;
            margin-bottom: 20px;
        }
        p {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <h1>500</h1>
    <p>Internal Server Error</p>
    <p>Something went wrong on our end. Please try again later.</p>
    <p><a href="<?php echo SITE_URL; ?>">Return to Homepage</a></p>
</body>
</html>