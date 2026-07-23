<?php
/**
 * Neptune Pay - Vérification du statut d'un paiement
 * Appel AJAX depuis la page de paiement
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/neptune_pay.php';

// Vérifier le code
$code = $_GET['code'] ?? '';
$orderNumber = $_GET['order'] ?? '';

if (empty($code) || empty($orderNumber)) {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
    exit();
}

// Vérifier le statut via l'API Neptune Pay
$status = neptuneGetPaymentStatus($code);

if (!$status) {
    echo json_encode(['success' => false, 'error' => 'Erreur de communication avec Neptune Pay']);
    exit();
}

// Si le paiement est confirmé
if ($status['status'] === 'paid') {
    $conn = getConnection();
    
    // Vérifier que la commande existe
    $stmt = $conn->prepare("SELECT id, status, payment_status FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if ($order && $order['status'] !== 'processing' && $order['payment_status'] !== 'paid') {
        // Mettre à jour la commande
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = 'processing', payment_status = 'paid' 
            WHERE order_number = ?
        ");
        $stmt->execute([$orderNumber]);
        
        // Enregistrer le paiement
        $stmt = $conn->prepare("
            INSERT INTO payments (order_id, payment_method, transaction_id, amount, status, response_data) 
            VALUES (?, 'neptune_pay', ?, ?, 'success', ?)
        ");
        $stmt->execute([
            $order['id'],
            $status['transaction_id'] ?? $code,
            $status['amount'],
            json_encode($status)
        ]);
        
        // Notifier l'admin (optionnel)
        // sendAdminNotification($orderNumber);
    }
}

// Retourner le statut
echo json_encode([
    'success' => true,
    'status' => $status['status'],
    'payment_id' => $status['payment_id'] ?? null,
    'amount' => $status['amount'] ?? null,
    'currency' => $status['currency'] ?? null,
    'payer_name' => $status['payer_name'] ?? null,
    'paid_at' => $status['paid_at'] ?? null
]);