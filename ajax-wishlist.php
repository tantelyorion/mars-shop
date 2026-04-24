<?php
// ajax-wishlist.php - Endpoint AJAX pour la wishlist
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
$action = isset($_POST['action']) ? $_POST['action'] : 'add';

// Connexion à la BDD
$conn = getConnection();

// Vérifier que le produit existe
$stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$product_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
    exit();
}

// Ajouter ou retirer de la wishlist
if ($action === 'add') {
    addToWishlist($product_id);
} else {
    removeFromWishlist($product_id);
}

// Récupérer le nouveau compteur
$wishlist_count = getWishlistCount();

// Retourner la réponse
echo json_encode([
    'success' => true,
    'wishlist_count' => $wishlist_count,
    'is_active' => ($action === 'add'),
    'message' => ($action === 'add') ? 'Ajouté à la wishlist' : 'Retiré de la wishlist'
]);
exit();
?>