<?php
// shop.php - Page boutique complète avec filtres, images et AJAX
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$conn = getConnection();

// Paramètres de filtrage
$category = isset($_GET['category']) ? clean($_GET['category']) : '';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec récupération de l'image principale
$sql = "SELECT p.*, 
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM products p 
        WHERE p.is_active = 1";
$count_sql = "SELECT COUNT(*) as total FROM products WHERE is_active = 1";
$params = [];

if ($category) {
    $sql .= " AND p.category = ?";
    $count_sql .= " AND category = ?";
    $params[] = $category;
}

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $count_sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Tri
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY p.name DESC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

$sql .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Exécuter la requête
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Compter le total
$stmt_count = $conn->prepare($count_sql);
$count_params = array_slice($params, 0, count($params) - 2);
$stmt_count->execute($count_params);
$total_products = $stmt_count->fetch()['total'] ?? 0;
$total_pages = ceil($total_products / $per_page);

// Récupérer les catégories
$stmt_cats = $conn->query("SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category");
$categories = $stmt_cats->fetchAll();

// Récupérer les IDs des produits dans la wishlist
$wishlist_ids = [];
if (isLoggedIn()) {
    $stmt_wish = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt_wish->execute([$_SESSION['user_id']]);
    $wishlist_ids = array_column($stmt_wish->fetchAll(), 'product_id');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique - Mars Shop</title>
    <style>
        /* ============================================
           SHOP.PHP - STYLES COMPLETS
           ============================================ */
        
        :root {
            --primary: #c14432;
            --primary-dark: #8b3a2b;
            --primary-light: #e8755a;
            --dark: #0f0f14;
            --darker: #0a0a0e;
            --gray: #1a1a24;
            --gray-light: #2a2a35;
            --text: #ffffff;
            --text-muted: #a0a0b0;
            --border: #2a2a35;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        
        .shop-page {
            padding: 30px 0;
        }
        
        .shop-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .shop-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .shop-header p {
            color: var(--text-muted);
            font-size: 1rem;
        }
        
        /* Filtres */
        .shop-filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .search-form {
            display: flex;
            flex: 1;
            max-width: 350px;
        }
        
        .search-form input {
            flex: 1;
            padding: 12px 16px;
            background: var(--gray);
            border: 1px solid var(--border);
            border-radius: 12px 0 0 12px;
            color: var(--text);
            font-size: 0.9rem;
        }
        
        .search-form input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .search-form button {
            padding: 12px 20px;
            background: var(--primary);
            border: none;
            border-radius: 0 12px 12px 0;
            color: white;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .search-form button:hover {
            background: var(--primary-dark);
        }
        
        .sort-select {
            padding: 12px 16px;
            background: var(--gray);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .sort-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Catégories */
        .categories-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .categories-filter a {
            padding: 8px 20px;
            background: var(--gray);
            border-radius: 30px;
            color: var(--text);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .categories-filter a:hover,
        .categories-filter a.active {
            background: var(--primary);
            color: white;
        }
        
        /* Grille produits */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .product-card {
            background: var(--gray);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-image i {
            font-size: 3.5rem;
            color: rgba(255,255,255,0.3);
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
        
        .stock-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(0,0,0,0.8);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .stock-badge.in-stock {
            color: var(--success);
        }
        
        .stock-badge.low-stock {
            background: var(--warning);
            color: #000;
        }
        
        .stock-badge.out-stock {
            color: var(--error);
        }
        
        .product-info {
            padding: 18px;
        }
        
        .product-info h3 {
            font-size: 1rem;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-info h3 a {
            color: var(--text);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .product-info h3 a:hover {
            color: var(--primary-light);
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
        }
        
        .btn-sm {
            flex: 1;
            padding: 8px 12px;
            font-size: 0.8rem;
            border-radius: 8px;
        }
        
        .wishlist-btn-sm {
            width: 38px;
            height: 38px;
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
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
        }
        
        .pagination a,
        .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            background: var(--gray);
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .pagination a:hover,
        .pagination .active {
            background: var(--primary);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--gray);
            border-radius: 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .shop-filters {
                flex-direction: column;
            }
            
            .search-form {
                max-width: 100%;
                width: 100%;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .product-image {
                height: 180px;
            }
            
            .product-actions {
                flex-direction: column;
            }
            
            .wishlist-btn-sm {
                width: 100%;
            }
            
            .categories-filter {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .products-grid {
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
    <div class="shop-page">
        <div class="container">
            <div class="shop-header">
                <h1><i class="fas fa-store"></i> Notre boutique</h1>
                <p>Découvrez notre collection d'équipements pour l'exploration martienne</p>
            </div>
            
            <div class="shop-filters">
                <form method="GET" class="search-form" id="searchForm">
                    <input type="text" name="search" placeholder="Rechercher un produit..." value="<?php echo clean($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                    <?php if($category): ?>
                    <input type="hidden" name="category" value="<?php echo clean($category); ?>">
                    <?php endif; ?>
                </form>
                
                <select id="sort-select" class="sort-select">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Prix croissant</option>
                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Prix décroissant</option>
                    <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Nom A-Z</option>
                    <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Nom Z-A</option>
                </select>
            </div>
            
            <div class="categories-filter">
                <a href="shop.php" class="<?php echo !$category ? 'active' : ''; ?>">Tous les produits</a>
                <?php foreach($categories as $cat): ?>
                <a href="?category=<?php echo urlencode($cat['category']); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
                   class="<?php echo $category == $cat['category'] ? 'active' : ''; ?>">
                    <?php echo clean($cat['category']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            
            <?php if(count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach($products as $product): ?>
                <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                    <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                    <div class="product-badge">-<?php echo round((1 - $product['price']/$product['compare_price']) * 100); ?>%</div>
                    <?php endif; ?>
                    
                    <div class="product-image">
                        <?php if(!empty($product['primary_image']) && file_exists('uploads/products/' . $product['primary_image'])): ?>
                            <img src="uploads/products/<?php echo $product['primary_image']; ?>" alt="<?php echo clean($product['name']); ?>" loading="lazy">
                        <?php elseif(!empty($product['image']) && file_exists('uploads/products/' . $product['image'])): ?>
                            <img src="uploads/products/<?php echo $product['image']; ?>" alt="<?php echo clean($product['name']); ?>" loading="lazy">
                        <?php else: ?>
                            <i class="fas fa-box-open"></i>
                        <?php endif; ?>
                        
                        <?php if($product['stock'] <= 0): ?>
                        <div class="stock-badge out-stock">Rupture</div>
                        <?php elseif($product['stock'] < 5): ?>
                        <div class="stock-badge low-stock">Plus que <?php echo $product['stock']; ?></div>
                        <?php else: ?>
                        <div class="stock-badge in-stock">En stock</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <h3><a href="product.php?id=<?php echo $product['id']; ?>"><?php echo clean($product['name']); ?></a></h3>
                        <div class="product-price">
                            <?php echo formatPrice($product['price']); ?>
                            <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                            <span class="old-price"><?php echo formatPrice($product['compare_price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-actions">
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn-secondary btn-sm">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <?php if($product['stock'] > 0): ?>
                            <button class="btn-primary btn-sm add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                                <i class="fas fa-shopping-bag"></i> Ajouter
                            </button>
                            <?php else: ?>
                            <button class="btn-secondary btn-sm" disabled style="opacity:0.5;">
                                <i class="fas fa-times"></i> Indisponible
                            </button>
                            <?php endif; ?>
                            <button class="wishlist-btn-sm <?php echo in_array($product['id'], $wishlist_ids) ? 'active' : ''; ?>" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    title="<?php echo in_array($product['id'], $wishlist_ids) ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>">
                                <i class="<?php echo in_array($product['id'], $wishlist_ids) ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if($start > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                <?php if($start > 2): ?>
                <span>...</span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for($i = $start; $i <= $end; $i++): ?>
                    <?php if($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if($end < $total_pages): ?>
                <?php if($end < $total_pages - 1): ?>
                <span>...</span>
                <?php endif; ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                
                <?php if($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Aucun produit trouvé</h3>
                <p>Essayez de modifier vos critères de recherche</p>
                <a href="shop.php" class="btn-primary">Voir tous les produits</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// ============================================
// SHOP.JS - COMPLET
// ============================================

// Tri
const sortSelect = document.getElementById('sort-select');
if (sortSelect) {
    sortSelect.addEventListener('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', this.value);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    });
}

// Fonction d'ajout au panier
async function handleAddToCart(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const button = e.currentTarget;
    const productId = button.dataset.productId;
    
    if (!productId) {
        showNotification('Erreur: ID produit manquant', 'error');
        return;
    }
    
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

// Fonction wishlist
async function handleWishlist(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const button = e.currentTarget;
    const productId = button.dataset.productId;
    const isActive = button.classList.contains('active');
    
    if (!productId) return;
    
    const originalHTML = button.innerHTML;
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
                button.title = 'Retirer des favoris';
                showNotification('Ajouté à votre wishlist', 'success');
            } else {
                button.classList.remove('active');
                button.innerHTML = '<i class="far fa-heart"></i>';
                button.title = 'Ajouter aux favoris';
                showNotification('Retiré de votre wishlist', 'info');
            }
            
            // Mettre à jour le badge
            const wishlistBadge = document.querySelector('.nav-wishlist .badge');
            if (wishlistBadge && result.wishlist_count > 0) {
                wishlistBadge.textContent = result.wishlist_count;
                wishlistBadge.style.display = 'inline-flex';
            } else if (wishlistBadge && result.wishlist_count === 0) {
                wishlistBadge.style.display = 'none';
            }
        } else {
            button.innerHTML = originalHTML;
            showNotification(result.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        button.innerHTML = originalHTML;
        showNotification('Erreur de connexion', 'error');
    } finally {
        button.disabled = false;
    }
}

// Notification toast
function showNotification(message, type = 'success') {
    const existing = document.querySelector('.cart-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'cart-notification';
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
    };
    
    notification.innerHTML = `
        <i class="fas ${icons[type] || icons.success}"></i>
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
        font-family: inherit;
    `;
    
    document.body.appendChild(notification);
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = 'background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:1.2rem;margin-left:8px';
    closeBtn.onclick = () => notification.remove();
    
    setTimeout(() => {
        if (notification && notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 3000);
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Boutons d'ajout au panier
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.removeEventListener('click', handleAddToCart);
        btn.addEventListener('click', handleAddToCart);
    });
    
    // Boutons wishlist
    document.querySelectorAll('.wishlist-btn-sm').forEach(btn => {
        btn.removeEventListener('click', handleWishlist);
        btn.addEventListener('click', handleWishlist);
    });
});

// Animation keyframes
if (!document.querySelector('#shop-styles')) {
    const style = document.createElement('style');
    style.id = 'shop-styles';
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}
</script>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>