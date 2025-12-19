<?php
// /linen-closet/includes/error.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo http_response_code(); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .error-container {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php 
    $errorCode = http_response_code();
    $errorMessages = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Page Not Found',
        500 => 'Internal Server Error'
    ];
    
    $errorTitle = $errorMessages[$errorCode] ?? 'An error occurred';
    ?>
    
    <div class="container error-container">
        <div class="text-center">
            <div class="error-code"><?php echo $errorCode; ?></div>
            <h1 class="h2 mb-4"><?php echo $errorTitle; ?></h1>
            <p class="lead mb-4"><?php echo $message ?? 'Something went wrong. Please try again later.'; ?></p>
            <a href="<?php echo SITE_URL; ?>" class="btn btn-dark btn-lg">
                <i class="fas fa-home me-2"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>