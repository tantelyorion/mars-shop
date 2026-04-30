<?php
// payment-process.php - Traitement des paiements (CORRIGÉ)
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit();
}

// Debug - afficher les données reçues
error_log("=== PAYMENT PROCESS DEBUG ===");
error_log("POST data: " . print_r($_POST, true));

// Récupérer le panier
$stmt = $conn->prepare("
    SELECT c.quantity, p.* 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    header('Location: shop.php');
    exit();
}

// Calcul du total
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$total = $subtotal;

// Récupérer les infos utilisateur
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Données du formulaire
$full_name = $user['full_name'] ?? $_SESSION['username'] ?? 'Client';
$email = $user['email'] ?? $_SESSION['email'] ?? '';
$phone = clean($_POST['phone'] ?? '');
$notes = clean($_POST['notes'] ?? '');

// Adresse de livraison
$address = clean($_POST['address'] ?? '');
$postal_code = clean($_POST['postal_code'] ?? '');
$city = clean($_POST['city'] ?? '');
$country = clean($_POST['country'] ?? 'France');

// Construire l'adresse complète
$shipping_address = "$address";
if ($postal_code) $shipping_address .= ", $postal_code";
if ($city) $shipping_address .= " $city";
$shipping_address .= ", $country";

// Vérifier que l'adresse n'est pas vide
if (empty($address)) {
    setFlashMessage('error', 'Veuillez fournir une adresse de livraison');
    header('Location: checkout.php');
    exit();
}

$payment_method = clean($_POST['payment_method'] ?? 'cash');
$order_number = generateOrderNumber();

$conn->beginTransaction();

try {
    // Mettre à jour le téléphone utilisateur si fourni
    if (!empty($phone)) {
        $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $stmt->execute([$phone, $user_id]);
    }
    
    // Statut initial selon mode de paiement
    $order_status = 'pending';
    $payment_status = 'pending';
    $transaction_id = null;
    
    // Créer la commande
    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, order_number, subtotal, total_amount, status, 
            payment_method, payment_status, shipping_address, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id, $order_number, $subtotal, $total, $order_status,
        $payment_method, $payment_status, $shipping_address, $notes
    ]);
    $order_id = $conn->lastInsertId();
    
    // Ajouter les articles
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
        
        // Mettre à jour le stock
        $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['id']]);
    }
    
    // Traitement selon le mode de paiement
    if ($payment_method === 'credit_card') {
        // Paiement par carte bancaire
        $card_number = preg_replace('/\s/', '', $_POST['card_number'] ?? '');
        $card_last4 = substr($card_number, -4);
        $transaction_id = 'CARD-' . strtoupper(uniqid());
        $payment_status = 'paid';
        $order_status = 'processing';
        
        $stmt = $conn->prepare("
            INSERT INTO payments (order_id, payment_method, card_last4, transaction_id, amount, status) 
            VALUES (?, ?, ?, ?, ?, 'success')
        ");
        $stmt->execute([$order_id, $payment_method, $card_last4, $transaction_id, $total]);
        
    } elseif ($payment_method === 'paypal') {
        // Paiement PayPal
        $transaction_id = 'PAYPAL-' . strtoupper(uniqid());
        $payment_status = 'paid';
        $order_status = 'processing';
        
        $stmt = $conn->prepare("
            INSERT INTO payments (order_id, payment_method, transaction_id, amount, status) 
            VALUES (?, ?, ?, ?, 'success')
        ");
        $stmt->execute([$order_id, $payment_method, $transaction_id, $total]);
        
    } elseif ($payment_method === 'mobile_money') {
        // Paiement Mobile Money (en attente de validation)
        $operator = clean($_POST['mobile_operator'] ?? '');
        $mobile_transaction_id = clean($_POST['mobile_transaction_id'] ?? '');
        $sender_phone = clean($_POST['mobile_sender_phone'] ?? $phone);
        
        if (empty($operator) || empty($mobile_transaction_id)) {
            throw new Exception('Informations Mobile Money incomplètes');
        }
        
        $transaction_id = 'MM-' . strtoupper(uniqid());
        $payment_status = 'pending';
        $order_status = 'pending';
        
        // Enregistrer la transaction Mobile Money
        $stmt = $conn->prepare("
            INSERT INTO mobile_money_transactions (
                order_id, user_id, operator, amount, transaction_id, sender_phone, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$order_id, $user_id, $operator, $total, $mobile_transaction_id, $sender_phone]);
        
        // Enregistrer le paiement comme en attente
        $stmt = $conn->prepare("
            INSERT INTO payments (order_id, payment_method, transaction_id, amount, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$order_id, $payment_method, $transaction_id, $total]);
        
    } else {
        // Paiement à la livraison (cash)
        $transaction_id = 'CASH-' . strtoupper(uniqid());
        $payment_status = 'pending';
        $order_status = 'pending';
        
        $stmt = $conn->prepare("
            INSERT INTO payments (order_id, payment_method, transaction_id, amount, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$order_id, $payment_method, $transaction_id, $total]);
    }
    
    // Mettre à jour le statut de la commande
    $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?");
    $stmt->execute([$order_status, $payment_status, $order_id]);
    
    // Vider le panier
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $conn->commit();
    
    // Message de confirmation différent selon le mode de paiement
    if ($payment_method === 'mobile_money') {
        setFlashMessage('info', 'Votre commande a été enregistrée. En attente de validation du paiement.');
    } elseif ($payment_method === 'cash') {
        setFlashMessage('success', 'Commande confirmée ! Paiement à la livraison.');
    } else {
        setFlashMessage('success', 'Paiement effectué avec succès !');
    }
    
    // Envoi email confirmation
    $payment_status_text = $payment_status === 'paid' ? 'payé' : 'en attente de validation';
    $email_body = "
        Bonjour $full_name,\n\n
        Merci pour votre commande sur Mars Shop !\n\n
        Numéro de commande : $order_number\n
        Montant total : " . formatPrice($total) . "\n
        Mode de paiement : " . ucfirst(str_replace('_', ' ', $payment_method)) . "\n
        Statut du paiement : $payment_status_text\n
        Adresse de livraison : $shipping_address\n\n";
    
    if ($payment_method === 'mobile_money') {
        $email_body .= "Important : Votre paiement sera vérifié par notre équipe sous 24-48h.\n";
        $email_body .= "Numéro de transaction : $mobile_transaction_id\n\n";
    }
    
    $email_body .= "Vous pouvez suivre votre commande dans votre espace client.\n\n
        Cordialement,\n
        L'équipe Mars Shop
    ";
    sendEmail($email, "Confirmation de commande #$order_number", $email_body);
    
    $_SESSION['cart_count'] = 0;
    header("Location: order-success.php?order=$order_number");
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Erreur commande: " . $e->getMessage());
    setFlashMessage('error', $e->getMessage());
    header("Location: checkout.php");
    exit();
}
?>