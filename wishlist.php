<?php
// wishlist.php - Wishlist avec gestion session
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$conn = getConnection();
$wishlist_items = [];

// Récupérer les favoris
if(isLoggedIn()) {
    $stmt = $conn->prepare("
        SELECT w.product_id, p.* 
        FROM wishlist w 
        JOIN products p ON w.product_id = p.id 
        WHERE w.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist_items = $stmt->fetchAll();
} else if(isset($_SESSION['guest_wishlist'])) {
    $ids = $_SESSION['guest_wishlist'];
    if(!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $wishlist_items = $stmt->fetchAll();
    }
}

// Suppression
if(isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    removeFromWishlist($product_id);
    header('Location: wishlist.php');
    exit();
}
?>

<div class="cart-container">
    <h2><i class="far fa-heart"></i> Ma wishlist</h2>
    
    <?php if(count($wishlist_items) > 0): ?>
    <div class="products-grid">
        <?php foreach($wishlist_items as $item): ?>
        <div class="product-card">
            <div class="product-image">
                <i class="fas fa-gift"></i>
            </div>
            <div class="product-info">
                <h3 class="product-title"><?php echo clean($item['name']); ?></h3>
                <div class="product-price"><?php echo formatPrice($item['price']); ?></div>
                <div class="product-actions">
                    <a href="product.php?id=<?php echo $item['id']; ?>" class="btn-secondary btn-sm">Voir</a>
                    <button class="btn-primary btn-sm add-to-cart" data-product-id="<?php echo $item['id']; ?>">
                        <i class="fas fa-cart-plus"></i> Ajouter
                    </button>
                    <a href="wishlist.php?remove=<?php echo $item['id']; ?>" class="btn-secondary btn-sm" 
                       onclick="return confirm('Supprimer des favoris ?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-cart">
        <i class="far fa-heart"></i>
        <h3>Votre wishlist est vide</h3>
        <p>Ajoutez vos produits préférés en cliquant sur le cœur ❤️</p>
        <a href="shop.php" class="btn-primary" style="margin-top: 16px;">Découvrir nos produits</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>