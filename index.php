<?php
// index.php - Page d'accueil avec images des produits
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$conn = getConnection();

// Récupérer les produits en vedette avec leurs images
$stmt = $conn->prepare("
    SELECT p.*, 
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p 
    WHERE p.is_featured = 1 AND p.is_active = 1 
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$stmt->execute();
$featured_products = $stmt->fetchAll();

// Récupérer les nouveaux produits avec leurs images
$stmt = $conn->prepare("
    SELECT p.*, 
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p 
    WHERE p.is_active = 1 
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$stmt->execute();
$new_products = $stmt->fetchAll();

// Récupérer les catégories avec comptage
$stmt = $conn->query("SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category");
$categories = $stmt->fetchAll();

$category_counts = [];
foreach ($categories as $cat) {
    $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category = ? AND is_active = 1");
    $stmt_count->execute([$cat['category']]);
    $category_counts[$cat['category']] = $stmt_count->fetch()['count'];
}

// Récupérer les IDs dans wishlist si connecté
$wishlist_ids = [];
if (isLoggedIn()) {
    $stmt_wish = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt_wish->execute([$_SESSION['user_id']]);
    $wishlist_ids = array_column($stmt_wish->fetchAll(), 'product_id');
}
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Mars Shop</h1>
            <p>L'équipement spatial pour les aventuriers modernes</p>
            <a href="shop.php" class="btn-primary">Découvrir la boutique</a>
        </div>
    </div>
</section>

<!-- Catégories -->
<section class="categories-section">
    <div class="container">
        <h2 class="section-title">Catégories</h2>
        <div class="categories-grid">
            <?php foreach($categories as $cat): ?>
            <a href="shop.php?category=<?php echo urlencode($cat['category']); ?>" class="category-card">
                <i class="fas fa-<?php 
                    echo $cat['category'] == 'Vêtements' ? 'tshirt' : 
                        ($cat['category'] == 'Accessoires' ? 'watch' : 
                        ($cat['category'] == 'Alimentation' ? 'apple-alt' : 
                        ($cat['category'] == 'Décoration' ? 'home' : 'gamepad'))); 
                ?>"></i>
                <span><?php echo clean($cat['category']); ?></span>
                <small><?php echo $category_counts[$cat['category']] ?? 0; ?> produits</small>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Produits vedettes -->
<section class="products-section">
    <div class="container">
        <h2 class="section-title">Produits vedettes</h2>
        
        <?php if(count($featured_products) > 0): ?>
        <div class="products-grid">
            <?php foreach($featured_products as $product): ?>
            <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                <div class="product-badge">-<?php echo round((1 - $product['price']/$product['compare_price']) * 100); ?>%</div>
                <?php endif; ?>
                <div class="product-image">
                    <?php if(!empty($product['primary_image']) && file_exists('uploads/products/' . $product['primary_image'])): ?>
                        <img src="uploads/products/<?php echo $product['primary_image']; ?>" alt="<?php echo clean($product['name']); ?>">
                    <?php elseif(!empty($product['image']) && file_exists('uploads/products/' . $product['image'])): ?>
                        <img src="uploads/products/<?php echo $product['image']; ?>" alt="<?php echo clean($product['name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-box-open"></i>
                    <?php endif; ?>
                    <?php if($product['stock'] > 0 && $product['stock'] < 5): ?>
                    <div class="stock-badge">Plus que <?php echo $product['stock']; ?></div>
                    <?php elseif($product['stock'] <= 0): ?>
                    <div class="stock-badge out">Rupture</div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h3><?php echo clean($product['name']); ?></h3>
                    <div class="product-price">
                        <?php echo formatPrice($product['price']); ?>
                        <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                        <span class="old-price"><?php echo formatPrice($product['compare_price']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="product-actions">
                        <a href="product.php?id=<?php echo $product['id']; ?>" class="btn-secondary btn-sm">Voir</a>
                        <?php if($product['stock'] > 0): ?>
                        <button class="btn-primary btn-sm add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                            <i class="fas fa-shopping-bag"></i> Ajouter
                        </button>
                        <?php endif; ?>
                        <button class="wishlist-btn-sm <?php echo in_array($product['id'], $wishlist_ids) ? 'active' : ''; ?>" 
                                data-product-id="<?php echo $product['id']; ?>">
                            <i class="<?php echo in_array($product['id'], $wishlist_ids) ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Aucun produit vedette</div>
        <?php endif; ?>
    </div>
</section>

<!-- Nouveautés -->
<section class="products-section bg-dark">
    <div class="container">
        <h2 class="section-title">Nouveautés</h2>
        
        <?php if(count($new_products) > 0): ?>
        <div class="products-grid">
            <?php foreach($new_products as $product): ?>
            <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                <div class="product-image">
                    <?php if(!empty($product['primary_image']) && file_exists('uploads/products/' . $product['primary_image'])): ?>
                        <img src="uploads/products/<?php echo $product['primary_image']; ?>" alt="<?php echo clean($product['name']); ?>">
                    <?php elseif(!empty($product['image']) && file_exists('uploads/products/' . $product['image'])): ?>
                        <img src="uploads/products/<?php echo $product['image']; ?>" alt="<?php echo clean($product['name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-box-open"></i>
                    <?php endif; ?>
                    <?php if($product['stock'] > 0 && $product['stock'] < 5): ?>
                    <div class="stock-badge">Plus que <?php echo $product['stock']; ?></div>
                    <?php elseif($product['stock'] <= 0): ?>
                    <div class="stock-badge out">Rupture</div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h3><?php echo clean($product['name']); ?></h3>
                    <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                    <div class="product-actions">
                        <a href="product.php?id=<?php echo $product['id']; ?>" class="btn-secondary btn-sm">Voir</a>
                        <?php if($product['stock'] > 0): ?>
                        <button class="btn-primary btn-sm add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                            <i class="fas fa-shopping-bag"></i> Ajouter
                        </button>
                        <?php endif; ?>
                        <button class="wishlist-btn-sm <?php echo in_array($product['id'], $wishlist_ids) ? 'active' : ''; ?>" 
                                data-product-id="<?php echo $product['id']; ?>">
                            <i class="<?php echo in_array($product['id'], $wishlist_ids) ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Aucune nouveauté</div>
        <?php endif; ?>
        
        <div class="text-center" style="margin-top: 30px;">
            <a href="shop.php" class="btn-primary">Voir tous les produits</a>
        </div>
    </div>
</section>

<style>
/* Hero */
.hero {
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    border-radius: 16px;
    margin-bottom: 40px;
}

.hero h1 {
    font-size: 3rem;
    margin-bottom: 16px;
}

.hero p {
    font-size: 1.2rem;
    margin-bottom: 24px;
    opacity: 0.9;
}

/* Section title */
.section-title {
    text-align: center;
    margin-bottom: 30px;
    font-size: 1.5rem;
}

/* Categories */
.categories-section {
    margin-bottom: 40px;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
}

.category-card {
    text-align: center;
    padding: 20px;
    background: var(--gray);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.2s;
    border: 1px solid var(--border);
}

.category-card:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
}

.category-card i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

.category-card span {
    display: block;
    font-weight: 500;
}

.category-card small {
    font-size: 0.7rem;
    color: var(--text-muted);
}

/* Products section */
.products-section {
    padding: 40px 0;
}

.products-section.bg-dark {
    background: rgba(0,0,0,0.2);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 24px;
}

.product-card {
    background: var(--gray);
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.2s, border-color 0.2s;
    border: 1px solid var(--border);
    position: relative;
}

.product-card:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
}

.product-image {
    height: 200px;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-image i {
    font-size: 3rem;
    color: rgba(255,255,255,0.3);
}

.product-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--success);
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 2;
}

.stock-badge {
    position: absolute;
    bottom: 12px;
    left: 12px;
    background: rgba(0,0,0,0.7);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 2;
    color: var(--warning);
}

.stock-badge.out {
    color: var(--error);
}

.product-info {
    padding: 16px;
}

.product-info h3 {
    font-size: 1rem;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary-light);
    margin-bottom: 12px;
}

.old-price {
    font-size: 0.8rem;
    color: var(--text-muted);
    text-decoration: line-through;
    margin-left: 8px;
}

.product-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.btn-sm {
    padding: 8px 12px;
    font-size: 0.8rem;
}

.wishlist-btn-sm {
    width: 36px;
    height: 36px;
    background: var(--gray-light);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wishlist-btn-sm:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.wishlist-btn-sm.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.text-center {
    text-align: center;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .hero {
        padding: 40px 20px;
    }
    
    .hero h1 {
        font-size: 2rem;
    }
    
    .hero p {
        font-size: 1rem;
    }
    
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .product-actions {
        flex-direction: column;
    }
    
    .wishlist-btn-sm {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Attacher les événements après chargement
document.addEventListener('DOMContentLoaded', function() {
    const addButtons = document.querySelectorAll('.add-to-cart-btn');
    addButtons.forEach(btn => {
        btn.removeEventListener('click', handleIndexAddToCart);
        btn.addEventListener('click', handleIndexAddToCart);
    });
    
    const wishlistButtons = document.querySelectorAll('.wishlist-btn-sm');
    wishlistButtons.forEach(btn => {
        btn.removeEventListener('click', handleIndexWishlist);
        btn.addEventListener('click', handleIndexWishlist);
    });
});

// Fonction d'ajout au panier
async function handleIndexAddToCart(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const button = e.currentTarget;
    const productId = button.dataset.productId;
    
    if (!productId) return;
    
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', 1);
        
        const response = await fetch('ajax-add-to-cart.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            button.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
            
            const cartBadge = document.querySelector('.nav-cart .badge');
            if (cartBadge) {
                cartBadge.textContent = result.cart_count;
                cartBadge.style.display = result.cart_count > 0 ? 'inline-flex' : 'none';
            }
            
            showIndexNotification('Produit ajouté au panier', 'success');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 1500);
        } else {
            button.innerHTML = originalHTML;
            button.disabled = false;
            showIndexNotification(result.message || 'Erreur', 'error');
        }
    } catch (error) {
        button.innerHTML = originalHTML;
        button.disabled = false;
        showIndexNotification('Erreur de connexion', 'error');
    }
}

// Fonction wishlist
async function handleIndexWishlist(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const button = e.currentTarget;
    const productId = button.dataset.productId;
    const isActive = button.classList.contains('active');
    
    if (!productId) return;
    
    const originalIcon = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('action', isActive ? 'remove' : 'add');
        
        const response = await fetch('ajax-wishlist.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (!isActive) {
                button.classList.add('active');
                button.innerHTML = '<i class="fas fa-heart"></i>';
                showIndexNotification('Ajouté à la wishlist', 'success');
            } else {
                button.classList.remove('active');
                button.innerHTML = '<i class="far fa-heart"></i>';
                showIndexNotification('Retiré de la wishlist', 'info');
            }
            
            const wishlistBadge = document.querySelector('.nav-wishlist .badge');
            if (wishlistBadge && result.wishlist_count > 0) {
                wishlistBadge.textContent = result.wishlist_count;
                wishlistBadge.style.display = 'inline-flex';
            } else if (wishlistBadge && result.wishlist_count === 0) {
                wishlistBadge.style.display = 'none';
            }
        } else {
            button.innerHTML = originalIcon;
            showIndexNotification('Erreur', 'error');
        }
    } catch (error) {
        button.innerHTML = originalIcon;
        showIndexNotification('Erreur de connexion', 'error');
    } finally {
        button.disabled = false;
    }
}

// Notification
function showIndexNotification(message, type = 'success') {
    const existing = document.querySelector('.cart-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = `cart-notification`;
    notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span><button class="notification-close">&times;</button>`;
    notification.style.cssText = `
        position: fixed; bottom: 20px; right: 20px; background: var(--gray, #1a1a24);
        border-left: 4px solid ${type === 'success' ? '#10b981' : '#ef4444'};
        padding: 12px 20px; border-radius: 8px; z-index: 10000;
        display: flex; align-items: center; gap: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3); animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = 'background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:1.2rem;margin-left:8px';
    closeBtn.onclick = () => notification.remove();
    
    setTimeout(() => notification.remove(), 3000);
}
</script>

<?php require_once 'includes/footer.php'; ?>