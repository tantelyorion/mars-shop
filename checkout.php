<?php
// checkout.php - Validation de commande avec géolocalisation
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer le panier
$stmt = $conn->prepare("
    SELECT c.id as cart_id, c.quantity, p.* 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    header('Location: shop.php');
    exit();
}

$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$total = $subtotal;

// Infos utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<div class="checkout-page">
    <div class="container">
        <div class="checkout-header">
            <h2><i class="fas fa-credit-card"></i> Validation de commande</h2>
            <p>Completez vos informations pour finaliser votre commande</p>
        </div>
        
        <div class="checkout-grid">
            <!-- Formulaire -->
            <div class="checkout-form">
                <form method="POST" action="payment-simulate.php" id="checkoutForm">
                    <!-- Section géolocalisation -->
                    <div class="form-section geo-section">
                        <div class="geo-header">
                            <h3><i class="fas fa-map-marker-alt"></i> Localisation automatique</h3>
                            <button type="button" id="detectLocationBtn" class="btn-geo">
                                <i class="fas fa-location-dot"></i> Détecter ma position
                            </button>
                        </div>
                        <div id="geoStatus" class="geo-status"></div>
                        <p class="geo-hint">
                            <i class="fas fa-info-circle"></i> 
                            Activez la géolocalisation pour pré-remplir automatiquement votre adresse
                        </p>
                    </div>
                    
                    <!-- Informations de livraison -->
                    <div class="form-section">
                        <h3><i class="fas fa-truck"></i> Informations de livraison</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom complet *</label>
                                <input type="text" name="full_name" id="fullName" required value="<?php echo clean($user['full_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" id="email" required value="<?php echo clean($user['email']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Téléphone *</label>
                                <input type="tel" name="phone" id="phone" required value="<?php echo clean($user['phone']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Adresse *</label>
                            <textarea name="address" id="address" rows="2" required placeholder="Numéro et nom de rue"><?php echo clean($user['address']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Code postal *</label>
                                <input type="text" name="postal_code" id="postalCode" required placeholder="ex: 75001" value="75001">
                            </div>
                            <div class="form-group">
                                <label>Ville *</label>
                                <input type="text" name="city" id="city" required placeholder="ex: Paris" value="Paris">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Pays *</label>
                            <select name="country" id="country" required>
                                <option value="France">France</option>
                                <option value="Belgique">Belgique</option>
                                <option value="Suisse">Suisse</option>
                                <option value="Luxembourg">Luxembourg</option>
                                <option value="Canada">Canada</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="save_address" value="1" checked>
                                <span>Enregistrer cette adresse pour mes prochaines commandes</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Mode de paiement -->
                    <div class="form-section">
                        <h3><i class="fas fa-credit-card"></i> Paiement</h3>
                        
                        <div class="payment-methods">
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="credit_card" checked>
                                <div class="payment-method-content">
                                    <i class="fab fa-cc-visa"></i>
                                    <i class="fab fa-cc-mastercard"></i>
                                    <span>Carte bancaire</span>
                                </div>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="paypal">
                                <div class="payment-method-content">
                                    <i class="fab fa-paypal"></i>
                                    <span>PayPal</span>
                                </div>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="cash">
                                <div class="payment-method-content">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>Paiement à la livraison</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-group">
                            <label>Notes (optionnel)</label>
                            <textarea name="notes" rows="2" placeholder="Instructions particulières pour la livraison..."></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="cart.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour au panier
                        </a>
                        <button type="submit" class="btn-primary btn-checkout">
                            <i class="fas fa-check-circle"></i> Confirmer la commande
                            <span class="checkout-total">(<?php echo formatPrice($total); ?>)</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Résumé de commande -->
            <div class="checkout-summary">
                <h3><i class="fas fa-receipt"></i> Récapitulatif</h3>
                
                <div class="summary-items">
                    <?php foreach($cart_items as $item): ?>
                    <div class="summary-item">
                        <div class="summary-item-info">
                            <span class="summary-item-name"><?php echo clean($item['name']); ?></span>
                            <span class="summary-item-qty">x<?php echo $item['quantity']; ?></span>
                        </div>
                        <div class="summary-item-price"><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-divider"></div>
                
                <div class="summary-row">
                    <span>Sous-total</span>
                    <span><?php echo formatPrice($subtotal); ?></span>
                </div>
                <div class="summary-row">
                    <span>Livraison</span>
                    <span class="free-shipping">Offerte</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <strong><?php echo formatPrice($total); ?></strong>
                </div>
                
                <div class="secure-payment">
                    <i class="fas fa-lock"></i> Paiement 100% sécurisé
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.checkout-page {
    padding: 30px 0;
}

.checkout-header {
    text-align: center;
    margin-bottom: 30px;
}

.checkout-header h2 {
    margin-bottom: 8px;
}

.checkout-header p {
    color: var(--text-muted);
}

.checkout-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 30px;
}

/* Formulaire */
.checkout-form {
    background: var(--gray);
    border-radius: 20px;
    padding: 28px;
}

.form-section {
    margin-bottom: 28px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--border);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.form-section h3 {
    margin-bottom: 20px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section h3 i {
    color: var(--primary-light);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 0.85rem;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    background: var(--gray-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(193, 68, 50, 0.1);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input {
    width: auto;
}

.checkbox-label span {
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* Section géolocalisation */
.geo-section {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(59, 130, 246, 0.05));
    border-radius: 16px;
    padding: 20px;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.geo-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 16px;
}

.geo-header h3 {
    margin-bottom: 0;
}

.btn-geo {
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    padding: 10px 20px;
    border-radius: 40px;
    color: white;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-geo:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-geo:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.geo-status {
    margin-bottom: 12px;
    font-size: 0.8rem;
}

.geo-status.success {
    color: #10b981;
}

.geo-status.error {
    color: #ef4444;
}

.geo-status.info {
    color: #3b82f6;
}

.geo-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 8px;
}

.geo-hint i {
    margin-right: 4px;
}

/* Modes de paiement */
.payment-methods {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-method {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: var(--gray-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.payment-method:hover {
    border-color: var(--primary);
    background: rgba(193, 68, 50, 0.05);
}

.payment-method input {
    margin-right: 12px;
    width: auto;
}

.payment-method-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.payment-method-content i {
    font-size: 1.5rem;
    color: var(--text-muted);
}

.payment-method-content .fa-cc-visa { color: #1a1f71; }
.payment-method-content .fa-cc-mastercard { color: #eb001b; }
.payment-method-content .fa-paypal { color: #003087; }

/* Actions formulaire */
.form-actions {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.btn-checkout {
    padding: 14px 28px;
    font-size: 1rem;
}

.checkout-total {
    margin-left: 8px;
    font-weight: bold;
}

/* Résumé commande */
.checkout-summary {
    background: var(--gray);
    border-radius: 20px;
    padding: 24px;
    height: fit-content;
    position: sticky;
    top: 100px;
}

.checkout-summary h3 {
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.summary-items {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 16px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.summary-item-info {
    display: flex;
    gap: 8px;
    align-items: baseline;
}

.summary-item-name {
    font-size: 0.9rem;
}

.summary-item-qty {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.summary-item-price {
    font-weight: 500;
}

.summary-divider {
    height: 1px;
    background: var(--border);
    margin: 16px 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}

.summary-row.total {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    font-size: 1.2rem;
}

.summary-row.total strong {
    color: var(--primary-light);
    font-size: 1.3rem;
}

.free-shipping {
    color: var(--success);
}

.secure-payment {
    margin-top: 20px;
    padding: 12px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 12px;
    text-align: center;
    font-size: 0.8rem;
    color: var(--success);
}

.secure-payment i {
    margin-right: 8px;
}

/* Responsive */
@media (max-width: 968px) {
    .checkout-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .checkout-summary {
        position: static;
        order: 2;
    }
    
    .checkout-form {
        order: 1;
    }
}

@media (max-width: 768px) {
    .checkout-page {
        padding: 20px 0;
    }
    
    .checkout-form {
        padding: 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .geo-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-checkout {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .checkout-form {
        padding: 16px;
    }
    
    .payment-method {
        padding: 10px 12px;
    }
}
</style>

<script>
// ============================================
// GÉOLOCALISATION AUTOMATIQUE
// ============================================

const detectBtn = document.getElementById('detectLocationBtn');
const geoStatus = document.getElementById('geoStatus');

// Fonction pour détecter la position
function detectLocation() {
    if (!navigator.geolocation) {
        showGeoStatus('La géolocalisation n\'est pas supportée par votre navigateur', 'error');
        return;
    }
    
    detectBtn.disabled = true;
    detectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Détection en cours...';
    showGeoStatus('Recherche de votre position...', 'info');
    
    navigator.geolocation.getCurrentPosition(
        async (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            showGeoStatus('Position détectée ! Récupération de l\'adresse...', 'info');
            
            // Utiliser l'API de géocodage inverse (Nominatim - OpenStreetMap)
            await getAddressFromCoordinates(lat, lng);
        },
        (error) => {
            detectBtn.disabled = false;
            detectBtn.innerHTML = '<i class="fas fa-location-dot"></i> Détecter ma position';
            
            let errorMessage = '';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage = 'Vous avez refusé la géolocalisation. Veuillez entrer votre adresse manuellement.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage = 'Position indisponible. Veuillez entrer votre adresse manuellement.';
                    break;
                case error.TIMEOUT:
                    errorMessage = 'Délai dépassé. Veuillez entrer votre adresse manuellement.';
                    break;
                default:
                    errorMessage = 'Erreur de géolocalisation. Veuillez entrer votre adresse manuellement.';
            }
            showGeoStatus(errorMessage, 'error');
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Récupérer l'adresse à partir des coordonnées
async function getAddressFromCoordinates(lat, lng) {
    try {
        // Utiliser l'API Nominatim d'OpenStreetMap (gratuite, pas besoin de clé)
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=fr`);
        const data = await response.json();
        
        if (data && data.address) {
            const address = data.address;
            
            // Extraire les informations
            const road = address.road || address.hamlet || address.suburb || '';
            const houseNumber = address.house_number || '';
            const fullStreet = houseNumber ? `${houseNumber} ${road}` : road;
            
            const postalCode = address.postcode || '';
            const city = address.city || address.town || address.village || '';
            const country = address.country || 'France';
            
            // Remplir le formulaire
            if (fullStreet && document.getElementById('address')) {
                const currentAddress = document.getElementById('address').value;
                if (!currentAddress || currentAddress === '') {
                    document.getElementById('address').value = fullStreet;
                }
            }
            
            if (postalCode && document.getElementById('postalCode')) {
                document.getElementById('postalCode').value = postalCode;
            }
            
            if (city && document.getElementById('city')) {
                document.getElementById('city').value = city;
            }
            
            if (country && document.getElementById('country')) {
                const countrySelect = document.getElementById('country');
                for(let i = 0; i < countrySelect.options.length; i++) {
                    if(countrySelect.options[i].value === country || 
                       countrySelect.options[i].text === country) {
                        countrySelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            showGeoStatus(`Adresse détectée : ${fullStreet}, ${postalCode} ${city}`, 'success');
            
            // Mettre en évidence les champs remplis
            highlightFilledFields();
            
        } else {
            showGeoStatus('Impossible de trouver l\'adresse correspondante', 'error');
        }
    } catch (error) {
        console.error('Erreur géocodage:', error);
        showGeoStatus('Erreur lors de la récupération de l\'adresse', 'error');
    } finally {
        detectBtn.disabled = false;
        detectBtn.innerHTML = '<i class="fas fa-location-dot"></i> Détecter ma position';
        
        // Cacher le message après 5 secondes
        setTimeout(() => {
            if (geoStatus) {
                geoStatus.style.opacity = '0';
                setTimeout(() => {
                    if (geoStatus) geoStatus.innerHTML = '';
                    if (geoStatus) geoStatus.style.opacity = '1';
                }, 300);
            }
        }, 5000);
    }
}

// Afficher le statut de géolocalisation
function showGeoStatus(message, type) {
    if (!geoStatus) return;
    geoStatus.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${message}`;
    geoStatus.className = `geo-status ${type}`;
}

// Mettre en évidence les champs remplis
function highlightFilledFields() {
    const fields = ['address', 'postalCode', 'city'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && field.value) {
            field.style.transition = 'all 0.3s';
            field.style.borderColor = '#10b981';
            setTimeout(() => {
                field.style.borderColor = '';
            }, 2000);
        }
    });
}

// Écouteur d'événement
detectBtn?.addEventListener('click', detectLocation);

// Validation du formulaire avant soumission
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#ef4444';
            isValid = false;
        } else {
            field.style.borderColor = '';
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        showNotification('Veuillez remplir tous les champs obligatoires', 'error');
    }
});

// Nettoyer les erreurs au focus
document.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('focus', function() {
        this.style.borderColor = '';
    });
});

// Fonction de notification simple
function showNotification(message, type) {
    const existing = document.querySelector('.checkout-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'checkout-notification';
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: var(--gray);
        border-left: 4px solid ${type === 'success' ? '#10b981' : '#ef4444'};
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
    setTimeout(() => notification.remove(), 3000);
}

// Animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);

// Pré-remplir si des données sont enregistrées
if (localStorage.getItem('savedAddress')) {
    try {
        const saved = JSON.parse(localStorage.getItem('savedAddress'));
        if (saved.address) document.getElementById('address').value = saved.address;
        if (saved.postalCode) document.getElementById('postalCode').value = saved.postalCode;
        if (saved.city) document.getElementById('city').value = saved.city;
        if (saved.country) document.getElementById('country').value = saved.country;
    } catch(e) {}
}

// Sauvegarder l'adresse si coché
document.querySelector('input[name="save_address"]')?.addEventListener('change', function() {
    if (this.checked) {
        const addressData = {
            address: document.getElementById('address').value,
            postalCode: document.getElementById('postalCode').value,
            city: document.getElementById('city').value,
            country: document.getElementById('country').value
        };
        localStorage.setItem('savedAddress', JSON.stringify(addressData));
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>