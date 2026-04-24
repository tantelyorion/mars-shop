// assets/js/main.js - Version complète et corrigée
// Mars Shop - JavaScript moderne pour e-commerce

// ============================================
// INITIALISATION PRINCIPALE
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des composants
    initAddToCartButtons();
    initWishlistButtons();
    initCartQuantityUpdate();
    initRemoveFromCart();
    initMobileMenu();
    initBackToTop();
    initFlashMessages();
    initQuantityHandlers();
    initProductGallery();
    initRatingStars();
    initCheckoutForm();
    initNewsletterForm();
    initPriceFormatting();
    initSearchForm();
    
    console.log('Mars Shop JS initialisé');
});

// ============================================
// AJOUT AU PANIER (AJAX)
// ============================================

function initAddToCartButtons() {
    const buttons = document.querySelectorAll('.add-to-cart, .btn-add-to-cart');
    
    buttons.forEach(button => {
        button.removeEventListener('click', handleAddToCart);
        button.addEventListener('click', handleAddToCart);
    });
}

async function handleAddToCart(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const button = e.currentTarget;
    const productId = button.dataset.productId;
    
    if (!productId) {
        console.error('Product ID manquant');
        return;
    }
    
    // Sauvegarder le contenu original
    const originalHTML = button.innerHTML;
    const originalText = button.textContent;
    
    // Afficher le chargement
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
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
            // Succès
            button.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
            updateCartBadge(result.cart_count);
            showNotification('Produit ajouté au panier', 'success');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 1500);
        } else {
            // Erreur
            button.innerHTML = originalHTML;
            button.disabled = false;
            showNotification(result.message || 'Erreur lors de l\'ajout', 'error');
        }
    } catch (error) {
        console.error('Erreur AJAX:', error);
        button.innerHTML = originalHTML;
        button.disabled = false;
        showNotification('Erreur de connexion', 'error');
    }
}

function updateCartBadge(count) {
    const cartBadge = document.querySelector('.nav-cart .badge, .cart-badge');
    
    if (cartBadge) {
        if (count > 0) {
            cartBadge.textContent = count;
            cartBadge.style.display = 'inline-flex';
        } else {
            cartBadge.style.display = 'none';
        }
    }
    
    // Mettre à jour le compteur dans la session (pour affichage)
    const cartLink = document.querySelector('.nav-cart');
    if (cartLink && count > 0) {
        if (!cartBadge) {
            const newBadge = document.createElement('span');
            newBadge.className = 'badge';
            newBadge.textContent = count;
            cartLink.appendChild(newBadge);
        }
    }
}

// ============================================
// WISHLIST (AJAX)
// ============================================

function initWishlistButtons() {
    const buttons = document.querySelectorAll('.wishlist-btn, .btn-wishlist');
    
    buttons.forEach(button => {
        button.removeEventListener('click', handleWishlist);
        button.addEventListener('click', handleWishlist);
    });
}

async function handleWishlist(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const button = e.currentTarget;
    const productId = button.dataset.productId;
    const isActive = button.classList.contains('active');
    
    if (!productId) {
        console.error('Product ID manquant');
        return;
    }
    
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
                button.innerHTML = '<i class="fas fa-heart"></i> Dans la wishlist';
                showNotification('Ajouté à votre wishlist', 'success');
            } else {
                button.classList.remove('active');
                button.innerHTML = '<i class="far fa-heart"></i> Ajouter à la wishlist';
                showNotification('Retiré de votre wishlist', 'info');
            }
            
            // Mettre à jour le badge
            updateWishlistBadge(result.wishlist_count);
            
            setTimeout(() => {
                button.disabled = false;
            }, 500);
        } else {
            button.innerHTML = originalHTML;
            button.disabled = false;
            showNotification(result.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur AJAX:', error);
        button.innerHTML = originalHTML;
        button.disabled = false;
        showNotification('Erreur de connexion', 'error');
    }
}

function updateWishlistBadge(count) {
    const wishlistBadge = document.querySelector('.nav-wishlist .badge, .wishlist-badge');
    
    if (wishlistBadge) {
        if (count > 0) {
            wishlistBadge.textContent = count;
            wishlistBadge.style.display = 'inline-flex';
        } else {
            wishlistBadge.style.display = 'none';
        }
    }
}

// ============================================
// GESTION DU PANIER (Quantités, Suppression)
// ============================================

function initCartQuantityUpdate() {
    const quantityInputs = document.querySelectorAll('.cart-quantity input, .item-quantity');
    
    quantityInputs.forEach(input => {
        input.removeEventListener('change', handleQuantityChange);
        input.addEventListener('change', handleQuantityChange);
    });
}

function handleQuantityChange(e) {
    const input = e.target;
    const max = parseInt(input.getAttribute('max')) || Infinity;
    const min = parseInt(input.getAttribute('min')) || 1;
    let value = parseInt(input.value);
    
    if (isNaN(value)) value = min;
    if (value > max) value = max;
    if (value < min) value = min;
    
    input.value = value;
    
    // Mettre à jour le total de la ligne
    const row = input.closest('.cart-item');
    if (row) {
        const priceText = row.querySelector('.cart-price')?.textContent || row.querySelector('.item-price')?.textContent;
        if (priceText) {
            const price = parseFloat(priceText.replace('€', '').replace(',', '.').trim());
            const total = price * value;
            const totalElement = row.querySelector('.cart-item-total, .item-total');
            if (totalElement) {
                totalElement.textContent = formatPriceNumber(total);
            }
        }
    }
    
    // Recalculer le total général
    updateCartTotal();
}

function initRemoveFromCart() {
    const removeButtons = document.querySelectorAll('.cart-remove, .btn-remove');
    
    removeButtons.forEach(button => {
        button.removeEventListener('click', handleRemoveFromCart);
        button.addEventListener('click', handleRemoveFromCart);
    });
}

function handleRemoveFromCart(e) {
    e.preventDefault();
    
    if (!confirm('Supprimer cet article du panier ?')) {
        return;
    }
    
    const button = e.currentTarget;
    const cartItem = button.closest('.cart-item');
    
    if (cartItem) {
        cartItem.style.opacity = '0.5';
        
        // Si c'est un formulaire, on le soumet
        const form = button.closest('form');
        if (form && button.name === 'remove_item') {
            form.submit();
        } else {
            // Sinon, suppression visuelle
            setTimeout(() => {
                cartItem.remove();
                updateCartTotal();
                showNotification('Article supprimé', 'info');
                
                // Vérifier si le panier est vide
                if (document.querySelectorAll('.cart-item').length === 0) {
                    location.reload();
                }
            }, 300);
        }
    }
}

function updateCartTotal() {
    let grandTotal = 0;
    
    document.querySelectorAll('.cart-item-total, .item-total').forEach(el => {
        const val = parseFloat(el.textContent.replace('€', '').replace(',', '.').trim());
        if (!isNaN(val)) grandTotal += val;
    });
    
    const totalElement = document.querySelector('.cart-total-amount, .cart-total, .total-amount');
    if (totalElement) {
        totalElement.textContent = formatPriceNumber(grandTotal);
    }
}

// ============================================
// QUANTITY HANDLERS (Produit + / -)
// ============================================

function initQuantityHandlers() {
    const quantityWrappers = document.querySelectorAll('.quantity-input');
    
    quantityWrappers.forEach(wrapper => {
        const input = wrapper.querySelector('input[type="number"]');
        const minusBtn = wrapper.querySelector('.quantity-minus');
        const plusBtn = wrapper.querySelector('.quantity-plus');
        
        if (minusBtn) {
            minusBtn.removeEventListener('click', () => {});
            minusBtn.addEventListener('click', () => {
                let value = parseInt(input.value);
                const min = parseInt(input.min) || 1;
                if (value > min) {
                    input.value = value - 1;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
        
        if (plusBtn) {
            plusBtn.removeEventListener('click', () => {});
            plusBtn.addEventListener('click', () => {
                let value = parseInt(input.value);
                const max = parseInt(input.max) || Infinity;
                if (value < max) {
                    input.value = value + 1;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
        
        input.removeEventListener('change', function() {});
        input.addEventListener('change', function() {
            let value = parseInt(this.value);
            const min = parseInt(this.min) || 1;
            const max = parseInt(this.max) || Infinity;
            
            if (isNaN(value)) value = min;
            if (value < min) value = min;
            if (value > max) value = max;
            
            this.value = value;
        });
    });
}

// ============================================
// MOBILE MENU
// ============================================

function initMobileMenu() {
    const toggle = document.getElementById('mobileToggle');
    const menu = document.getElementById('mobileMenu');
    const closeBtn = document.querySelector('.mobile-close');
    const overlay = document.querySelector('.mobile-overlay');
    
    if (!toggle || !menu) return;
    
    // Ouvrir le menu
    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.add('active');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
    
    // Fermer avec le bouton close
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMobileMenu);
    }
    
    // Fermer avec l'overlay
    if (overlay) {
        overlay.addEventListener('click', closeMobileMenu);
    }
    
    // Fermer avec la touche Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && menu.classList.contains('active')) {
            closeMobileMenu();
        }
    });
    
    function closeMobileMenu() {
        menu.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ============================================
// BACK TO TOP
// ============================================

function initBackToTop() {
    const button = document.getElementById('backToTop');
    
    if (!button) return;
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            button.style.display = 'flex';
        } else {
            button.style.display = 'none';
        }
    });
    
    button.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// ============================================
// FLASH MESSAGES
// ============================================

function initFlashMessages() {
    const flashMessages = document.querySelectorAll('.flash-message');
    
    flashMessages.forEach(flash => {
        const closeBtn = flash.querySelector('.flash-close');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                flash.remove();
            });
        }
        
        // Auto fermeture après 5 secondes
        setTimeout(() => {
            if (flash) {
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 300);
            }
        }, 5000);
    });
}

// ============================================
// NOTIFICATIONS TOAST
// ============================================

function showNotification(message, type = 'success') {
    // Supprimer les anciennes notifications
    const oldNotification = document.querySelector('.cart-notification');
    if (oldNotification) oldNotification.remove();
    
    const notification = document.createElement('div');
    notification.className = `cart-notification cart-notification-${type}`;
    
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
        background: var(--gray, #1a1a24);
        border-left: 4px solid ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideInRight 0.3s ease;
        font-size: 0.9rem;
    `;
    
    document.body.appendChild(notification);
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        color: rgba(255,255,255,0.5);
        cursor: pointer;
        font-size: 1.2rem;
        margin-left: 8px;
    `;
    
    closeBtn.addEventListener('click', () => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    });
    
    setTimeout(() => {
        if (notification) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 4000);
}

// ============================================
// PRODUCT GALLERY
// ============================================

function initProductGallery() {
    const thumbnails = document.querySelectorAll('.product-thumb');
    const mainImage = document.querySelector('.product-main-image');
    
    if (!thumbnails.length || !mainImage) return;
    
    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const imageUrl = this.dataset.image;
            const icon = this.dataset.icon;
            
            // Mettre à jour l'image principale
            if (imageUrl && mainImage) {
                mainImage.style.backgroundImage = `url(${imageUrl})`;
                mainImage.style.backgroundSize = 'cover';
                mainImage.style.backgroundPosition = 'center';
            } else if (icon && mainImage) {
                mainImage.innerHTML = `<i class="${icon}"></i>`;
            }
            
            // Marquer comme actif
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

// ============================================
// RATING STARS
// ============================================

function initRatingStars() {
    const ratingInputs = document.querySelectorAll('.rating-select input, .rating-input input');
    
    ratingInputs.forEach(input => {
        input.addEventListener('change', function() {
            const container = this.closest('.rating-select, .rating-input');
            const stars = container.querySelectorAll('i');
            const value = parseInt(this.value);
            
            stars.forEach((star, index) => {
                if (index < value) {
                    star.style.color = '#f59e0b';
                } else {
                    star.style.color = '#2a2a35';
                }
            });
        });
    });
}

// ============================================
// CHECKOUT FORM VALIDATION
// ============================================

function initCheckoutForm() {
    const form = document.getElementById('checkoutForm');
    
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ef4444';
                
                // Ajouter message d'erreur
                let error = field.parentElement.querySelector('.field-error');
                if (!error) {
                    error = document.createElement('small');
                    error.className = 'field-error';
                    error.style.cssText = 'color: #ef4444; font-size: 0.75rem; margin-top: 4px; display: block;';
                    field.parentElement.appendChild(error);
                }
                error.textContent = 'Ce champ est requis';
            } else {
                field.style.borderColor = '';
                const error = field.parentElement.querySelector('.field-error');
                if (error) error.remove();
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Veuillez remplir tous les champs obligatoires', 'error');
        }
    });
    
    // Nettoyer les erreurs au focus
    form.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('focus', function() {
            this.style.borderColor = '';
            const error = this.parentElement.querySelector('.field-error');
            if (error) error.remove();
        });
    });
}

// ============================================
// NEWSLETTER FORM
// ============================================

function initNewsletterForm() {
    const form = document.querySelector('.newsletter-form');
    
    if (!form) return;
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const emailInput = this.querySelector('input[type="email"]');
        const email = emailInput.value.trim();
        
        if (!email) {
            showNotification('Veuillez entrer votre email', 'error');
            return;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showNotification('Email invalide', 'error');
            return;
        }
        
        const button = this.querySelector('button');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        try {
            await new Promise(resolve => setTimeout(resolve, 800));
            showNotification('Inscription réussie à la newsletter !', 'success');
            emailInput.value = '';
        } catch (error) {
            showNotification('Erreur, veuillez réessayer', 'error');
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

// ============================================
// PRICE FORMATTING
// ============================================

function initPriceFormatting() {
    const priceElements = document.querySelectorAll('.price, .product-price, .item-price, .cart-price');
    
    priceElements.forEach(element => {
        const text = element.textContent;
        const match = text.match(/[\d,\.]+/);
        if (match) {
            const value = parseFloat(match[0].replace(',', '.'));
            if (!isNaN(value)) {
                element.textContent = formatPriceNumber(value);
            }
        }
    });
}

function formatPriceNumber(price) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(price);
}

// ============================================
// SEARCH FORM DEBOUNCE
// ============================================

let searchTimeout;

function initSearchForm() {
    const searchInputs = document.querySelectorAll('input[name="search"], .search-input');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            
            searchTimeout = setTimeout(() => {
                const form = this.closest('form');
                if (form && this.value.length >= 2 || this.value.length === 0) {
                    form.submit();
                }
            }, 500);
        });
    });
}

// ============================================
// STYLES DYNAMIQUES POUR ANIMATIONS
// ============================================

// Ajouter les styles d'animation
const animationStyles = document.createElement('style');
animationStyles.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
    
    .fa-spin {
        animation: spin 1s linear infinite;
    }
    
    .cart-notification {
        font-family: inherit;
    }
    
    .notification-close:hover {
        color: white;
    }
    
    /* Transition pour les product cards */
    .product-card {
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    
    /* Loading spinner pour AJAX */
    .btn-loading {
        pointer-events: none;
        opacity: 0.7;
    }
`;
document.head.appendChild(animationStyles);

// ============================================
// UTILITAIRES
// ============================================

/**
 * Fonction utilitaire pour attendre
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Fonction utilitaire pour débouncer
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Récupérer les paramètres URL
 */
function getUrlParams() {
    const params = {};
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    
    for (const [key, value] of urlParams) {
        params[key] = value;
    }
    
    return params;
}

/**
 * Mettre à jour l'URL sans rechargement
 */
function updateUrlParams(params) {
    const url = new URL(window.location.href);
    
    for (const [key, value] of Object.entries(params)) {
        if (value) {
            url.searchParams.set(key, value);
        } else {
            url.searchParams.delete(key);
        }
    }
    
    window.history.pushState({}, '', url);
}

// ============================================
// EXPOSER LES FONCTIONS GLOBALEMENT (DEBUG)
// ============================================

if (typeof window !== 'undefined') {
    window.MarsShop = {
        showNotification,
        formatPrice: formatPriceNumber,
        updateCartBadge,
        updateWishlistBadge,
        sleep,
        debounce,
        getUrlParams,
        updateUrlParams
    };
}

console.log('Mars Shop JS - Version complète chargée');