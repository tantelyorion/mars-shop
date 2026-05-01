<?php
// wishlist.php - Wishlist complète avec miniatures et gestion AJAX
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$conn = getConnection();
$wishlist_items = [];

// Récupérer les favoris avec l'image principale
if(isLoggedIn()) {
    $stmt = $conn->prepare("
        SELECT w.product_id, p.*, 
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM wishlist w 
        JOIN products p ON w.product_id = p.id 
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist_items = $stmt->fetchAll();
} else if(isset($_SESSION['guest_wishlist'])) {
    $ids = $_SESSION['guest_wishlist'];
    if(!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("
            SELECT p.*, 
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p 
            WHERE p.id IN ($placeholders)
        ");
        $stmt->execute($ids);
        $wishlist_items = $stmt->fetchAll();
    }
}

// Suppression individuelle
if(isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    removeFromWishlist($product_id);
    setFlashMessage('info', 'Produit retiré de votre wishlist');
    header('Location: wishlist.php');
    exit();
}

// Suppression multiple
if(isset($_POST['clear_wishlist'])) {
    if(isLoggedIn()) {
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        unset($_SESSION['guest_wishlist']);
    }
    setFlashMessage('info', 'Votre wishlist a été vidée');
    header('Location: wishlist.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma wishlist - Mars Shop</title>
    <style>
        /* ============================================
           WISHLIST.PHP - STYLES COMPLETS
           ============================================ */
        
        :root {
            --primary: #c14432;
            --primary-dark: #8b3a2b;
            --primary-light: #e8755a;
            --gray: #1a1a24;
            --gray-light: #2a2a35;
            --text: #ffffff;
            --text-muted: #a0a0b0;
            --border: #2a2a35;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        
        .wishlist-page {
            padding: 30px 0;
            min-height: calc(100vh - 200px);
        }
        
        .wishlist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .wishlist-header h1 {
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .wishlist-header h1 i {
            color: var(--primary);
        }
        
        .wishlist-header p {
            color: var(--text-muted);
        }
        
        .wishlist-stats {
            display: flex;
            align-items: center;
            gap: 20px;
            background: var(--gray);
            padding: 10px 20px;
            border-radius: 40px;
        }
        
        .wishlist-stats span {
            color: var(--primary-light);
            font-weight: 600;
        }
        
        .clear-wishlist-btn {
            background: rgba(239,68,68,0.15);
            border: 1px solid var(--error);
            color: var(--error);
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .clear-wishlist-btn:hover {
            background: var(--error);
            color: white;
        }
        
        /* Grille produits */
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .wishlist-card {
            background: var(--gray);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            position: relative;
        }
        
        .wishlist-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .product-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--success);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .product-image {
            height: 220px;
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
            transition: transform 0.3s ease;
        }
        
        .wishlist-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-image i {
            font-size: 3.5rem;
            color: rgba(255,255,255,0.3);
        }
        
        .stock-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .stock-badge.in-stock {
            background: rgba(16,185,129,0.9);
            color: white;
        }
        
        .stock-badge.low-stock {
            background: rgba(245,158,11,0.9);
            color: #000;
        }
        
        .stock-badge.out-stock {
            background: rgba(239,68,68,0.9);
            color: white;
        }
        
        .product-info {
            padding: 18px;
        }
        
        .product-title {
            font-size: 1rem;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-title a {
            color: var(--text);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .product-title a:hover {
            color: var(--primary-light);
        }
        
        .product-category {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 8px;
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
            gap: 10px;
            align-items: center;
            margin-top: 12px;
        }
        
        .btn-sm {
            flex: 1;
            padding: 8px 12px;
            font-size: 0.8rem;
            border-radius: 8px;
        }
        
        .remove-wishlist {
            width: 38px;
            height: 38px;
            background: rgba(239,68,68,0.1);
            border: 1px solid var(--error);
            border-radius: 8px;
            color: var(--error);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .remove-wishlist:hover {
            background: var(--error);
            color: white;
        }
        
        /* Panier vide */
        .empty-wishlist {
            text-align: center;
            padding: 80px 20px;
            background: var(--gray);
            border-radius: 20px;
        }
        
        .empty-wishlist i {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        
        .empty-wishlist h3 {
            margin-bottom: 10px;
        }
        
        .empty-wishlist p {
            color: var(--text-muted);
            margin-bottom: 25px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .wishlist-header {
                flex-direction: column;
                text-align: center;
            }
            
            .wishlist-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .product-image {
                height: 180px;
            }
            
            .product-actions {
                flex-direction: column;
            }
            
            .remove-wishlist {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .wishlist-grid {
                grid-template-columns: 1fr;
            }
            
            .product-image {
                height: 220px;
            }
        }
    </style>
</head>
<body>

<?php require_once 'includes/header.php'; ?>

<main>
    <div class="wishlist-page">
        <div class="container">
            <div class="wishlist-header">
                <div>
                    <h1><i class="fas fa-heart"></i> Ma wishlist</h1>
                    <p>Retrouvez ici tous vos produits favoris</p>
                </div>
                
                <?php if(count($wishlist_items) > 0): ?>
                <div class="wishlist-stats">
                    <span><?php echo count($wishlist_items); ?></span> produit(s) dans votre wishlist
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Vider toute votre wishlist ? Cette action est irréversible.');">
                        <button type="submit" name="clear_wishlist" class="clear-wishlist-btn">
                            <i class="fas fa-trash-alt"></i> Tout supprimer
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if(count($wishlist_items) > 0): ?>
            <div class="wishlist-grid">
                <?php foreach($wishlist_items as $item): ?>
                <div class="wishlist-card" data-product-id="<?php echo $item['id']; ?>">
                    <?php if($item['compare_price'] && $item['compare_price'] > $item['price']): ?>
                    <div class="product-badge">-<?php echo round((1 - $item['price']/$item['compare_price']) * 100); ?>%</div>
                    <?php endif; ?>
                    
                    <div class="product-image">
                        <?php if(!empty($item['primary_image']) && file_exists('uploads/products/' . $item['primary_image'])): ?>
                            <img src="uploads/products/<?php echo $item['primary_image']; ?>" alt="<?php echo clean($item['name']); ?>" loading="lazy">
                        <?php elseif(!empty($item['image']) && file_exists('uploads/products/' . $item['image'])): ?>
                            <img src="uploads/products/<?php echo $item['image']; ?>" alt="<?php echo clean($item['name']); ?>" loading="lazy">
                        <?php else: ?>
                            <i class="fas fa-gift"></i>
                        <?php endif; ?>
                        
                        <?php if($item['stock'] <= 0): ?>
                        <div class="stock-badge out-stock">Rupture</div>
                        <?php elseif($item['stock'] < 5): ?>
                        <div class="stock-badge low-stock">Plus que <?php echo $item['stock']; ?></div>
                        <?php else: ?>
                        <div class="stock-badge in-stock">En stock</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <h3 class="product-title">
                            <a href="product.php?id=<?php echo $item['id']; ?>"><?php echo clean($item['name']); ?></a>
                        </h3>
                        <div class="product-category">
                            <i class="fas fa-tag"></i> <?php echo clean($item['category']); ?>
                        </div>
                        <div class="product-price">
                            <?php echo formatPrice($item['price']); ?>
                            <?php if($item['compare_price'] && $item['compare_price'] > $item['price']): ?>
                            <span class="old-price"><?php echo formatPrice($item['compare_price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-actions">
                            <a href="product.php?id=<?php echo $item['id']; ?>" class="btn-secondary btn-sm">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <?php if($item['stock'] > 0): ?>
                            <button class="btn-primary btn-sm add-to-cart-btn" data-product-id="<?php echo $item['id']; ?>">
                                <i class="fas fa-shopping-bag"></i> Ajouter
                            </button>
                            <?php else: ?>
                            <button class="btn-secondary btn-sm" disabled style="opacity:0.5;">
                                <i class="fas fa-times"></i> Indisponible
                            </button>
                            <?php endif; ?>
                            <a href="wishlist.php?remove=<?php echo $item['id']; ?>" class="remove-wishlist" 
                               onclick="return confirm('Retirer ce produit de votre wishlist ?');" title="Retirer">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center" style="margin-top: 40px;">
                <a href="shop.php" class="btn-primary">
                    <i class="fas fa-store"></i> Continuer mes achats
                </a>
            </div>
            
            <?php else: ?>
            <div class="empty-wishlist">
                <i class="far fa-heart"></i>
                <h3>Votre wishlist est vide</h3>
                <p>Ajoutez vos produits préférés en cliquant sur le cœur ❤️</p>
                <a href="shop.php" class="btn-primary">
                    <i class="fas fa-store"></i> Découvrir nos produits
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// ============================================
// WISHLIST.JS - COMPLET
// ============================================

// Fonction d'ajout au panier
async function handleAddToCart(e) {
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
            
            // Mettre à jour le badge du panier
            const cartBadge = document.querySelector('.nav-cart .badge');
            if (cartBadge) {
                cartBadge.textContent = result.cart_count;
                cartBadge.style.display = result.cart_count > 0 ? 'inline-flex' : 'none';
            }
            
            showNotification('Produit ajouté au panier !', 'success');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 1500);
        } else {
            button.innerHTML = originalHTML;
            button.disabled = false;
            showNotification(result.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        button.innerHTML = originalHTML;
        button.disabled = false;
        showNotification('Erreur de connexion', 'error');
    }
}

// Notification
function showNotification(message, type = 'success') {
    const existing = document.querySelector('.wishlist-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'wishlist-notification';
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        info: '#3b82f6'
    };
    
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #1a1a24;
        border-left: 4px solid ${colors[type] || colors.success};
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = 'background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:1.2rem;margin-left:8px';
    closeBtn.onclick = () => notification.remove();
    
    setTimeout(() => notification.remove(), 3000);
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.removeEventListener('click', handleAddToCart);
        btn.addEventListener('click', handleAddToCart);
    });
});

// Animation keyframes
if (!document.querySelector('#wishlist-styles')) {
    const style = document.createElement('style');
    style.id = 'wishlist-styles';
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}
</script>

<?php require_once 'includes/footer.php'; ?>