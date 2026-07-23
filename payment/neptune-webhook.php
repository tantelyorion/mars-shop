<?php
/**
 * Neptune Pay - Webhook
 * Reçoit les notifications de paiement de Neptune Pay
 * URL: https://mars-shop.com/payment/neptune-webhook.php
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/neptune_pay.php';

// Récupérer le payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_NEPTUNE_SIGNATURE'] ?? '';

// Vérifier la signature
if (!neptuneVerifyWebhookSignature($payload, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Signature invalide']);
    exit();
}

$data = json_decode($payload, true);

if (!$data || $data['event'] !== 'payment.completed') {
    http_response_code(400);
    echo json_encode(['error' => 'Événement non supporté']);
    exit();
}

// Traiter le paiement
$reference = $data['reference'];
$amount = $data['amount'];
$currency = $data['currency'];
$payerName = $data['payer_name'] ?? '';
$transactionId = $data['transaction_id'] ?? '';

$conn = getConnection();

// Vérifier que la commande existe
$stmt = $conn->prepare("SELECT id, status, payment_status FROM orders WHERE order_number = ?");
$stmt->execute([$reference]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Commande non trouvée']);
    exit();
}

// Vérifier que la commande n'a pas déjà été traitée
if ($order['payment_status'] === 'paid') {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Déjà traité']);
    exit();
}

// Mettre à jour la commande
$conn->beginTransaction();

try {
    $stmt = $conn->prepare("
        UPDATE orders 
        SET status = 'processing', payment_status = 'paid' 
        WHERE order_number = ?
    ");
    $stmt->execute([$reference]);
    
    // Enregistrer le paiement
    $stmt = $conn->prepare("
        INSERT INTO payments (order_id, payment_method, transaction_id, amount, status, response_data) 
        VALUES (?, 'neptune_pay', ?, ?, 'success', ?)
    ");
    $stmt->execute([
        $order['id'],
        $transactionId,
        $amount,
        json_encode($data)
    ]);
    
    $conn->commit();
    
    // Envoyer un email de confirmation (optionnel)
    // sendOrderConfirmation($reference);
    
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Paiement traité']);
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Webhook Neptune Pay Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne']);
}