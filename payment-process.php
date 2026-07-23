<?php
// payment-process.php - Traitement complet des paiements (CB et PayPal)
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Vérifier que le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit();
}

// ============================================
// 1. RÉCUPÉRATION DU PANIER
// ============================================
$stmt = $conn->prepare("
    SELECT c.id as cart_id, c.quantity, p.* 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    setFlashMessage('error', 'Votre panier est vide');
    header('Location: shop.php');
    exit();
}

// ============================================
// 2. CALCUL DES MONTANTS
// ============================================
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$discount = 0;
$coupon_code = $_SESSION['coupon_code'] ?? $_POST['coupon_code'] ?? '';

if (!empty($coupon_code)) {
    $stmt = $conn->prepare("
        SELECT * FROM coupons 
        WHERE code = ? AND is_active = 1 
        AND valid_from <= CURDATE() AND valid_to >= CURDATE()
        AND used_count < usage_limit
    ");
    $stmt->execute([$coupon_code]);
    $coupon = $stmt->fetch();
    
    if ($coupon && $subtotal >= $coupon['min_order_amount']) {
        if ($coupon['discount_type'] === 'percentage') {
            $discount = $subtotal * ($coupon['discount_value'] / 100);
            if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
                $discount = $coupon['max_discount'];
            }
        } else {
            $discount = min($coupon['discount_value'], $subtotal);
        }
    }
}

$shipping_cost = 0; // Livraison offerte
$tax = $subtotal * 0.1; // TVA 10%
$total = $subtotal + $shipping_cost + $tax - $discount;

// ============================================
// 3. RÉCUPÉRATION DES INFOS UTILISATEUR
// ============================================
$stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$full_name = !empty($_POST['full_name']) ? clean($_POST['full_name']) : ($user['full_name'] ?? $_SESSION['username'] ?? 'Client');
$email = !empty($_POST['email']) ? clean($_POST['email']) : ($user['email'] ?? $_SESSION['email'] ?? '');
$phone = !empty($_POST['phone']) ? clean($_POST['phone']) : ($user['phone'] ?? '');

// ============================================
// 4. ADRESSE DE LIVRAISON
// ============================================
$address_type = $_POST['address_type'] ?? 'auto';
$shipping_address = '';
$delivery_latitude = null;
$delivery_longitude = null;

if ($address_type === 'gps' || $address_type === 'auto') {
    $delivery_latitude = isset($_POST['delivery_latitude']) && !empty($_POST['delivery_latitude']) ? (float)$_POST['delivery_latitude'] : null;
    $delivery_longitude = isset($_POST['delivery_longitude']) && !empty($_POST['delivery_longitude']) ? (float)$_POST['delivery_longitude'] : null;
    
    if ($delivery_latitude && $delivery_longitude) {
        $shipping_address = "GPS: {$delivery_latitude}, {$delivery_longitude}";
    } else {
        $address = clean($_POST['address'] ?? '');
        $postal_code = clean($_POST['postal_code'] ?? '');
        $city = clean($_POST['city'] ?? '');
        $country = clean($_POST['country'] ?? 'France');
        $shipping_address = $address;
        if ($postal_code) $shipping_address .= ", $postal_code";
        if ($city) $shipping_address .= " $city";
        $shipping_address .= ", $country";
    }
} else {
    $address = clean($_POST['address'] ?? '');
    $postal_code = clean($_POST['postal_code'] ?? '');
    $city = clean($_POST['city'] ?? '');
    $country = clean($_POST['country'] ?? 'France');
    
    $shipping_address = $address;
    if ($postal_code) $shipping_address .= ", $postal_code";
    if ($city) $shipping_address .= " $city";
    $shipping_address .= ", $country";
}

if (empty($shipping_address) && !$delivery_latitude) {
    setFlashMessage('error', 'Veuillez fournir une adresse de livraison');
    header('Location: checkout.php');
    exit();
}

// ============================================
// 5. MODE DE PAIEMENT
// ============================================
$payment_method = clean($_POST['payment_method'] ?? 'credit_card');
$notes = clean($_POST['notes'] ?? '');
$order_number = generateOrderNumber();

// Vérifier que la méthode est autorisée (CB ou PayPal)
if (!in_array($payment_method, ['credit_card', 'paypal'])) {
    setFlashMessage('error', 'Mode de paiement non autorisé');
    header('Location: checkout.php');
    exit();
}

$conn->beginTransaction();

try {
    // Mettre à jour le téléphone utilisateur si fourni
    if (!empty($phone)) {
        $stmt = $conn->prepare("UPDATE users SET phone = ?, full_name = ? WHERE id = ?");
        $stmt->execute([$phone, $full_name, $user_id]);
    }
    
    // ============================================
    // 6. CRÉATION DE LA COMMANDE
    // ============================================
    $order_status = 'pending';
    $payment_status = 'pending';
    
    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, order_number, subtotal, discount, shipping_cost, tax, total_amount, 
            coupon_code, status, payment_method, payment_status, shipping_address, notes,
            delivery_latitude, delivery_longitude
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id, $order_number, $subtotal, $discount, $shipping_cost, $tax, $total,
        $coupon_code, $order_status, $payment_method, $payment_status, $shipping_address, $notes,
        $delivery_latitude, $delivery_longitude
    ]);
    $order_id = $conn->lastInsertId();
    
    // ============================================
    // 7. AJOUT DES ARTICLES
    // ============================================
    foreach ($cart_items as $item) {
        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $item_total = $item['price'] * $item['quantity'];
        $stmt->execute([
            $order_id, $item['id'], $item['name'], 
            $item['quantity'], $item['price'], $item_total
        ]);
        
        $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['id']]);
    }
    
    // ============================================
    // 8. TRAITEMENT DU PAIEMENT
    // ============================================
    $transaction_id = null;
    $payment_response = [];
    
    // 8.1 CARTE BANCAIRE
    if ($payment_method === 'credit_card') {
        $card_number = preg_replace('/\s/', '', $_POST['card_number'] ?? '');
        $card_last4 = substr($card_number, -4);
        $card_expiry = $_POST['card_expiry'] ?? '';
        $card_cvv = $_POST['card_cvv'] ?? '';
        
        if (empty($card_number) || strlen($card_number) < 13) {
            throw new Exception('Numéro de carte invalide');
        }
        if (empty($card_expiry) || strlen($card_expiry) < 5) {
            throw new Exception('Date d\'expiration invalide');
        }
        if (empty($card_cvv) || strlen($card_cvv) < 3) {
            throw new Exception('CVV invalide');
        }
        
        $transaction_id = 'CARD-' . strtoupper(uniqid());
        $payment_status = 'paid';
        $order_status = 'processing';
        
        // Simulation de vérification (en production: appel API bancaire)
        $payment_response = [
            'success' => true,
            'message' => 'Paiement carte bancaire accepté',
            'card_type' => 'visa',
            'auth_code' => strtoupper(substr(uniqid(), -6))
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO payments (order_id, payment_method, card_last4, transaction_id, amount, status, response_data) 
            VALUES (?, ?, ?, ?, ?, 'success', ?)
        ");
        $stmt->execute([
            $order_id, $payment_method, $card_last4, $transaction_id, $total, 
            json_encode($payment_response)
        ]);
    }
    
    // 8.2 PAYPAL
    elseif ($payment_method === 'paypal') {
        $transaction_id = 'PAYPAL-' . strtoupper(uniqid());
        $payment_status = 'paid';
        $order_status = 'processing';
        
        // Simulation de vérification PayPal
        $payment_response = [
            'success' => true,
            'message' => 'Paiement PayPal accepté',
            'paypal_email' => $email,
            'transaction' => $transaction_id
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO payments (order_id, payment_method, transaction_id, amount, status, response_data) 
            VALUES (?, ?, ?, ?, 'success', ?)
        ");
        $stmt->execute([
            $order_id, $payment_method, $transaction_id, $total, 
            json_encode($payment_response)
        ]);
    }
    
    // ============================================
    // 9. MISE À JOUR DU STATUT DE LA COMMANDE
    // ============================================
    $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?");
    $stmt->execute([$order_status, $payment_status, $order_id]);
    
    // Mettre à jour l'utilisation du coupon
    if (!empty($coupon_code) && $discount > 0) {
        $stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?");
        $stmt->execute([$coupon_code]);
        unset($_SESSION['coupon_code']);
    }
    
    // ============================================
    // 10. VIDER LE PANIER
    // ============================================
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $conn->commit();
    
    // ============================================
    // 11. MESSAGE DE CONFIRMATION
    // ============================================
    setFlashMessage('success', 'Paiement effectué avec succès ! Merci pour votre commande.');
    
    // ============================================
    // 12. ENVOI D'EMAIL DE CONFIRMATION
    // ============================================
    $email_body = "
        Bonjour $full_name,\n\n
        Merci pour votre commande sur Mars Shop !\n\n
        ─────────────────────────────\n
        📦 RÉCAPITULATIF DE VOTRE COMMANDE\n
        ─────────────────────────────\n
        Numéro de commande : $order_number\n
        Date : " . date('d/m/Y H:i') . "\n
        Montant total : " . formatPrice($total) . "\n
        Mode de paiement : " . ucfirst(str_replace('_', ' ', $payment_method)) . "\n
        Statut du paiement : Payé\n\n";
    
    if ($delivery_latitude && $delivery_longitude) {
        $email_body .= "📍 Point de livraison GPS :\n";
        $email_body .= "   Latitude : $delivery_latitude\n";
        $email_body .= "   Longitude : $delivery_longitude\n\n";
    } else {
        $email_body .= "📍 Adresse de livraison :\n";
        $email_body .= "   $shipping_address\n\n";
    }
    
    $email_body .= "🛒 Articles commandés :\n";
    foreach ($cart_items as $item) {
        $email_body .= "   - " . $item['name'] . " x" . $item['quantity'] . " : " . formatPrice($item['price'] * $item['quantity']) . "\n";
    }
    
    $email_body .= "─────────────────────────────\n";
    $email_body .= "Vous pouvez suivre votre commande dans votre espace client.\n\n";
    $email_body .= "Cordialement,\n";
    $email_body .= "L'équipe Mars Shop";
    
    sendEmail($email, "Confirmation de commande #$order_number", $email_body);
    
    // ============================================
    // 13. REDIRECTION
    // ============================================
    $_SESSION['cart_count'] = 0;
    header("Location: order-success.php?order=$order_number");
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Erreur commande: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    setFlashMessage('error', 'Erreur: ' . $e->getMessage());
    header("Location: checkout.php");
    exit();
}
?>