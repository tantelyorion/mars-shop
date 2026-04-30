<?php
// admin/update-order-status.php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $conn = getConnection();
    $order_id = (int)$_POST['order_id'];
    $status = clean($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    
    setFlashMessage('success', 'Statut de la commande mis à jour');
}

header('Location: orders.php');
exit();
?>