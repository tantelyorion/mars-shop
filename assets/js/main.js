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
    initPriceFormatting();
    initSearchForm();
    initPaymentMethodToggle();
    initPapayFields();
    
    console.log('Mars Shop JS initialisé');
});

// ============================================
// AJOUT AU PANIER (AJAX)
// ============================================

function initAddToCartButtons() {
    const buttons = document.querySelectorAll('.add-to-cart-btn, .add-to-cart');
    
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
            button.innerHTML = '<i class="fas fa-check"></i>';
            updateCartBadge(result.cart_count);
            showNotification('Produit ajouté au panier', 'success');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 1500);
        } else {
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
    const cartBadge = document.querySelector('.nav-cart .badge');
    
    if (cartBadge) {
        if (count > 0) {
            cartBadge.textContent = count;
            cartBadge.style.display = 'inline-flex';
        } else {
            cartBadge.style.display = 'none';
        }
    } else if (count > 0) {
        const cartLink = document.querySelector('.nav-cart');
        if (cartLink) {
            const newBadge = document.createElement('span');
            newBadge.className = 'badge';
            newBadge.textContent = count;
            cartLink.appendChild(newBadge);
        }
    }
}

// ============================================
// PANIER - QUANTITÉS ET MISES À JOUR
// ============================================

function initCartQuantityUpdate() {
    const quantityInputs = document.querySelectorAll('.cart-qty, .cart-qty-mobile, .item-quantity');
    
    quantityInputs.forEach(input => {
        input.removeEventListener('change', handleQuantityChange);
        input.addEventListener('change', handleQuantityChange);
        
        // Supprimer les flèches par défaut des inputs number
        if (input.type === 'number') {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                }
            });
        }
    });
}

function handleQuantityChange(e) {
    const input = e.target;
    let value = parseInt(input.value);
    const min = parseInt(input.min) || 1;
    const max = parseInt(input.max) || Infinity;
    
    if (isNaN(value)) value = min;
    if (value < min) value = min;
    if (value > max) value = max;
    
    input.value = value;
    
    // Mettre à jour le total de la ligne
    const row = input.closest('.cart-item, .cart-card');
    if (row) {
        let priceElement = row.querySelector('.cart-price');
        if (!priceElement) {
            priceElement = row.querySelector('.cart-price-row strong');
        }
        
        if (priceElement) {
            const priceText = priceElement.textContent;
            const price = parseFloat(priceText.replace('€', '').replace(',', '.').trim());
            const total = price * value;
            
            const totalElementDesktop = row.querySelector('.cart-item-total');
            if (totalElementDesktop) {
                totalElementDesktop.textContent = formatPriceNumber(total);
            }
            
            const totalElementMobile = row.querySelector('.cart-item-total-mobile');
            if (totalElementMobile) {
                totalElementMobile.textContent = formatPriceNumber(total);
            }
        }
    }
    
    // Recalculer le total général
    updateCartTotal();
}

function updateCartTotal() {
    let grandTotal = 0;
    
    document.querySelectorAll('.cart-item-total, .cart-item-total-mobile').forEach(el => {
        const val = parseFloat(el.textContent.replace('€', '').replace(',', '.').trim());
        if (!isNaN(val)) grandTotal += val;
    });
    
    const totalElement = document.querySelector('.cart-grand-total, .cart-total, .cart-total-amount');
    if (totalElement) {
        totalElement.textContent = formatPriceNumber(grandTotal);
    }
    
    const subtotalElement = document.querySelector('.cart-subtotal');
    if (subtotalElement) {
        subtotalElement.textContent = formatPriceNumber(grandTotal);
    }
}

function initRemoveFromCart() {
    const removeButtons = document.querySelectorAll('.cart-remove, .cart-remove-mobile, .btn-remove');
    
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
    const form = button.closest('form');
    
    if (form && (button.name === 'remove_item' || button.classList.contains('cart-remove'))) {
        form.submit();
    } else {
        const cartItem = button.closest('.cart-item, .cart-card');
        if (cartItem) {
            cartItem.style.opacity = '0.5';
            setTimeout(() => {
                cartItem.remove();
                updateCartTotal();
                showNotification('Article supprimé', 'info');
                
                const remainingItems = document.querySelectorAll('.cart-item, .cart-card').length;
                if (remainingItems === 0) {
                    location.reload();
                }
            }, 300);
        }
    }
}

// ============================================
// WISHLIST (AJAX)
// ============================================

function initWishlistButtons() {
    const buttons = document.querySelectorAll('.wishlist-btn-sm, .wishlist-btn');
    
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
        showNotification('Erreur: ID produit manquant', 'error');
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
                button.innerHTML = '<i class="fas fa-heart"></i>';
                button.title = 'Retirer des favoris';
                showNotification('Ajouté à votre wishlist', 'success');
            } else {
                button.classList.remove('active');
                button.innerHTML = '<i class="far fa-heart"></i>';
                button.title = 'Ajouter aux favoris';
                showNotification('Retiré de votre wishlist', 'info');
            }
            
            updateWishlistBadge(result.wishlist_count);
        } else {
            button.innerHTML = originalHTML;
            showNotification(result.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur AJAX:', error);
        button.innerHTML = originalHTML;
        showNotification('Erreur de connexion', 'error');
    } finally {
        button.disabled = false;
    }
}

function updateWishlistBadge(count) {
    const wishlistBadge = document.querySelector('.nav-wishlist .badge');
    
    if (wishlistBadge) {
        if (count > 0) {
            wishlistBadge.textContent = count;
            wishlistBadge.style.display = 'inline-flex';
        } else {
            wishlistBadge.style.display = 'none';
        }
    } else if (count > 0) {
        const wishlistLink = document.querySelector('.nav-wishlist');
        if (wishlistLink) {
            const newBadge = document.createElement('span');
            newBadge.className = 'badge';
            newBadge.textContent = count;
            wishlistLink.appendChild(newBadge);
        }
    }
}

// ============================================
// QUANTITY HANDLERS (PRODUIT + / -)
// ============================================

function initQuantityHandlers() {
    const quantityWrappers = document.querySelectorAll('.quantity-input');
    
    quantityWrappers.forEach(wrapper => {
        const input = wrapper.querySelector('input[type="number"]');
        const minusBtn = wrapper.querySelector('.quantity-minus');
        const plusBtn = wrapper.querySelector('.quantity-plus');
        
        if (!input) return;
        
        const min = parseInt(input.min) || 1;
        const max = parseInt(input.max) || Infinity;
        
        if (minusBtn) {
            minusBtn.removeEventListener('click', handleMinusClick);
            minusBtn.addEventListener('click', function(e) {
                e.preventDefault();
                let value = parseInt(input.value);
                if (value > min) {
                    input.value = value - 1;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
        
        if (plusBtn) {
            plusBtn.removeEventListener('click', handlePlusClick);
            plusBtn.addEventListener('click', function(e) {
                e.preventDefault();
                let value = parseInt(input.value);
                if (value < max) {
                    input.value = value + 1;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
        
        input.removeEventListener('change', handleInputChange);
        input.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value)) value = min;
            if (value < min) value = min;
            if (value > max) value = max;
            this.value = value;
        });
    });
}

function handleMinusClick(e) {}
function handlePlusClick(e) {}
function handleInputChange(e) {}

// ============================================
// MOBILE MENU
// ============================================

function initMobileMenu() {
    const toggle = document.getElementById('mobileToggle');
    const menu = document.getElementById('mobileMenu');
    const closeBtn = document.querySelector('.mobile-close');
    
    if (!toggle || !menu) return;
    
    // Ouvrir le menu
    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
    
    // Fermer avec le bouton close
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMobileMenu);
    }
    
    // Fermer en cliquant à l'extérieur
    document.addEventListener('click', function(e) {
        if (menu.classList.contains('active') && 
            !menu.contains(e.target) && 
            !toggle.contains(e.target)) {
            closeMobileMenu();
        }
    });
    
    // Fermer avec la touche Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && menu.classList.contains('active')) {
            closeMobileMenu();
        }
    });
    
    function closeMobileMenu() {
        menu.classList.remove('active');
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
            if (flash && flash.parentElement) {
                flash.style.opacity = '0';
                setTimeout(() => {
                    if (flash && flash.parentElement) flash.remove();
                }, 300);
            }
        }, 5000);
    });
}

// ============================================
// NOTIFICATION TOAST
// ============================================

function showNotification(message, type = 'success') {
    const existing = document.querySelector('.cart-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = `cart-notification cart-notification-${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
    };
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b'
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
        font-size: 0.9rem;
    `;
    
    document.body.appendChild(notification);
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = 'background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:1.2rem;margin-left:8px';
    closeBtn.onclick = () => notification.remove();
    
    setTimeout(() => {
        if (notification && notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification && notification.parentElement) notification.remove();
            }, 300);
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
            
            if (imageUrl && mainImage) {
                mainImage.style.backgroundImage = `url(${imageUrl})`;
                mainImage.style.backgroundSize = 'cover';
                mainImage.style.backgroundPosition = 'center';
                if (mainImage.querySelector('i')) {
                    mainImage.querySelector('i').style.display = 'none';
                }
            } else if (icon && mainImage) {
                mainImage.style.backgroundImage = '';
                mainImage.innerHTML = `<i class="${icon}"></i>`;
            }
            
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
    
    form.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('focus', function() {
            this.style.borderColor = '';
            const error = this.parentElement.querySelector('.field-error');
            if (error) error.remove();
        });
    });
}

// ============================================
// MODE DE PAIEMENT (CASH / PAPAY)
// ============================================

function initPaymentMethodToggle() {
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const papayFields = document.getElementById('papayFields');
    
    if (!paymentMethods.length || !papayFields) return;
    
    function togglePapayFields() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        if (selected && selected.value === 'papay') {
            papayFields.style.display = 'block';
            const papayPhone = document.getElementById('papayPhone');
            if (papayPhone) papayPhone.required = true;
        } else {
            papayFields.style.display = 'none';
            const papayPhone = document.getElementById('papayPhone');
            if (papayPhone) papayPhone.required = false;
        }
    }
    
    paymentMethods.forEach(method => {
        method.removeEventListener('change', togglePapayFields);
        method.addEventListener('change', togglePapayFields);
    });
    
    togglePapayFields();
}

function initPapayFields() {
    const papayPhone = document.getElementById('papayPhone');
    const papayPin = document.getElementById('papayPin');
    
    if (papayPhone) {
        papayPhone.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 10) value = value.slice(0, 10);
            this.value = value;
        });
    }
    
    if (papayPin) {
        papayPin.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 4) value = value.slice(0, 4);
            this.value = value;
        });
    }
}

// ============================================
// PRICE FORMATTING
// ============================================

function initPriceFormatting() {
    const priceElements = document.querySelectorAll('.price, .product-price, .item-price, .cart-price, .cart-item-total, .cart-item-total-mobile');
    
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
        input.removeEventListener('input', handleSearchInput);
        input.addEventListener('input', handleSearchInput);
    });
}

function handleSearchInput() {
    clearTimeout(searchTimeout);
    
    searchTimeout = setTimeout(() => {
        const form = this.closest('form');
        if (form && (this.value.length >= 2 || this.value.length === 0)) {
            form.submit();
        }
    }, 500);
}

// ============================================
// GÉOLOCALISATION (CHECKOUT)
// ============================================

function initGeolocation() {
    const detectBtn = document.getElementById('detectLocationBtn');
    if (!detectBtn) return;
    
    detectBtn.addEventListener('click', detectLocation);
}

function detectLocation() {
    const detectBtn = document.getElementById('detectLocationBtn');
    const geoStatus = document.getElementById('geoStatus');
    
    if (!navigator.geolocation) {
        showGeoStatus(geoStatus, 'La géolocalisation n\'est pas supportée', 'error');
        return;
    }
    
    if (detectBtn) {
        detectBtn.disabled = true;
        detectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Détection...';
    }
    
    showGeoStatus(geoStatus, 'Recherche de votre position...', 'info');
    
    navigator.geolocation.getCurrentPosition(
        async (position) => {
            await getAddressFromCoordinates(position.coords.latitude, position.coords.longitude);
        },
        (error) => {
            if (detectBtn) {
                detectBtn.disabled = false;
                detectBtn.innerHTML = '<i class="fas fa-location-dot"></i> Détecter ma position';
            }
            
            let errorMessage = 'Géolocalisation échouée';
            if (error.code === error.PERMISSION_DENIED) {
                errorMessage = 'Vous avez refusé la géolocalisation';
            }
            showGeoStatus(geoStatus, errorMessage, 'error');
        }
    );
}

async function getAddressFromCoordinates(lat, lng) {
    const detectBtn = document.getElementById('detectLocationBtn');
    const geoStatus = document.getElementById('geoStatus');
    
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=fr`);
        const data = await response.json();
        
        if (data && data.address) {
            const address = data.address;
            const road = address.road || '';
            const houseNumber = address.house_number || '';
            const fullStreet = houseNumber ? `${houseNumber} ${road}` : road;
            const postalCode = address.postcode || '';
            const city = address.city || address.town || address.village || '';
            
            const addressField = document.getElementById('address');
            const postalField = document.getElementById('postalCode');
            const cityField = document.getElementById('city');
            
            if (fullStreet && addressField && !addressField.value) {
                addressField.value = fullStreet;
            }
            if (postalCode && postalField) postalField.value = postalCode;
            if (city && cityField) cityField.value = city;
            
            showGeoStatus(geoStatus, `Adresse détectée : ${fullStreet}, ${postalCode} ${city}`, 'success');
            
            highlightField(addressField);
            highlightField(postalField);
            highlightField(cityField);
        } else {
            showGeoStatus(geoStatus, 'Impossible de trouver l\'adresse', 'error');
        }
    } catch (error) {
        console.error('Erreur géocodage:', error);
        showGeoStatus(geoStatus, 'Erreur lors de la récupération de l\'adresse', 'error');
    } finally {
        if (detectBtn) {
            detectBtn.disabled = false;
            detectBtn.innerHTML = '<i class="fas fa-location-dot"></i> Détecter ma position';
        }
        
        setTimeout(() => {
            if (geoStatus) geoStatus.innerHTML = '';
        }, 5000);
    }
}

function showGeoStatus(element, message, type) {
    if (!element) return;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };
    
    element.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${message}`;
    element.className = `geo-status ${type}`;
}

function highlightField(field) {
    if (!field || !field.value) return;
    
    field.style.transition = 'all 0.3s';
    field.style.borderColor = '#10b981';
    setTimeout(() => {
        field.style.borderColor = '';
    }, 2000);
}

// ============================================
// NEWSLETTER FORM (SUPPRIMÉ SI NON UTILISÉ)
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
// STYLES DYNAMIQUES POUR ANIMATIONS
// ============================================

if (!document.querySelector('#mars-shop-styles')) {
    const animationStyles = document.createElement('style');
    animationStyles.id = 'mars-shop-styles';
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
        
        .product-card {
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .cart-qty::-webkit-inner-spin-button,
        .cart-qty::-webkit-outer-spin-button,
        .cart-qty-mobile::-webkit-inner-spin-button,
        .cart-qty-mobile::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .cart-qty, .cart-qty-mobile {
            -moz-appearance: textfield;
        }
        
        .field-error {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(animationStyles);
}

// ============================================
// UTILITAIRES
// ============================================

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

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

function getUrlParams() {
    const params = {};
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    
    for (const [key, value] of urlParams) {
        params[key] = value;
    }
    
    return params;
}

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
// EXPOSER LES FONCTIONS GLOBALEMENT
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
        updateUrlParams,
        detectLocation
    };
    
    // Initialiser la géolocalisation si présente sur la page
    if (document.getElementById('detectLocationBtn')) {
        initGeolocation();
    }
    
    // Initialiser les modes de paiement
    if (document.querySelector('input[name="payment_method"]')) {
        initPaymentMethodToggle();
        initPapayFields();
    }
}

console.log('Mars Shop JS - Version complète chargée');