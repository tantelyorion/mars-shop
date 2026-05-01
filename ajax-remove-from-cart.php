<?php
// ajax-remove-from-cart.php - Suppression AJAX d'un article du panier
require_once 'config/database.php';
require_once 'includes/functions.php';

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Forcer l'en-tête JSON
header('Content-Type: application/json');

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données
$cart_id = isset($_POST['cart_id']) ? $_POST['cart_id'] : '';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if (empty($cart_id) && empty($product_id)) {
    echo json_encode(['success' => false, 'message' => 'Identifiant manquant']);
    exit();
}

$conn = getConnection();

// Supprimer le produit
if (isLoggedIn()) {
    // Utilisateur connecté : supprimer par l'ID du panier
    if (!empty($cart_id) && is_numeric($cart_id)) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $_SESSION['user_id']]);
    } elseif ($product_id > 0) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$product_id, $_SESSION['user_id']]);
    }
} else {
    // Utilisateur non connecté : supprimer de la session
    if (isset($_SESSION['guest_cart'])) {
        // Extraire l'ID produit du cart_id (format: guest_123)
        if (strpos($cart_id, 'guest_') === 0) {
            $product_id = (int)str_replace('guest_', '', $cart_id);
        }
        
        foreach ($_SESSION['guest_cart'] as $key => $item) {
            if ($item['product_id'] == $product_id) {
                unset($_SESSION['guest_cart'][$key]);
                $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']);
                break;
            }
        }
    }
}

// Récupérer le nouveau compteur
$cart_count = getCartCount();

// Retourner la réponse JSON
echo json_encode([
    'success' => true,
    'cart_count' => $cart_count,
    'message' => 'Article supprimé du panier'
]);
exit();
?>