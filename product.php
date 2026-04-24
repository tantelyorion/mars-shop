<?php
// product.php - Page produit (CORRIGÉ)
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: shop.php');
    exit();
}

$conn = getConnection();

// Récupérer le produit
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: shop.php');
    exit();
}

// Incrémenter les vues
$stmt = $conn->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$stmt->execute([$product_id]);

// Vérifier si dans wishlist
$in_wishlist = isInWishlist($product_id);

// Récupérer les avis
$stmt = $conn->prepare("
    SELECT r.*, u.username 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Calculer la note moyenne
$avg_rating = 0;
if (count($reviews) > 0) {
    $total = array_sum(array_column($reviews, 'rating'));
    $avg_rating = $total / count($reviews);
}

// Produits similaires
$stmt = $conn->prepare("
    SELECT * FROM products 
    WHERE category = ? AND id != ? AND is_active = 1 
    LIMIT 4
");
$stmt->execute([$product['category'], $product_id]);
$related = $stmt->fetchAll();

// Ajout au panier (formulaire POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = max(1, min((int)$_POST['quantity'], $product['stock']));
    addToCart($product_id, $quantity);
    setFlashMessage('success', 'Produit ajouté au panier');
    header("Location: cart.php");
    exit();
}
?>

<div class="product-page">
    <div class="container">
        <div class="product-grid">
            <div class="product-image-main">
                <div class="image-placeholder">
                    <i class="fas fa-box-open"></i>
                </div>
            </div>
            
            <div class="product-details">
                <h1><?php echo clean($product['name']); ?></h1>
                
                <div class="product-rating">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star" style="color: <?php echo $i <= round($avg_rating) ? '#f59e0b' : '#2a2a35'; ?>"></i>
                    <?php endfor; ?>
                    <span>(<?php echo count($reviews); ?> avis)</span>
                </div>
                
                <div class="product-price-large">
                    <?php echo formatPrice($product['price']); ?>
                    <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                    <span class="old-price"><?php echo formatPrice($product['compare_price']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="product-stock">
                    <?php if($product['stock'] > 0): ?>
                        <span style="color: var(--success);"><i class="fas fa-check-circle"></i> En stock (<?php echo $product['stock']; ?> disponibles)</span>
                    <?php else: ?>
                        <span style="color: var(--error);"><i class="fas fa-times-circle"></i> Rupture de stock</span>
                    <?php endif; ?>
                </div>
                
                <div class="product-description">
                    <h3>Description</h3>
                    <p><?php echo nl2br(clean($product['description'])); ?></p>
                </div>
                
                <?php if($product['stock'] > 0): ?>
                <form method="POST" class="product-form">
                    <div class="quantity-selector">
                        <label>Quantité :</label>
                        <div class="quantity-input">
                            <button type="button" class="quantity-minus">-</button>
                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                            <button type="button" class="quantity-plus">+</button>
                        </div>
                    </div>
                    <button type="submit" name="add_to_cart" class="btn-primary btn-large add-to-cart-form">
                        <i class="fas fa-shopping-bag"></i> Ajouter au panier
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="product-actions-side">
                    <?php if($in_wishlist): ?>
                        <a href="?remove_wishlist=1" class="btn-secondary wishlist-link" style="width: 100%; text-align: center;">
                            <i class="fas fa-heart" style="color: var(--primary);"></i> Dans ma wishlist
                        </a>
                    <?php else: ?>
                        <a href="?wishlist=1" class="btn-secondary wishlist-link" style="width: 100%; text-align: center;">
                            <i class="far fa-heart"></i> Ajouter à ma wishlist
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="product-meta">
                    <p><strong>Catégorie :</strong> <?php echo clean($product['category']); ?></p>
                    <p><strong>Référence :</strong> MARS-<?php echo str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Avis clients -->
        <div class="reviews-section">
            <h3>Avis clients</h3>
            
            <?php if(count($reviews) > 0): ?>
                <?php foreach($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <strong><?php echo clean($review['username']); ?></strong>
                        <div class="review-stars">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#f59e0b' : '#2a2a35'; ?>; font-size: 0.8rem;"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="review-date"><?php echo formatDate($review['created_at'], 'd/m/Y'); ?></span>
                    </div>
                    <p><?php echo nl2br(clean($review['comment'])); ?></p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-reviews">Aucun avis pour le moment.</p>
            <?php endif; ?>
        </div>
        
        <!-- Produits similaires -->
        <?php if(count($related) > 0): ?>
        <div class="related-products">
            <h3>Produits similaires</h3>
            <div class="products-grid">
                <?php foreach($related as $item): ?>
                <div class="product-card">
                    <div class="product-image">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="product-info">
                        <h3><?php echo clean($item['name']); ?></h3>
                        <div class="product-price"><?php echo formatPrice($item['price']); ?></div>
                        <a href="product.php?id=<?php echo $item['id']; ?>" class="btn-secondary btn-sm">Voir</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Styles existants... */
.product-page {
    padding: 20px 0;
}

.product-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
}

.image-placeholder {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    border-radius: 16px;
    height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-placeholder i {
    font-size: 8rem;
    color: rgba(255,255,255,0.3);
}

.product-details h1 {
    margin-bottom: 12px;
}

.product-rating {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.product-price-large {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-light);
    margin: 16px 0;
}

.old-price {
    font-size: 1rem;
    color: var(--text-muted);
    text-decoration: line-through;
    margin-left: 12px;
}

.product-stock {
    margin: 16px 0;
}

.product-description {
    margin: 24px 0;
    padding: 16px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.product-description h3 {
    margin-bottom: 12px;
    font-size: 1rem;
}

.product-form {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    margin: 24px 0;
}

.quantity-selector {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.quantity-input {
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}

.quantity-input button {
    width: 36px;
    height: 36px;
    background: var(--gray-light);
    border: none;
    color: var(--text);
    cursor: pointer;
    font-size: 1.2rem;
}

.quantity-input button:hover {
    background: var(--primary);
}

.quantity-input input {
    width: 60px;
    height: 36px;
    text-align: center;
    background: var(--gray);
    border: none;
    color: var(--text);
}

.btn-large {
    padding: 12px 32px;
    font-size: 1rem;
}

.product-actions-side {
    margin: 16px 0;
}

.product-meta {
    padding-top: 16px;
    border-top: 1px solid var(--border);
    font-size: 0.85rem;
    color: var(--text-muted);
}

.reviews-section {
    margin-top: 40px;
    padding-top: 40px;
    border-top: 1px solid var(--border);
}

.reviews-section h3 {
    margin-bottom: 24px;
}

.review-item {
    background: var(--gray);
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 16px;
}

.review-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.review-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.no-reviews {
    color: var(--text-muted);
    text-align: center;
    padding: 40px;
}

.related-products {
    margin-top: 40px;
    padding-top: 40px;
    border-top: 1px solid var(--border);
}

.related-products h3 {
    margin-bottom: 24px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}

@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .image-placeholder {
        height: 300px;
    }
    
    .product-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .quantity-selector {
        width: 100%;
    }
    
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Gestion des quantités
document.querySelectorAll('.quantity-input').forEach(wrapper => {
    const input = wrapper.querySelector('input');
    const minus = wrapper.querySelector('.quantity-minus');
    const plus = wrapper.querySelector('.quantity-plus');
    
    if (minus) {
        minus.addEventListener('click', () => {
            let val = parseInt(input.value);
            const min = parseInt(input.min) || 1;
            if (val > min) {
                input.value = val - 1;
            }
        });
    }
    
    if (plus) {
        plus.addEventListener('click', () => {
            let val = parseInt(input.value);
            const max = parseInt(input.max) || Infinity;
            if (val < max) {
                input.value = val + 1;
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>