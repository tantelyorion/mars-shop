<?php
// cart.php - Panier d'achat avec miniatures des produits (CORRIGÉ)
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$conn = getConnection();

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Suppression d'un article (doit être traité AVANT la mise à jour)
    if (isset($_POST['remove_item']) && isset($_POST['cart_id'])) {
        $cart_id = $_POST['cart_id'];
        
        if (isLoggedIn()) {
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, $_SESSION['user_id']]);
        } else {
            $product_id = str_replace('guest_', '', $cart_id);
            removeFromCart($product_id, true);
        }
        
        setFlashMessage('success', 'Article supprimé');
        header('Location: cart.php');
        exit();
    }
    
    // Vider le panier
    if (isset($_POST['clear_cart'])) {
        clearCart();
        setFlashMessage('info', 'Panier vidé');
        header('Location: cart.php');
        exit();
    }
    
    // Mise à jour des quantités (à traiter après suppression)
    if (isset($_POST['update_cart']) && isset($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $cart_id => $quantity) {
            $quantity = max(0, (int)$quantity);
            
            if (isLoggedIn()) {
                if ($quantity <= 0) {
                    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                    $stmt->execute([$cart_id, $_SESSION['user_id']]);
                } else {
                    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$quantity, $cart_id, $_SESSION['user_id']]);
                }
            } else {
                $product_id = str_replace('guest_', '', $cart_id);
                updateCartQuantity($product_id, $quantity, true);
            }
        }
        setFlashMessage('success', 'Panier mis à jour');
        header('Location: cart.php');
        exit();
    }
}

// Récupérer les articles du panier avec l'image principale
$cart_items = [];
if (isLoggedIn()) {
    $stmt = $conn->prepare("
        SELECT c.id as cart_id, c.quantity, p.*,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();
} else {
    if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
        $ids = array_column($_SESSION['guest_cart'], 'product_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("
            SELECT p.*,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p WHERE p.id IN ($placeholders)
        ");
        $stmt->execute($ids);
        $products = $stmt->fetchAll();
        
        foreach ($products as $product) {
            foreach ($_SESSION['guest_cart'] as $item) {
                if ($item['product_id'] == $product['id']) {
                    $product['cart_id'] = 'guest_' . $product['id'];
                    $product['quantity'] = $item['quantity'];
                    $cart_items[] = $product;
                    break;
                }
            }
        }
    }
}

// Calcul des totaux
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$total = $subtotal;
?>

<div class="cart-container">
    <div class="cart-header-section">
        <h2><i class="fas fa-shopping-bag"></i> Mon panier</h2>
        <?php if(count($cart_items) > 0): ?>
        <p class="cart-count"><?php echo count($cart_items); ?> article(s)</p>
        <?php endif; ?>
    </div>
    
    <?php if(count($cart_items) > 0): ?>
    <form method="POST" id="cartForm">
        <!-- Version desktop -->
        <div class="cart-desktop">
            <div class="cart-header">
                <div>Produit</div>
                <div>Prix</div>
                <div>Quantité</div>
                <div>Total</div>
                <div></div>
            </div>
            
            <?php foreach($cart_items as $item): ?>
            <div class="cart-item" data-cart-id="<?php echo $item['cart_id']; ?>">
                <div class="cart-product">
                    <div class="cart-product-image">
                        <?php 
                        $image_path = null;
                        if(!empty($item['primary_image']) && file_exists('uploads/products/' . $item['primary_image'])) {
                            $image_path = 'uploads/products/' . $item['primary_image'];
                        } elseif(!empty($item['image']) && file_exists('uploads/products/' . $item['image'])) {
                            $image_path = 'uploads/products/' . $item['image'];
                        }
                        ?>
                        <?php if($image_path): ?>
                            <img src="<?php echo $image_path; ?>" alt="<?php echo clean($item['name']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">
                        <?php else: ?>
                            <i class="fas fa-box"></i>
                        <?php endif; ?>
                    </div>
                    <div class="cart-product-info">
                        <strong><?php echo clean($item['name']); ?></strong>
                        <div class="product-category"><?php echo clean($item['category']); ?></div>
                    </div>
                </div>
                <div class="cart-price" data-price="<?php echo $item['price']; ?>">
                    <?php echo formatPrice($item['price']); ?>
                </div>
                <div class="cart-quantity">
                    <div class="quantity-control">
                        <button type="button" class="qty-btn qty-minus" data-cart-id="<?php echo $item['cart_id']; ?>">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" name="quantities[<?php echo $item['cart_id']; ?>]" 
                               value="<?php echo $item['quantity']; ?>" 
                               min="1" max="<?php echo $item['stock']; ?>"
                               class="cart-qty" 
                               data-cart-id="<?php echo $item['cart_id']; ?>"
                               data-price="<?php echo $item['price']; ?>">
                        <button type="button" class="qty-btn qty-plus" data-cart-id="<?php echo $item['cart_id']; ?>">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="cart-item-total" data-total="<?php echo $item['price'] * $item['quantity']; ?>">
                    <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                </div>
                <div class="cart-action">
                    <button type="button" class="cart-remove" data-cart-id="<?php echo $item['cart_id']; ?>" data-product-id="<?php echo $item['id']; ?>">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Version mobile -->
        <div class="cart-mobile">
            <?php foreach($cart_items as $item): ?>
            <div class="cart-card" data-cart-id="<?php echo $item['cart_id']; ?>">
                <div class="cart-card-header">
                    <div class="cart-product-image">
                        <?php 
                        $image_path = null;
                        if(!empty($item['primary_image']) && file_exists('uploads/products/' . $item['primary_image'])) {
                            $image_path = 'uploads/products/' . $item['primary_image'];
                        } elseif(!empty($item['image']) && file_exists('uploads/products/' . $item['image'])) {
                            $image_path = 'uploads/products/' . $item['image'];
                        }
                        ?>
                        <?php if($image_path): ?>
                            <img src="<?php echo $image_path; ?>" alt="<?php echo clean($item['name']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">
                        <?php else: ?>
                            <i class="fas fa-box"></i>
                        <?php endif; ?>
                    </div>
                    <div class="cart-product-info">
                        <strong><?php echo clean($item['name']); ?></strong>
                        <div class="product-category"><?php echo clean($item['category']); ?></div>
                    </div>
                    <button type="button" class="cart-remove-mobile" data-cart-id="<?php echo $item['cart_id']; ?>" data-product-id="<?php echo $item['id']; ?>">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="cart-card-body">
                    <div class="cart-price-row">
                        <span>Prix unitaire :</span>
                        <strong><?php echo formatPrice($item['price']); ?></strong>
                    </div>
                    <div class="cart-quantity-row">
                        <span>Quantité :</span>
                        <div class="quantity-control">
                            <button type="button" class="qty-btn qty-minus" data-cart-id="<?php echo $item['cart_id']; ?>">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" name="quantities[<?php echo $item['cart_id']; ?>]" 
                                   value="<?php echo $item['quantity']; ?>" 
                                   min="1" max="<?php echo $item['stock']; ?>"
                                   class="cart-qty-mobile" 
                                   data-cart-id="<?php echo $item['cart_id']; ?>"
                                   data-price="<?php echo $item['price']; ?>">
                            <button type="button" class="qty-btn qty-plus" data-cart-id="<?php echo $item['cart_id']; ?>">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="cart-total-row">
                        <span>Total :</span>
                        <strong class="cart-item-total-mobile"><?php echo formatPrice($item['price'] * $item['quantity']); ?></strong>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Résumé -->
        <div class="cart-summary">
            <div class="cart-summary-inner">
                <div class="cart-summary-row">
                    <span>Sous-total</span>
                    <span class="cart-subtotal"><?php echo formatPrice($subtotal); ?></span>
                </div>
                <div class="cart-summary-row">
                    <span>Livraison</span>
                    <span>Offerte</span>
                </div>
                <div class="cart-summary-divider"></div>
                <div class="cart-summary-row total">
                    <span>Total</span>
                    <strong class="cart-grand-total"><?php echo formatPrice($total); ?></strong>
                </div>
            </div>
            
            <div class="cart-buttons">
                <button type="submit" name="update_cart" class="btn-secondary btn-update">
                    <i class="fas fa-sync-alt"></i> Mettre à jour
                </button>
                <button type="submit" name="clear_cart" class="btn-secondary btn-clear" 
                        onclick="return confirm('Vider tout le panier ?')">
                    <i class="fas fa-trash-alt"></i> Vider
                </button>
                <a href="checkout.php" class="btn-primary btn-checkout">
                    <i class="fas fa-credit-card"></i> Valider la commande
                </a>
            </div>
        </div>
    </form>
    <?php else: ?>
    <div class="empty-cart">
        <div class="empty-cart-icon">
            <i class="fas fa-shopping-bag"></i>
        </div>
        <h3>Votre panier est vide</h3>
        <p>Découvrez nos produits et ajoutez-les à votre panier</p>
        <a href="shop.php" class="btn-primary">
            Découvrir la boutique
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
.cart-container {
    background: var(--gray);
    border-radius: 20px;
    padding: 32px;
    max-width: 1200px;
    margin: 0 auto;
}

.cart-header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.cart-header-section h2 {
    margin-bottom: 0;
    font-size: 1.5rem;
}

.cart-count {
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* Version desktop */
.cart-desktop {
    overflow-x: auto;
}

.cart-header {
    display: grid;
    grid-template-columns: 3fr 1fr 1.5fr 1fr 0.5fr;
    gap: 16px;
    padding: 16px 12px;
    font-weight: 600;
    border-bottom: 2px solid var(--border);
    color: var(--text-muted);
    font-size: 0.85rem;
}

.cart-item {
    display: grid;
    grid-template-columns: 3fr 1fr 1.5fr 1fr 0.5fr;
    gap: 16px;
    align-items: center;
    padding: 20px 12px;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.cart-item:hover {
    background: rgba(255, 255, 255, 0.03);
}

.cart-product {
    display: flex;
    align-items: center;
    gap: 16px;
}

.cart-product-image {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.cart-product-image i {
    font-size: 1.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.cart-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-product-info strong {
    display: block;
    margin-bottom: 4px;
    font-size: 1rem;
}

.product-category {
    font-size: 0.7rem;
    color: var(--text-muted);
}

.cart-price {
    font-weight: 500;
    color: var(--primary-light);
}

/* Contrôles de quantité */
.quantity-control {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--gray-light);
    border-radius: 40px;
    padding: 4px;
    width: fit-content;
}

.qty-btn {
    width: 32px;
    height: 32px;
    background: var(--gray);
    border: 1px solid var(--border);
    border-radius: 50%;
    color: var(--text);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qty-btn:hover {
    background: var(--primary);
    border-color: var(--primary);
    transform: scale(1.05);
}

.qty-btn:active {
    transform: scale(0.95);
}

.cart-qty, .cart-qty-mobile {
    width: 50px;
    height: 36px;
    text-align: center;
    background: transparent;
    border: none;
    color: var(--text);
    font-size: 1rem;
    font-weight: 500;
}

.cart-qty:focus, .cart-qty-mobile:focus {
    outline: none;
}

/* Supprimer les flèches des inputs number */
.cart-qty::-webkit-inner-spin-button,
.cart-qty::-webkit-outer-spin-button,
.cart-qty-mobile::-webkit-inner-spin-button,
.cart-qty-mobile::-webkit-outer-spin-button {
    opacity: 0;
    width: 0;
}

.cart-item-total {
    font-weight: 600;
    color: var(--primary-light);
    font-size: 1.1rem;
}

.cart-remove, .cart-remove-mobile {
    background: rgba(239, 68, 68, 0.1);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    color: var(--error);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cart-remove:hover, .cart-remove-mobile:hover {
    background: var(--error);
    color: white;
    transform: scale(1.05);
}

/* Version mobile */
.cart-mobile {
    display: none;
}

.cart-card {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 16px;
}

.cart-card-header {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.cart-card-header .cart-product-info {
    flex: 1;
}

.cart-card-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cart-price-row, .cart-quantity-row, .cart-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-price-row span, .cart-quantity-row span, .cart-total-row span {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.cart-total-row strong {
    color: var(--primary-light);
    font-size: 1.1rem;
}

/* Résumé */
.cart-summary {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 2px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 24px;
}

.cart-summary-inner {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 16px;
    padding: 20px;
    min-width: 280px;
}

.cart-summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}

.cart-summary-row.total {
    margin-top: 12px;
    font-size: 1.2rem;
}

.cart-summary-row.total strong {
    color: var(--primary-light);
    font-size: 1.3rem;
}

.cart-summary-divider {
    height: 1px;
    background: var(--border);
    margin: 16px 0;
}

.cart-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.btn-update, .btn-clear, .btn-checkout {
    padding: 12px 24px;
    font-size: 0.9rem;
}

/* Panier vide */
.empty-cart {
    text-align: center;
    padding: 60px 20px;
}

.empty-cart-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 24px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-cart-icon i {
    font-size: 3rem;
    color: var(--text-muted);
}

.empty-cart h3 {
    margin-bottom: 8px;
}

.empty-cart p {
    color: var(--text-muted);
    margin-bottom: 24px;
}

/* Responsive */
@media (max-width: 768px) {
    .cart-container {
        padding: 20px;
    }
    
    .cart-desktop {
        display: none;
    }
    
    .cart-mobile {
        display: block;
    }
    
    .cart-summary {
        flex-direction: column;
        align-items: stretch;
    }
    
    .cart-summary-inner {
        width: 100%;
    }
    
    .cart-buttons {
        justify-content: stretch;
    }
    
    .btn-update, .btn-clear, .btn-checkout {
        flex: 1;
        text-align: center;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .cart-container {
        padding: 16px;
    }
    
    .cart-buttons {
        flex-direction: column;
    }
    
    .cart-header-section {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
// ============================================
// CART.JS - CORRIGÉ
// ============================================

// Gestion des boutons + et -
document.querySelectorAll('.qty-minus, .qty-plus').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const cartId = this.dataset.cartId;
        let input;
        
        // Trouver l'input correspondant
        input = document.querySelector(`.cart-qty[data-cart-id="${cartId}"]`);
        if (!input) {
            input = document.querySelector(`.cart-qty-mobile[data-cart-id="${cartId}"]`);
        }
        
        if (!input) return;
        
        let currentValue = parseInt(input.value);
        const maxValue = parseInt(input.max) || Infinity;
        const minValue = parseInt(input.min) || 1;
        
        if (this.classList.contains('qty-minus')) {
            if (currentValue > minValue) {
                input.value = currentValue - 1;
            }
        } else {
            if (currentValue < maxValue) {
                input.value = currentValue + 1;
            }
        }
        
        // Déclencher l'événement change
        input.dispatchEvent(new Event('change'));
    });
});

// Gestion de la suppression (AJAX)
document.querySelectorAll('.cart-remove, .cart-remove-mobile').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        
        const cartId = this.dataset.cartId;
        const productId = this.dataset.productId;
        
        if (!confirm('Supprimer cet article du panier ?')) {
            return;
        }
        
        const button = this;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        try {
            const formData = new FormData();
            formData.append('cart_id', cartId);
            formData.append('product_id', productId);
            formData.append('action', 'remove');
            
            const response = await fetch('ajax-remove-from-cart.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Supprimer la ligne du panier visuellement
                const row = document.querySelector(`.cart-item[data-cart-id="${cartId}"], .cart-card[data-cart-id="${cartId}"]`);
                if (row) {
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        updateCartTotals();
                        
                        // Vérifier si le panier est vide
                        if (document.querySelectorAll('.cart-item, .cart-card').length === 0) {
                            location.reload();
                        }
                    }, 300);
                }
                showNotification('Article supprimé', 'info');
                
                // Mettre à jour le badge du panier
                const cartBadge = document.querySelector('.nav-cart .badge');
                if (cartBadge && result.cart_count !== undefined) {
                    cartBadge.textContent = result.cart_count;
                    cartBadge.style.display = result.cart_count > 0 ? 'inline-flex' : 'none';
                }
            } else {
                button.innerHTML = originalHTML;
                button.disabled = false;
                showNotification(result.message || 'Erreur lors de la suppression', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            button.innerHTML = originalHTML;
            button.disabled = false;
            showNotification('Erreur de connexion', 'error');
        }
    });
});

// Mise à jour automatique du total lors du changement de quantité
function updateCartTotals() {
    let grandTotal = 0;
    
    // Pour chaque ligne de panier
    document.querySelectorAll('.cart-item, .cart-card').forEach(row => {
        let price, quantity;
        
        // Trouver le prix
        const priceElement = row.querySelector('.cart-price');
        if (priceElement) {
            price = parseFloat(priceElement.dataset.price || priceElement.textContent.replace('€', '').replace(',', '.').trim());
        } else {
            const priceRow = row.querySelector('.cart-price-row strong');
            if (priceRow) {
                price = parseFloat(priceRow.textContent.replace('€', '').replace(',', '.').trim());
            }
        }
        
        // Trouver la quantité
        const qtyInput = row.querySelector('.cart-qty') || row.querySelector('.cart-qty-mobile');
        if (qtyInput) {
            quantity = parseInt(qtyInput.value);
        }
        
        if (price && quantity) {
            const total = price * quantity;
            grandTotal += total;
            
            // Mettre à jour l'affichage du total
            const totalElementDesktop = row.querySelector('.cart-item-total');
            if (totalElementDesktop) {
                totalElementDesktop.textContent = new Intl.NumberFormat('fr-FR', {
                    style: 'currency',
                    currency: 'EUR'
                }).format(total);
                totalElementDesktop.dataset.total = total;
            }
            
            const totalElementMobile = row.querySelector('.cart-item-total-mobile');
            if (totalElementMobile) {
                totalElementMobile.textContent = new Intl.NumberFormat('fr-FR', {
                    style: 'currency',
                    currency: 'EUR'
                }).format(total);
            }
        }
    });
    
    // Mettre à jour le total général
    const grandTotalElement = document.querySelector('.cart-grand-total');
    if (grandTotalElement) {
        grandTotalElement.textContent = new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(grandTotal);
    }
    
    const subtotalElement = document.querySelector('.cart-subtotal');
    if (subtotalElement) {
        subtotalElement.textContent = new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(grandTotal);
    }
}

// Écouter les changements de quantité
document.querySelectorAll('.cart-qty, .cart-qty-mobile').forEach(input => {
    input.addEventListener('change', function() {
        let value = parseInt(this.value);
        const min = parseInt(this.min) || 1;
        const max = parseInt(this.max) || Infinity;
        
        if (isNaN(value)) value = min;
        if (value < min) value = min;
        if (value > max) value = max;
        
        this.value = value;
        updateCartTotals();
    });
});

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
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
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
    `;
    
    document.body.appendChild(notification);
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = 'background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:1.2rem;margin-left:8px';
    closeBtn.onclick = () => notification.remove();
    
    setTimeout(() => notification.remove(), 3000);
}

// Initialiser les totaux au chargement
document.addEventListener('DOMContentLoaded', function() {
    updateCartTotals();
});

// Animation keyframes
if (!document.querySelector('#cart-styles')) {
    const style = document.createElement('style');
    style.id = 'cart-styles';
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