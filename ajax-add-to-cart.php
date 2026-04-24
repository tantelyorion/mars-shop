<?php
// ajax-add-to-cart.php - Endpoint AJAX pour ajouter au panier
require_once 'config/database.php';
require_once 'includes/functions.php';

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Forcer l'en-tête JSON
header('Content-Type: application/json');

// Vérifier si la requête est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérifier si product_id est présent
if (!isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
    exit();
}

$product_id = (int)$_POST['product_id'];
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Connexion à la BDD
$conn = getConnection();

// Vérifier que le produit existe et a du stock
$stmt = $conn->prepare("SELECT id, stock, name FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
    exit();
}

if ($product['stock'] < $quantity) {
    // CORRECTION ICI : ajout du '=>' après 'success'
    echo json_encode(['success' => false, 'message' => 'Stock insuffisant']);
    exit();
}

// Ajouter au panier en utilisant la fonction existante
addToCart($product_id, $quantity);

// Récupérer le nouveau compteur
$cart_count = getCartCount();

// Retourner la réponse
echo json_encode([
    'success' => true,
    'cart_count' => $cart_count,
    'message' => 'Produit ajouté au panier',
    'product_name' => $product['name']
]);
exit();
?>