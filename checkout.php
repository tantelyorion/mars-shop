<?php
// checkout.php - Version avec géolocalisation GPS, paiement CB et PayPal
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

// Récupérer les méthodes de paiement ACTIVES (uniquement CB et PayPal)
$stmt = $conn->prepare("SELECT * FROM payment_methods WHERE is_active = 1 AND name IN ('credit_card', 'paypal') ORDER BY sort_order");
$stmt->execute();
$active_payment_methods = $stmt->fetchAll();

// Si aucune méthode active, on crée des méthodes par défaut
if (empty($active_payment_methods)) {
    $active_payment_methods = [
        ['name' => 'credit_card', 'display_name' => 'Carte bancaire', 'description' => 'Visa, Mastercard (paiement sécurisé)'],
        ['name' => 'paypal', 'display_name' => 'PayPal', 'description' => 'Paiement via votre compte PayPal']
    ];
}

// Infos utilisateur
$stmt = $conn->prepare("SELECT username, full_name, email, phone FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Configuration des icônes
$method_icons = [
    'credit_card' => 'fa-credit-card',
    'paypal' => 'fa-paypal'
];

$method_names = [
    'credit_card' => 'Carte bancaire',
    'paypal' => 'PayPal'
];

$method_descs = [
    'credit_card' => 'Visa, Mastercard (paiement sécurisé)',
    'paypal' => 'Paiement via votre compte PayPal'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser ma commande - Mars Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f14;
            color: #fff;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        .checkout-header {
            text-align: center;
            padding: 30px 0 20px;
            border-bottom: 1px solid #2a2a35;
            margin-bottom: 30px;
        }
        .checkout-header h1 { font-size: 1.8rem; margin-bottom: 8px; }
        .checkout-header p { color: #a0a0b0; }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 32px;
            padding-bottom: 50px;
        }
        
        .checkout-form {
            background: #1a1a24;
            border-radius: 20px;
            padding: 28px;
            border: 1px solid #2a2a35;
        }
        
        .form-card {
            margin-bottom: 28px;
            padding-bottom: 28px;
            border-bottom: 1px solid #2a2a35;
        }
        .form-card:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-title i { color: #c14432; }
        
        /* Adresse - Choix entre GPS et manuel */
        .address-options {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .address-btn {
            flex: 1;
            padding: 12px;
            background: #2a2a35;
            border: 2px solid #3a3a45;
            border-radius: 12px;
            cursor: pointer;
            text-align: center;
            font-weight: 500;
            transition: all 0.2s;
        }
        .address-btn.active {
            border-color: #c14432;
            background: rgba(193,68,50,0.15);
            color: #c14432;
        }
        
        .address-panel { display: none; margin-top: 16px; }
        .address-panel.active { display: block; }
        
        /* Géolocalisation GPS */
        .geo-btn {
            width: 100%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            padding: 14px;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .geo-status {
            font-size: 0.75rem;
            padding: 10px;
            border-radius: 10px;
            margin-top: 12px;
            text-align: center;
        }
        .geo-status.success { background: rgba(16,185,129,0.1); color: #10b981; }
        .geo-status.error { background: rgba(239,68,68,0.1); color: #ef4444; }
        .geo-status.info { background: rgba(59,130,246,0.1); color: #3b82f6; }
        
        .gps-coords-card {
            background: rgba(59,130,246,0.1);
            border: 1px solid #3b82f6;
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }
        .gps-coords-card p {
            font-size: 0.85rem;
            margin-bottom: 4px;
            font-family: monospace;
        }
        .gps-coords-card strong {
            color: #3b82f6;
        }
        
        /* Adresse manuelle */
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            color: #a0a0b0;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #c14432;
        }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        
        /* Paiement */
        .payment-item {
            background: #2a2a35;
            border: 2px solid #3a3a45;
            border-radius: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .payment-item:hover { border-color: #c14432; }
        .payment-item.active { border-color: #c14432; background: rgba(193,68,50,0.1); }
        .payment-item-content {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
        }
        .payment-radio {
            width: 22px;
            height: 22px;
            border: 2px solid #a0a0b0;
            border-radius: 50%;
            position: relative;
            flex-shrink: 0;
        }
        .payment-item.active .payment-radio { border-color: #c14432; }
        .payment-item.active .payment-radio::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 12px;
            height: 12px;
            background: #c14432;
            border-radius: 50%;
        }
        .payment-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .payment-icon i { font-size: 1.5rem; }
        .payment-info { flex: 1; }
        .payment-name { font-weight: 600; margin-bottom: 4px; }
        .payment-desc { font-size: 0.7rem; color: #a0a0b0; }
        
        .payment-details {
            display: none;
            padding: 20px;
            background: rgba(0,0,0,0.2);
            border-radius: 16px;
            margin-top: 0;
        }
        .payment-details.show { display: block; }
        
        .info-note {
            background: rgba(59,130,246,0.1);
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            font-size: 0.85rem;
            color: #3b82f6;
        }
        
        /* Résumé */
        .order-summary {
            background: #1a1a24;
            border-radius: 20px;
            padding: 24px;
            height: fit-content;
            position: sticky;
            top: 100px;
            border: 1px solid #2a2a35;
        }
        .summary-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #2a2a35;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #2a2a35;
        }
        .summary-divider { height: 1px; background: #2a2a35; margin: 16px 0; }
        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .summary-line.total {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #2a2a35;
            font-size: 1.1rem;
        }
        .summary-line.total strong { color: #c14432; font-size: 1.2rem; }
        .free-shipping { color: #10b981; }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #c14432, #e8755a);
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .btn-submit:hover { transform: translateY(-2px); }
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: #a0a0b0;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .btn-back:hover { color: #c14432; }
        
        @media (max-width: 768px) {
            .checkout-grid { grid-template-columns: 1fr; }
            .order-summary { position: static; }
            .form-row { grid-template-columns: 1fr; }
            .address-options { flex-direction: column; }
        }
    </style>
</head>
<body>

<?php require_once 'includes/header.php'; ?>

<div class="container">
    <div class="checkout-header">
        <h1><i class="fas fa-credit-card"></i> Finaliser ma commande</h1>
        <p>Complétez vos informations pour valider votre commande</p>
    </div>
    
    <div class="checkout-grid">
        <div class="checkout-form">
            <form method="POST" action="payment-process.php" id="checkoutForm">
                
                <!-- ==================== POINT DE LIVRAISON ==================== -->
                <div class="form-card">
                    <div class="card-title">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Point de livraison</span>
                    </div>
                    
                    <div class="address-options">
                        <div class="address-btn" data-type="gps">
                            <i class="fas fa-location-dot"></i> Géolocalisation GPS
                        </div>
                        <div class="address-btn" data-type="manual">
                            <i class="fas fa-pen-alt"></i> Adresse manuelle
                        </div>
                    </div>
                    
                    <!-- Panneau Géolocalisation GPS -->
                    <div id="gpsPanel" class="address-panel active">
                        <button type="button" id="detectLocationBtn" class="geo-btn">
                            <i class="fas fa-map-pin"></i> Me localiser (GPS)
                        </button>
                        <div id="geoStatus" class="geo-status"></div>
                        <div id="gpsCoordsDisplay" style="display: none;">
                            <div class="gps-coords-card">
                                <p><i class="fas fa-satellite-dish"></i> <strong>Coordonnées GPS :</strong></p>
                                <p id="displayLat">Latitude : --</p>
                                <p id="displayLng">Longitude : --</p>
                                <p style="font-size: 0.7rem; margin-top: 8px;"><i class="fas fa-info-circle"></i> Ces coordonnées seront transmises au livreur</p>
                            </div>
                        </div>
                        <input type="hidden" id="deliveryLatitude" name="delivery_latitude">
                        <input type="hidden" id="deliveryLongitude" name="delivery_longitude">
                    </div>
                    
                    <!-- Panneau Adresse manuelle -->
                    <div id="manualPanel" class="address-panel">
                        <div class="form-group">
                            <label>Adresse complète</label>
                            <textarea id="manualAddress" rows="2" placeholder="Numéro, rue, complément..."></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Code postal</label>
                                <input type="text" id="manualZip" placeholder="75001">
                            </div>
                            <div class="form-group">
                                <label>Ville</label>
                                <input type="text" id="manualCity" placeholder="Paris">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Pays</label>
                            <select id="manualCountry">
                                <option value="France">France</option>
                                <option value="Belgique">Belgique</option>
                                <option value="Suisse">Suisse</option>
                                <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                                <option value="Sénégal">Sénégal</option>
                                <option value="Cameroun">Cameroun</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Champs cachés pour l'envoi (adresse manuelle) -->
                    <input type="hidden" name="address" id="finalAddress">
                    <input type="hidden" name="postal_code" id="finalZip">
                    <input type="hidden" name="city" id="finalCity">
                    <input type="hidden" name="country" id="finalCountry">
                    <input type="hidden" name="address_type" id="addressType" value="gps">
                </div>
                
                <!-- ==================== MODE DE PAIEMENT ==================== -->
                <div class="form-card">
                    <div class="card-title">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Mode de paiement</span>
                    </div>
                    
                    <div class="payment-list" id="paymentList">
                        <?php 
                        $first_method = true;
                        foreach($active_payment_methods as $method): 
                            $method_name = $method['name'];
                        ?>
                        <div class="payment-item <?php echo $first_method ? 'active' : ''; ?>" data-method="<?php echo $method_name; ?>">
                            <div class="payment-item-content">
                                <div class="payment-radio"></div>
                                <div class="payment-icon">
                                    <i class="fas <?php echo $method_icons[$method_name] ?? 'fa-credit-card'; ?>"></i>
                                </div>
                                <div class="payment-info">
                                    <div class="payment-name"><?php echo $method_names[$method_name] ?? ucfirst($method_name); ?></div>
                                    <div class="payment-desc"><?php echo $method_descs[$method_name] ?? ''; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Détails Carte Bancaire -->
                        <?php if($method_name === 'credit_card'): ?>
                        <div id="cardDetails" class="payment-details <?php echo $first_method ? 'show' : ''; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Numéro de carte</label>
                                    <input type="text" name="card_number" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Date d'expiration</label>
                                    <input type="text" name="card_expiry" id="cardExpiry" placeholder="MM/YY" maxlength="5">
                                </div>
                                <div class="form-group">
                                    <label>CVV</label>
                                    <input type="text" name="card_cvv" id="cardCvv" placeholder="123" maxlength="4">
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Détails PayPal -->
                        <?php if($method_name === 'paypal'): ?>
                        <div id="paypalDetails" class="payment-details <?php echo $first_method ? 'show' : ''; ?>">
                            <div class="info-note">
                                <i class="fab fa-paypal"></i> Vous serez redirigé vers PayPal pour finaliser votre paiement.
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $first_method = false;
                        endforeach; 
                        ?>
                    </div>
                    
                    <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="<?php echo $active_payment_methods[0]['name'] ?? 'credit_card'; ?>">
                    
                    <!-- Instructions livreur -->
                    <div class="form-group" style="margin-top: 20px;">
                        <label><i class="fas fa-pencil-alt"></i> Instructions pour le livreur</label>
                        <textarea name="notes" rows="2" placeholder="Code interphone, étage, sonnette..."></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-check-circle"></i> Confirmer la commande
                    <span>(<?php echo formatPrice($total); ?>)</span>
                </button>
                <a href="cart.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour au panier
                </a>
            </form>
        </div>
        
        <!-- RÉSUMÉ -->
        <div class="order-summary">
            <div class="summary-title">
                <i class="fas fa-receipt"></i>
                <span>Récapitulatif</span>
            </div>
            <?php foreach($cart_items as $item): ?>
            <div class="summary-item">
                <div>
                    <span class="item-name"><?php echo clean($item['name']); ?></span>
                    <span class="item-qty" style="font-size: 0.7rem; color: #a0a0b0;">x<?php echo $item['quantity']; ?></span>
                </div>
                <div class="item-price" style="color: #c14432;"><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
            </div>
            <?php endforeach; ?>
            <div class="summary-divider"></div>
            <div class="summary-line">
                <span>Sous-total</span>
                <span><?php echo formatPrice($subtotal); ?></span>
            </div>
            <div class="summary-line">
                <span>Livraison</span>
                <span class="free-shipping">Offerte</span>
            </div>
            <div class="summary-line total">
                <span>Total</span>
                <strong><?php echo formatPrice($total); ?></strong>
            </div>
            <div class="secure-badge" style="margin-top: 20px; padding: 12px; background: rgba(16,185,129,0.1); border-radius: 12px; text-align: center; font-size: 0.75rem; color: #10b981;">
                <i class="fas fa-lock"></i> Paiement 100% sécurisé
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// CHECKOUT.JS - GPS et Paiement CB/PayPal
// ============================================

// 1. GESTION ADRESSE (GPS vs Manuel)
const gpsBtn = document.querySelector('.address-btn[data-type="gps"]');
const manualAddrBtn = document.querySelector('.address-btn[data-type="manual"]');
const gpsPanel = document.getElementById('gpsPanel');
const manualPanel = document.getElementById('manualPanel');
const addressTypeInput = document.getElementById('addressType');

function setActiveAddress(type) {
    gpsBtn.classList.remove('active');
    manualAddrBtn.classList.remove('active');
    if (type === 'gps') {
        gpsBtn.classList.add('active');
        gpsPanel.classList.add('active');
        manualPanel.classList.remove('active');
        addressTypeInput.value = 'gps';
    } else {
        manualAddrBtn.classList.add('active');
        manualPanel.classList.add('active');
        gpsPanel.classList.remove('active');
        addressTypeInput.value = 'manual';
    }
}

if(gpsBtn) gpsBtn.addEventListener('click', () => setActiveAddress('gps'));
if(manualAddrBtn) manualAddrBtn.addEventListener('click', () => setActiveAddress('manual'));

// 2. GESTION PAIEMENT
const paymentItems = document.querySelectorAll('.payment-item');
const paymentDetails = {
    credit_card: document.getElementById('cardDetails'),
    paypal: document.getElementById('paypalDetails')
};

function setActivePayment(method) {
    paymentItems.forEach(item => item.classList.remove('active'));
    Object.values(paymentDetails).forEach(detail => {
        if(detail) detail.classList.remove('show');
    });
    const selectedItem = document.querySelector(`.payment-item[data-method="${method}"]`);
    if(selectedItem) selectedItem.classList.add('active');
    if(paymentDetails[method]) paymentDetails[method].classList.add('show');
    document.getElementById('selectedPaymentMethod').value = method;
}

paymentItems.forEach(item => {
    item.addEventListener('click', () => {
        const method = item.dataset.method;
        setActivePayment(method);
    });
});

// 3. GÉOLOCALISATION GPS
const detectBtn = document.getElementById('detectLocationBtn');
const geoStatus = document.getElementById('geoStatus');
const gpsCoordsDisplay = document.getElementById('gpsCoordsDisplay');
const displayLat = document.getElementById('displayLat');
const displayLng = document.getElementById('displayLng');
const deliveryLatitude = document.getElementById('deliveryLatitude');
const deliveryLongitude = document.getElementById('deliveryLongitude');

function showGeoStatus(msg, type) {
    if(!geoStatus) return;
    geoStatus.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${msg}`;
    geoStatus.className = `geo-status ${type}`;
}

function detectGPSLocation() {
    if(!navigator.geolocation) {
        showGeoStatus('❌ GPS non supporté par votre navigateur', 'error');
        return;
    }
    
    if(detectBtn) {
        detectBtn.disabled = true;
        detectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recherche GPS...';
    }
    showGeoStatus('📍 Recherche de votre position GPS...', 'info');
    
    navigator.geolocation.getCurrentPosition(
        position => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            if(deliveryLatitude) deliveryLatitude.value = lat;
            if(deliveryLongitude) deliveryLongitude.value = lng;
            
            if(displayLat) displayLat.innerHTML = `Latitude : <strong>${lat.toFixed(6)}</strong>`;
            if(displayLng) displayLng.innerHTML = `Longitude : <strong>${lng.toFixed(6)}</strong>`;
            if(gpsCoordsDisplay) gpsCoordsDisplay.style.display = 'block';
            
            showGeoStatus(`✅ Coordonnées GPS enregistrées avec précision`, 'success');
        },
        error => {
            if(detectBtn) {
                detectBtn.disabled = false;
                detectBtn.innerHTML = '<i class="fas fa-map-pin"></i> Me localiser (GPS)';
            }
            
            let errorMsg = '❌ Impossible de vous localiser';
            if(error.code === error.PERMISSION_DENIED) {
                errorMsg = '❌ Vous avez refusé l\'accès GPS. Veuillez saisir une adresse manuelle.';
            } else if(error.code === error.TIMEOUT) {
                errorMsg = '❌ Délai dépassé. Vérifiez votre connexion GPS.';
            }
            showGeoStatus(errorMsg, 'error');
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

if(detectBtn) detectBtn.addEventListener('click', detectGPSLocation);

// 4. FORMATAGE CARTE BANCAIRE
const cardNumber = document.getElementById('cardNumber');
if(cardNumber) {
    cardNumber.addEventListener('input', function() {
        let v = this.value.replace(/\s/g, '');
        if(v.length > 16) v = v.slice(0,16);
        v = v.replace(/(\d{4})/g, '$1 ').trim();
        this.value = v;
    });
}

const cardExpiry = document.getElementById('cardExpiry');
if(cardExpiry) {
    cardExpiry.addEventListener('input', function() {
        let v = this.value.replace('/', '');
        if(v.length >= 2) v = v.slice(0,2) + '/' + v.slice(2,4);
        this.value = v;
    });
}

// 5. SOUMISSION
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const addressType = addressTypeInput ? addressTypeInput.value : 'gps';
    const finalAddress = document.getElementById('finalAddress');
    const finalZip = document.getElementById('finalZip');
    const finalCity = document.getElementById('finalCity');
    const finalCountry = document.getElementById('finalCountry');
    
    if(addressType === 'gps') {
        const lat = deliveryLatitude ? deliveryLatitude.value : '';
        const lng = deliveryLongitude ? deliveryLongitude.value : '';
        if(!lat || !lng) {
            e.preventDefault();
            showGeoStatus('❌ Veuillez d\'abord activer votre GPS', 'error');
            return;
        }
        finalAddress.value = `GPS: ${lat}, ${lng}`;
        finalZip.value = '';
        finalCity.value = '';
        finalCountry.value = 'GPS';
    } else {
        const manualAddr = document.getElementById('manualAddress');
        if(!manualAddr || !manualAddr.value) {
            e.preventDefault();
            showGeoStatus('❌ Veuillez saisir votre adresse', 'error');
            return;
        }
        finalAddress.value = manualAddr.value;
        finalZip.value = document.getElementById('manualZip')?.value || '';
        finalCity.value = document.getElementById('manualCity')?.value || '';
        finalCountry.value = document.getElementById('manualCountry')?.value || 'France';
    }
    
    // Validation carte bancaire
    const paymentMethod = document.getElementById('selectedPaymentMethod')?.value;
    if(paymentMethod === 'credit_card') {
        const num = document.getElementById('cardNumber')?.value.replace(/\s/g, '');
        const exp = document.getElementById('cardExpiry')?.value;
        const cvv = document.getElementById('cardCvv')?.value;
        if(!num || num.length < 13) {
            e.preventDefault();
            showGeoStatus('❌ Numéro de carte invalide', 'error');
            return;
        }
        if(!exp || exp.length < 5) {
            e.preventDefault();
            showGeoStatus('❌ Date d\'expiration invalide', 'error');
            return;
        }
        if(!cvv || cvv.length < 3) {
            e.preventDefault();
            showGeoStatus('❌ CVV invalide', 'error');
            return;
        }
    }
    // PayPal : pas de validation côté client
});
</script>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>