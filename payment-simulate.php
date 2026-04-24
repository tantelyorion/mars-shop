<?php
// payment-simulate.php - Simulation de paiement
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit();
}

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

// Données du formulaire
$full_name = clean($_POST['full_name']);
$email = clean($_POST['email']);
$phone = clean($_POST['phone']);
$address = clean($_POST['address']);
$payment_method = clean($_POST['payment_method']);

// Génération du numéro de commande
$order_number = generateOrderNumber();

$conn->beginTransaction();

try {
    // Mettre à jour les infos utilisateur
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $address, $user_id]);
    
    // Créer la commande
    $stmt = $conn->prepare("
        INSERT INTO orders (user_id, order_number, subtotal, total_amount, status, payment_method, shipping_address) 
        VALUES (?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([$user_id, $order_number, $subtotal, $total, $payment_method, $address]);
    $order_id = $conn->lastInsertId();
    
    // Ajouter les articles
    foreach ($cart_items as $item) {
        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $item_total = $item['price'] * $item['quantity'];
        $stmt->execute([$order_id, $item['id'], $item['name'], $item['quantity'], $item['price'], $item_total]);
        
        // Mettre à jour le stock
        $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['id']]);
    }
    
    // Simulation paiement
    $transaction_id = 'TXN-' . strtoupper(uniqid());
    $stmt = $conn->prepare("
        INSERT INTO payments (order_id, payment_method, transaction_id, amount, status) 
        VALUES (?, ?, ?, ?, 'success')
    ");
    $stmt->execute([$order_id, $payment_method, $transaction_id, $total]);
    
    // Mettre à jour le statut
    $stmt = $conn->prepare("UPDATE orders SET status = 'processing', payment_status = 'paid' WHERE id = ?");
    $stmt->execute([$order_id]);
    
    // Vider le panier
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $conn->commit();
    
    $_SESSION['cart_count'] = 0;
    header("Location: order-success.php?order=$order_number");
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    setFlashMessage('error', 'Erreur lors de la commande');
    header("Location: checkout.php");
    exit();
}
?>