<?php
// shop.php - Page boutique avec filtres et AJAX
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

// Construction de la requête
$sql = "SELECT * FROM products WHERE is_active = 1";
$count_sql = "SELECT COUNT(*) as total FROM products WHERE is_active = 1";
$params = [];

if ($category) {
    $sql .= " AND category = ?";
    $count_sql .= " AND category = ?";
    $params[] = $category;
}

if ($search) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $count_sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Tri
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY name DESC";
        break;
    default:
        $sql .= " ORDER BY created_at DESC";
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

<style>
/* Styles spécifiques à la page boutique */
.shop-page {
    padding: 20px 0;
}

.shop-header {
    text-align: center;
    margin-bottom: 30px;
}

.shop-header h1 {
    margin-bottom: 8px;
    font-size: 2rem;
}

.shop-header p {
    color: var(--text-muted);
}

.shop-filters {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-form {
    display: flex;
    flex: 1;
    max-width: 300px;
}

.search-form input {
    flex: 1;
    padding: 10px 12px;
    background: var(--gray);
    border: 1px solid var(--border);
    border-radius: 8px 0 0 8px;
    color: var(--text);
    font-size: 0.9rem;
}

.search-form input:focus {
    outline: none;
    border-color: var(--primary);
}

.search-form button {
    padding: 10px 16px;
    background: var(--primary);
    border: none;
    border-radius: 0 8px 8px 0;
    color: white;
    cursor: pointer;
    transition: background 0.2s;
}

.search-form button:hover {
    background: var(--primary-dark);
}

.sort-select {
    padding: 10px 12px;
    background: var(--gray);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    cursor: pointer;
    font-size: 0.9rem;
}

.categories-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 30px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.categories-filter a {
    padding: 6px 16px;
    background: var(--gray);
    border-radius: 20px;
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
    height: 180px;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.product-image i {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.3);
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
}

.stock-badge {
    position: absolute;
    bottom: 12px;
    left: 12px;
    background: rgba(0, 0, 0, 0.8);
    color: var(--error);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
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

.product-price .old-price {
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

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
}

.pagination a,
.pagination span {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 8px;
    background: var(--gray);
    border-radius: 6px;
    color: var(--text);
    text-decoration: none;
    transition: all 0.2s;
}

.pagination a:hover,
.pagination .active {
    background: var(--primary);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--gray);
    border-radius: 16px;
}

.empty-state i {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 16px;
}

.empty-state h3 {
    margin-bottom: 8px;
}

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

<div class="shop-page">
    <div class="container">
        <div class="shop-header">
            <h1>Nos produits</h1>
            <p><?php echo $total_products; ?> article(s)</p>
        </div>
        
        <div class="shop-filters">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Rechercher..." value="<?php echo clean($search); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
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
            <a href="shop.php" class="<?php echo !$category ? 'active' : ''; ?>">Tous</a>
            <?php foreach($categories as $cat): ?>
            <a href="?category=<?php echo urlencode($cat['category']); ?>" 
               class="<?php echo $category == $cat['category'] ? 'active' : ''; ?>">
                <?php echo clean($cat['category']); ?>
            </a>
            <?php endforeach; ?>
        </div>
        
        <?php if(count($products) > 0): ?>
        <div class="products-grid">
            <?php foreach($products as $product): ?>
            <div class="product-card">
                <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                <div class="product-badge">-<?php echo round((1 - $product['price']/$product['compare_price']) * 100); ?>%</div>
                <?php endif; ?>
                <div class="product-image">
                    <i class="fas fa-box-open"></i>
                    <?php if($product['stock'] <= 0): ?>
                    <div class="stock-badge">Rupture</div>
                    <?php elseif($product['stock'] < 5): ?>
                    <div class="stock-badge" style="background: var(--warning); color: #000;">Plus que <?php echo $product['stock']; ?></div>
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
                        <a href="product.php?id=<?php echo $product['id']; ?>" class="btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> Voir
                        </a>
                        <?php if($product['stock'] > 0): ?>
                        <button class="btn-primary btn-sm add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                            <i class="fas fa-shopping-bag"></i> Ajouter
                        </button>
                        <?php else: ?>
                        <button class="btn-secondary btn-sm" disabled style="opacity: 0.5;">
                            <i class="fas fa-times"></i> Indisponible
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
        
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo;</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
                <?php elseif($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php elseif($i == $page - 3 || $i == $page + 3): ?>
                <span>...</span>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">&raquo;</a>
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

<script>
// Tri
document.getElementById('sort-select')?.addEventListener('change', function() {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', this.value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
});

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
                showNotification('Ajouté à votre wishlist', 'success');
            } else {
                button.classList.remove('active');
                button.innerHTML = '<i class="far fa-heart"></i>';
                showNotification('Retiré de votre wishlist', 'info');
            }
            
            // Mettre à jour le badge
            const wishlistBadge = document.querySelector('.nav-wishlist .badge');
            if (wishlistBadge && result.wishlist_count > 0) {
                wishlistBadge.textContent = result.wishlist_count;
                wishlistBadge.style.display = 'inline-flex';
            } else if (wishlistBadge) {
                wishlistBadge.style.display = 'none';
            }
        } else {
            button.innerHTML = originalIcon;
            showNotification(result.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        button.innerHTML = originalIcon;
        showNotification('Erreur de connexion', 'error');
    } finally {
        button.disabled = false;
    }
}

// Notification
function showNotification(message, type = 'success') {
    const existing = document.querySelector('.cart-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'cart-notification';
    
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
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);
</script>

<?php require_once 'includes/footer.php'; ?>