<?php
// admin/product-save.php - Sauvegarde du produit avec images
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();
header('Content-Type: application/json');

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

$name = clean($_POST['name'] ?? '');
$slug = !empty($_POST['slug']) ? createSlug($_POST['slug']) : createSlug($name);
$description = clean($_POST['description'] ?? '');
$short_description = clean($_POST['short_description'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$compare_price = !empty($_POST['compare_price']) ? (float)$_POST['compare_price'] : null;
$stock = (int)($_POST['stock'] ?? 0);
$category = clean($_POST['category'] ?? '');
$tags = clean($_POST['tags'] ?? '');
$is_featured = isset($_POST['is_featured']) ? 1 : 0;
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Images
$images = $_POST['images'] ?? [];
$primary_image = $_POST['primary_image'] ?? ($images[0] ?? '');

if (empty($name) || $price <= 0 || empty($category)) {
    echo json_encode(['success' => false, 'error' => 'Veuillez remplir tous les champs obligatoires']);
    exit();
}

// Vérifier si le slug existe déjà
$stmt = $conn->prepare("SELECT id FROM products WHERE slug = ?");
$stmt->execute([$slug]);
if ($stmt->fetch()) {
    $slug = $slug . '-' . uniqid();
}

$conn->beginTransaction();

try {
    // Insérer le produit
    $stmt = $conn->prepare("
        INSERT INTO products (name, slug, description, short_description, price, compare_price, stock, category, tags, is_featured, is_active, image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name, $slug, $description, $short_description, $price, $compare_price, 
        $stock, $category, $tags, $is_featured, $is_active, $primary_image
    ]);
    $product_id = $conn->lastInsertId();
    
    // Insérer les images additionnelles
    foreach ($images as $index => $filename) {
        $is_primary = ($filename === $primary_image);
        $stmt = $conn->prepare("
            INSERT INTO product_images (product_id, image_path, sort_order, is_primary)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$product_id, $filename, $index, $is_primary ? 1 : 0]);
    }
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'product_id' => $product_id]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>