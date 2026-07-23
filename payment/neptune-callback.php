<?php
/**
 * Neptune Pay - Callback de retour utilisateur
 * URL: https://mars-shop.com/payment/neptune-callback.php
 * 
 * Cette page est appelée quand l'utilisateur revient après avoir payé
 * ou quand il annule le paiement
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/neptune_pay.php';

// ============================================
// RÉCUPÉRATION DES PARAMÈTRES
// ============================================

// Neptune Pay envoie ces paramètres en GET
$code = $_GET['code'] ?? '';
$status = $_GET['status'] ?? '';
$reference = $_GET['reference'] ?? '';
$transaction_id = $_GET['transaction_id'] ?? '';

// Ou via POST (si configuré)
if (empty($code) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $code = $input['code'] ?? '';
    $status = $input['status'] ?? '';
    $reference = $input['reference'] ?? '';
    $transaction_id = $input['transaction_id'] ?? '';
}

// ============================================
// SESSION ET CONNEXION
// ============================================
$conn = getConnection();

// Récupérer les infos de paiement depuis la session
$paymentData = $_SESSION['neptune_payment'] ?? [];
$orderNumber = $reference ?: ($paymentData['order_number'] ?? '');

// ============================================
// VÉRIFICATION DU PAIEMENT
// ============================================

// Si on a un code, vérifier le statut via l'API
if (!empty($code) && empty($status)) {
    $apiStatus = neptuneGetPaymentStatus($code);
    if ($apiStatus) {
        $status = $apiStatus['status'];
        $reference = $apiStatus['reference'] ?? $reference;
        $transaction_id = $apiStatus['transaction_id'] ?? $transaction_id;
        $amount = $apiStatus['amount'] ?? 0;
        $payer_name = $apiStatus['payer_name'] ?? '';
    }
}

// ============================================
// TRAITEMENT SELON LE STATUT
// ============================================

// Rediriger vers order-success.php si paiement confirmé
if ($status === 'paid' || $status === 'completed') {
    // Vérifier que la commande existe
    if (!empty($orderNumber)) {
        $stmt = $conn->prepare("SELECT id, status, payment_status FROM orders WHERE order_number = ?");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Si la commande n'est pas encore marquée comme payée
            if ($order['payment_status'] !== 'paid') {
                $conn->beginTransaction();
                try {
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
                        $transaction_id ?: $code,
                        $paymentData['amount'] ?? 0,
                        json_encode([
                            'code' => $code,
                            'status' => $status,
                            'reference' => $reference,
                            'transaction_id' => $transaction_id,
                            'payer_name' => $payer_name ?? ''
                        ])
                    ]);
                    
                    $conn->commit();
                    
                    // Vider le panier
                    if (isset($_SESSION['user_id'])) {
                        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                    }
                    
                    // Nettoyer la session
                    unset($_SESSION['neptune_payment']);
                    unset($_SESSION['pending_order']);
                    
                    // Message flash
                    setFlashMessage('success', 'Paiement effectué avec succès ! Merci pour votre commande.');
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    error_log("Callback Neptune Pay Error: " . $e->getMessage());
                    setFlashMessage('error', 'Erreur lors du traitement du paiement.');
                }
            }
        }
    }
    
    // Rediriger vers la page de succès
    if (!empty($orderNumber)) {
        header('Location: ../order-success.php?order=' . urlencode($orderNumber));
        exit();
    } else {
        header('Location: ../order-success.php');
        exit();
    }
}

// ============================================
// STATUT EN ATTENTE (REDIRECTION VERS LA PAGE DE PAIEMENT)
// ============================================
elseif ($status === 'pending') {
    // Si le paiement est toujours en attente, rediriger vers la page de paiement
    if (!empty($code)) {
        header('Location: neptune-generate.php?code=' . urlencode($code) . '&order=' . urlencode($orderNumber));
        exit();
    }
    
    // Sinon, rediriger vers le checkout
    setFlashMessage('info', 'Votre paiement est en attente. Veuillez patienter.');
    header('Location: ../checkout.php');
    exit();
}

// ============================================
// STATUT ANNULÉ / EXPIRÉ
// ============================================
elseif ($status === 'cancelled' || $status === 'expired') {
    // Mettre à jour le statut de la commande
    if (!empty($orderNumber)) {
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = 'cancelled' 
            WHERE order_number = ? AND payment_status != 'paid'
        ");
        $stmt->execute([$orderNumber]);
    }
    
    // Nettoyer la session
    unset($_SESSION['neptune_payment']);
    unset($_SESSION['pending_order']);
    
    // Message flash
    if ($status === 'cancelled') {
        setFlashMessage('warning', 'Le paiement a été annulé.');
    } else {
        setFlashMessage('warning', 'Le paiement a expiré. Veuillez recommencer.');
    }
    
    header('Location: ../checkout.php');
    exit();
}

// ============================================
// AUCUN STATUT - REDIRECTION VERS LE CHECKOUT
// ============================================
else {
    // Si on arrive ici sans statut, vérifier le code si disponible
    if (!empty($code)) {
        header('Location: neptune-generate.php?code=' . urlencode($code) . '&order=' . urlencode($orderNumber));
        exit();
    }
    
    setFlashMessage('error', 'Erreur de paiement. Veuillez réessayer.');
    header('Location: ../checkout.php');
    exit();
}