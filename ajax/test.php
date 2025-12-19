<?php
// /linen-closet/ajax/test.php
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['test' => 'success', 'message' => 'AJAX is working']);
exit();
?>