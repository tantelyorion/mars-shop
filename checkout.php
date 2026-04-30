<?php
// checkout.php - Version finale entièrement corrigée
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

// Récupérer les méthodes de paiement actives
$stmt = $conn->prepare("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order");
$stmt->execute();
$payment_methods = $stmt->fetchAll();

// Récupérer les comptes Mobile Money actifs
$stmt = $conn->prepare("SELECT * FROM mobile_money_accounts WHERE is_active = 1");
$stmt->execute();
$mobile_accounts = $stmt->fetchAll();

// Infos utilisateur déjà connecté
$stmt = $conn->prepare("SELECT username, full_name, email, phone FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Finaliser ma commande - Mars Shop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f0f14 0%, #0a0a0e 100%);
            color: #ffffff;
            line-height: 1.5;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .checkout-header {
            text-align: center;
            padding: 30px 0 20px;
            border-bottom: 1px solid #2a2a35;
            margin-bottom: 30px;
        }
        
        .checkout-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        
        .checkout-header p {
            color: #a0a0b0;
            font-size: 0.9rem;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 32px;
            padding-bottom: 50px;
        }
        
        .checkout-form {
            background: #1a1a24;
            border-radius: 24px;
            padding: 28px;
            border: 1px solid #2a2a35;
        }
        
        .form-card {
            margin-bottom: 32px;
            padding-bottom: 32px;
            border-bottom: 1px solid #2a2a35;
        }
        
        .form-card:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            color: #c14432;
            width: 24px;
        }
        
        .address-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .address-tab {
            flex: 1;
            min-width: 140px;
        }
        
        .address-tab input {
            display: none;
        }
        
        .tab-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: #2a2a35;
            border: 2px solid #3a3a45;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .address-tab input:checked + .tab-btn {
            border-color: #c14432;
            background: rgba(193, 68, 50, 0.15);
            color: #c14432;
        }
        
        .address-panel {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .address-panel.active {
            display: block;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #a0a0b0;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #c14432;
            box-shadow: 0 0 0 3px rgba(193, 68, 50, 0.1);
        }
        
        .geo-btn {
            width: 100%;
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            padding: 14px;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .geo-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .geo-status {
            font-size: 0.75rem;
            padding: 10px;
            border-radius: 10px;
            margin-top: 12px;
            text-align: center;
        }
        
        .geo-status.success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .geo-status.error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .geo-status.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        
        .phone-field {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #2a2a35;
        }
        
        /* Modes de paiement */
        .payment-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .payment-item {
            background: #2a2a35;
            border: 2px solid #3a3a45;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .payment-item:hover {
            border-color: #c14432;
        }
        
        .payment-item input {
            display: none;
        }
        
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
        
        .payment-item input:checked ~ .payment-item-content .payment-radio {
            border-color: #c14432;
        }
        
        .payment-item input:checked ~ .payment-item-content .payment-radio::after {
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
        
        .payment-icon i {
            font-size: 1.6rem;
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-name {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .payment-desc {
            font-size: 0.7rem;
            color: #a0a0b0;
        }
        
        .payment-details {
            margin-top: 16px;
            padding: 20px;
            background: rgba(0,0,0,0.2);
            border-radius: 16px;
            display: none;
        }
        
        .payment-details.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .operator-group {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .operator-option {
            flex: 1;
            min-width: 100px;
            cursor: pointer;
        }
        
        .operator-option input {
            display: none;
        }
        
        .operator-card {
            background: #2a2a35;
            border: 2px solid #3a3a45;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            transition: all 0.2s;
        }
        
        .operator-option input:checked + .operator-card {
            border-color: #c14432;
            background: rgba(193, 68, 50, 0.1);
        }
        
        .operator-card i {
            font-size: 1.5rem;
            margin-bottom: 6px;
            display: block;
        }
        
        .operator-card span {
            font-size: 0.8rem;
        }
        
        .mobile-info {
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            padding: 16px;
            margin: 16px 0;
        }
        
        .info-note {
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            padding: 12px;
            font-size: 0.8rem;
            color: #3b82f6;
            text-align: center;
        }
        
        .warning-note {
            background: rgba(245, 158, 11, 0.1);
            border-radius: 8px;
            padding: 10px;
            font-size: 0.7rem;
            color: #f59e0b;
            margin-top: 12px;
        }
        
        .shop-number {
            color: #c14432;
            font-weight: 600;
            font-size: 1rem;
        }
        
        /* Résumé commande */
        .order-summary {
            background: #1a1a24;
            border-radius: 24px;
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
        
        .items-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 16px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #2a2a35;
        }
        
        .item-name {
            font-size: 0.85rem;
        }
        
        .item-qty {
            font-size: 0.7rem;
            color: #a0a0b0;
            margin-left: 6px;
        }
        
        .item-price {
            font-weight: 500;
            color: #c14432;
        }
        
        .summary-divider {
            height: 1px;
            background: #2a2a35;
            margin: 16px 0;
        }
        
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
        
        .summary-line.total strong {
            color: #c14432;
            font-size: 1.2rem;
        }
        
        .free-shipping {
            color: #10b981;
        }
        
        .secure-badge {
            margin-top: 20px;
            padding: 12px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 12px;
            text-align: center;
            font-size: 0.75rem;
            color: #10b981;
        }
        
        .form-actions {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #2a2a35;
        }
        
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
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(193, 68, 50, 0.3);
        }
        
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: #a0a0b0;
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        
        .btn-back:hover {
            color: #c14432;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 968px) {
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            .order-summary {
                position: static;
                order: 2;
            }
            .checkout-form { order: 1; }
        }
        
        @media (max-width: 768px) {
            .checkout-header h1 { font-size: 1.5rem; }
            .checkout-form { padding: 20px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .address-tabs { flex-direction: column; }
            .address-tab { min-width: auto; }
            .operator-group { flex-direction: column; }
            .payment-item-content { flex-wrap: wrap; }
        }
        
        @media (max-width: 480px) {
            .container { padding: 0 16px; }
            .checkout-form { padding: 16px; }
            .payment-icon { width: 40px; height: 40px; }
            .payment-icon i { font-size: 1.2rem; }
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
                
                <!-- ADRESSE DE LIVRAISON -->
                <div class="form-card">
                    <div class="card-title">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Adresse de livraison</span>
                    </div>
                    
                    <div class="address-tabs">
                        <label class="address-tab">
                            <input type="radio" name="address_type" value="auto" id="autoAddressRadio" checked>
                            <div class="tab-btn">
                                <i class="fas fa-location-dot"></i>
                                <span>Géolocalisation</span>
                            </div>
                        </label>
                        <label class="address-tab">
                            <input type="radio" name="address_type" value="manual" id="manualAddressRadio">
                            <div class="tab-btn">
                                <i class="fas fa-pen-alt"></i>
                                <span>Saisie manuelle</span>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Géolocalisation -->
                    <div id="autoPanel" class="address-panel active">
                        <button type="button" id="detectLocationBtn" class="geo-btn">
                            <i class="fas fa-location-dot"></i> Détecter ma position
                        </button>
                        <div id="geoStatus" class="geo-status"></div>
                        <!-- Champ caché pour stocker l'adresse détectée -->
                        <input type="hidden" id="detectedAddress" name="detected_address" value="">
                        <input type="hidden" id="detectedPostalCode" name="detected_postal_code" value="">
                        <input type="hidden" id="detectedCity" name="detected_city" value="">
                        <input type="hidden" id="detectedCountry" name="detected_country" value="">
                    </div>
                    
                    <!-- Saisie manuelle -->
                    <div id="manualPanel" class="address-panel">
                        <div class="form-group">
                            <label>Adresse complète</label>
                            <textarea name="manual_address" id="manualAddress" rows="2" placeholder="Numéro, rue, complément..."></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Code postal</label>
                                <input type="text" name="manual_zip" id="manualZip" placeholder="75001">
                            </div>
                            <div class="form-group">
                                <label>Ville</label>
                                <input type="text" name="manual_city" id="manualCity" placeholder="Paris">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Pays</label>
                            <select name="manual_country" id="manualCountry">
                                <option value="France">France</option>
                                <option value="Belgique">Belgique</option>
                                <option value="Suisse">Suisse</option>
                                <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                                <option value="Sénégal">Sénégal</option>
                                <option value="Cameroun">Cameroun</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Téléphone -->
                    <div class="phone-field">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Téléphone (pour le livreur)</label>
                            <input type="tel" name="phone" id="phoneNumber" placeholder="Votre numéro de téléphone" value="<?php echo clean($user['phone']); ?>">
                            <small style="font-size: 0.7rem; color: #a0a0b0;">Optionnel mais recommandé</small>
                        </div>
                    </div>
                </div>
                
                <!-- MODE DE PAIEMENT -->
                <div class="form-card">
                    <div class="card-title">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Mode de paiement</span>
                    </div>
                    
                    <div class="payment-grid">
                        <!-- Carte Bancaire -->
                        <label class="payment-item">
                            <input type="radio" name="payment_method" value="credit_card" id="paymentCard">
                            <div class="payment-item-content">
                                <div class="payment-radio"></div>
                                <div class="payment-icon">
                                    <i class="fab fa-cc-visa"></i>
                                    <i class="fab fa-cc-mastercard" style="margin-left: -8px;"></i>
                                </div>
                                <div class="payment-info">
                                    <div class="payment-name">Carte bancaire</div>
                                    <div class="payment-desc">Visa, Mastercard (paiement sécurisé)</div>
                                </div>
                            </div>
                        </label>
                        <div id="cardFields" class="payment-details">
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
                        
                        <!-- PayPal -->
                        <label class="payment-item">
                            <input type="radio" name="payment_method" value="paypal" id="paymentPaypal">
                            <div class="payment-item-content">
                                <div class="payment-radio"></div>
                                <div class="payment-icon">
                                    <i class="fab fa-cc-paypal"></i>
                                </div>
                                <div class="payment-info">
                                    <div class="payment-name">PayPal</div>
                                    <div class="payment-desc">Paiement via votre compte PayPal</div>
                                </div>
                            </div>
                        </label>
                        <div id="paypalFields" class="payment-details">
                            <div class="info-note">
                                <i class="fab fa-paypal"></i> Vous serez redirigé vers PayPal pour finaliser votre paiement.
                            </div>
                        </div>
                        
                        <!-- Mobile Money -->
                        <label class="payment-item">
                            <input type="radio" name="payment_method" value="mobile_money" id="paymentMobile">
                            <div class="payment-item-content">
                                <div class="payment-radio"></div>
                                <div class="payment-icon">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="payment-info">
                                    <div class="payment-name">Mobile Money</div>
                                    <div class="payment-desc">Airtel Money, Mvola, Orange Money</div>
                                </div>
                            </div>
                        </label>
                        <div id="mobileFields" class="payment-details">
                            <div class="operator-group">
                                <?php foreach($mobile_accounts as $account): ?>
                                <label class="operator-option">
                                    <input type="radio" name="mobile_operator" value="<?php echo $account['operator']; ?>">
                                    <div class="operator-card">
                                        <i class="fas fa-<?php echo $account['operator'] === 'airtel' ? 'tower-cell' : ($account['operator'] === 'mvola' ? 'mobile' : 'sim-card'); ?>"></i>
                                        <span><?php echo $account['operator_name']; ?></span>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <div id="mobileInfoContainer" class="mobile-info" style="display: none;">
                                <?php foreach($mobile_accounts as $account): ?>
                                <div class="operator-detail" data-operator="<?php echo $account['operator']; ?>" style="display: none;">
                                    <p><strong>📱 Instructions de paiement :</strong></p>
                                    <ol>
                                        <li>Composez le code USSD de votre opérateur</li>
                                        <li>Sélectionnez "Transfert d'argent"</li>
                                        <li>Entrez le numéro : <strong class="shop-number"><?php echo $account['phone_number']; ?></strong></li>
                                        <li>Entrez le montant : <strong><?php echo formatPrice($total); ?></strong></li>
                                        <li>Confirmez la transaction avec votre code secret</li>
                                    </ol>
                                    <div class="warning-note">
                                        <i class="fas fa-info-circle"></i> Conservez précieusement le numéro de transaction reçu par SMS
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="form-group">
                                <label>Numéro de transaction (reçu par SMS)</label>
                                <input type="text" name="mobile_transaction_id" id="mobileTransactionId" placeholder="Ex: TRX-123456789">
                            </div>
                        </div>
                        
                        <!-- Cash à la livraison -->
                        <label class="payment-item">
                            <input type="radio" name="payment_method" value="cash" id="paymentCash" checked>
                            <div class="payment-item-content">
                                <div class="payment-radio"></div>
                                <div class="payment-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="payment-info">
                                    <div class="payment-name">Paiement à la livraison</div>
                                    <div class="payment-desc">Payez en espèces à la réception</div>
                                </div>
                            </div>
                        </label>
                        <div id="cashFields" class="payment-details">
                            <div class="info-note" style="background: rgba(16,185,129,0.1); color: #10b981;">
                                <i class="fas fa-check-circle"></i> Vous payez directement au livreur lors de la réception de votre commande.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Instructions livreur -->
                    <div class="form-group" style="margin-top: 20px;">
                        <label><i class="fas fa-pencil-alt"></i> Instructions pour le livreur</label>
                        <textarea name="notes" id="deliveryNotes" rows="2" placeholder="Code interphone, étage, sonnette..."></textarea>
                    </div>
                </div>
                
                <!-- Boutons -->
                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-check-circle"></i> Confirmer la commande
                        <span>(<?php echo formatPrice($total); ?>)</span>
                    </button>
                    <a href="cart.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Retour au panier
                    </a>
                </div>
                
                <!-- Champs cachés pour l'adresse (seront remplis par JS) -->
                <input type="hidden" name="address" id="finalAddress">
                <input type="hidden" name="postal_code" id="finalZip">
                <input type="hidden" name="city" id="finalCity">
                <input type="hidden" name="country" id="finalCountry">
                
            </form>
        </div>
        
        <!-- Résumé commande -->
        <div class="order-summary">
            <div class="summary-title">
                <i class="fas fa-receipt"></i>
                <span>Récapitulatif</span>
            </div>
            
            <div class="items-list">
                <?php foreach($cart_items as $item): ?>
                <div class="summary-item">
                    <div>
                        <span class="item-name"><?php echo clean($item['name']); ?></span>
                        <span class="item-qty">x<?php echo $item['quantity']; ?></span>
                    </div>
                    <div class="item-price"><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
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
            
            <div class="secure-badge">
                <i class="fas fa-lock"></i> Paiement 100% sécurisé
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// CHECKOUT.JS - VERSION FINALE CORRIGÉE
// ============================================

// === 1. GESTION DES ONGLETS ADRESSE ===
const autoRadio = document.getElementById('autoAddressRadio');
const manualRadio = document.getElementById('manualAddressRadio');
const autoPanel = document.getElementById('autoPanel');
const manualPanel = document.getElementById('manualPanel');

function toggleAddressPanel() {
    if (autoRadio.checked) {
        autoPanel.classList.add('active');
        manualPanel.classList.remove('active');
    } else {
        autoPanel.classList.remove('active');
        manualPanel.classList.add('active');
    }
}

if (autoRadio && manualRadio) {
    autoRadio.addEventListener('change', toggleAddressPanel);
    manualRadio.addEventListener('change', toggleAddressPanel);
    toggleAddressPanel();
}

// === 2. GESTION DES MOYENS DE PAIEMENT ===
const paymentCard = document.getElementById('paymentCard');
const paymentPaypal = document.getElementById('paymentPaypal');
const paymentMobile = document.getElementById('paymentMobile');
const paymentCash = document.getElementById('paymentCash');
const cardFields = document.getElementById('cardFields');
const paypalFields = document.getElementById('paypalFields');
const mobileFields = document.getElementById('mobileFields');
const cashFields = document.getElementById('cashFields');

function hideAllPaymentDetails() {
    if (cardFields) cardFields.classList.remove('active');
    if (paypalFields) paypalFields.classList.remove('active');
    if (mobileFields) mobileFields.classList.remove('active');
    if (cashFields) cashFields.classList.remove('active');
}

function togglePaymentDetails() {
    hideAllPaymentDetails();
    
    if (paymentCard.checked && cardFields) {
        cardFields.classList.add('active');
    } else if (paymentPaypal.checked && paypalFields) {
        paypalFields.classList.add('active');
    } else if (paymentMobile.checked && mobileFields) {
        mobileFields.classList.add('active');
    } else if (paymentCash.checked && cashFields) {
        cashFields.classList.add('active');
    }
}

if (paymentCard) paymentCard.addEventListener('change', togglePaymentDetails);
if (paymentPaypal) paymentPaypal.addEventListener('change', togglePaymentDetails);
if (paymentMobile) paymentMobile.addEventListener('change', togglePaymentDetails);
if (paymentCash) paymentCash.addEventListener('change', togglePaymentDetails);
togglePaymentDetails();

// === 3. GESTION MOBILE MONEY ===
const operatorRadios = document.querySelectorAll('input[name="mobile_operator"]');
const operatorDetails = document.querySelectorAll('.operator-detail');
const mobileInfoContainer = document.getElementById('mobileInfoContainer');

function toggleMobileOperatorInfo() {
    const selected = document.querySelector('input[name="mobile_operator"]:checked');
    
    if (!selected) {
        if (mobileInfoContainer) mobileInfoContainer.style.display = 'none';
        return;
    }
    
    const operator = selected.value;
    
    operatorDetails.forEach(detail => {
        detail.style.display = 'none';
    });
    
    const selectedDetail = document.querySelector(`.operator-detail[data-operator="${operator}"]`);
    if (selectedDetail && mobileInfoContainer) {
        mobileInfoContainer.style.display = 'block';
        selectedDetail.style.display = 'block';
    }
}

operatorRadios.forEach(radio => {
    radio.addEventListener('change', toggleMobileOperatorInfo);
});
toggleMobileOperatorInfo();

// === 4. FORMATAGE CARTE BANCAIRE ===
const cardNumberInput = document.getElementById('cardNumber');
if (cardNumberInput) {
    cardNumberInput.addEventListener('input', function(e) {
        let value = this.value.replace(/\s/g, '');
        if (value.length > 16) value = value.slice(0, 16);
        value = value.replace(/(\d{4})/g, '$1 ').trim();
        this.value = value;
    });
}

const expiryInput = document.getElementById('cardExpiry');
if (expiryInput) {
    expiryInput.addEventListener('input', function(e) {
        let value = this.value.replace(/\//g, '');
        if (value.length >= 2) {
            value = value.slice(0, 2) + '/' + value.slice(2, 4);
        }
        this.value = value;
    });
}

const cvvInput = document.getElementById('cardCvv');
if (cvvInput) {
    cvvInput.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 4) value = value.slice(0, 4);
        this.value = value;
    });
}

// === 5. GÉOLOCALISATION - CORRIGÉE ===
const detectBtn = document.getElementById('detectLocationBtn');
const geoStatus = document.getElementById('geoStatus');
const detectedAddress = document.getElementById('detectedAddress');
const detectedPostalCode = document.getElementById('detectedPostalCode');
const detectedCity = document.getElementById('detectedCity');
const detectedCountry = document.getElementById('detectedCountry');

function showGeoStatus(message, type) {
    if (!geoStatus) return;
    geoStatus.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${message}`;
    geoStatus.className = `geo-status ${type}`;
}

async function getAddressFromCoordinates(lat, lng) {
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
            const country = address.country || 'France';
            
            // Stocker dans les champs cachés
            if (detectedAddress) detectedAddress.value = fullStreet;
            if (detectedPostalCode) detectedPostalCode.value = postalCode;
            if (detectedCity) detectedCity.value = city;
            if (detectedCountry) detectedCountry.value = country;
            
            showGeoStatus(`✅ Adresse détectée : ${fullStreet}, ${postalCode} ${city}`, 'success');
        } else {
            showGeoStatus('❌ Impossible de trouver l\'adresse', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showGeoStatus('❌ Erreur lors de la récupération', 'error');
    } finally {
        if (detectBtn) {
            detectBtn.disabled = false;
            detectBtn.innerHTML = '<i class="fas fa-location-dot"></i> Détecter ma position';
        }
    }
}

function detectLocation() {
    if (!navigator.geolocation) {
        showGeoStatus('❌ La géolocalisation n\'est pas supportée', 'error');
        return;
    }
    
    if (detectBtn) {
        detectBtn.disabled = true;
        detectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Détection...';
    }
    showGeoStatus('📍 Recherche de votre position...', 'info');
    
    navigator.geolocation.getCurrentPosition(
        async (position) => {
            await getAddressFromCoordinates(position.coords.latitude, position.coords.longitude);
        },
        (error) => {
            if (detectBtn) {
                detectBtn.disabled = false;
                detectBtn.innerHTML = '<i class="fas fa-location-dot"></i> Détecter ma position';
            }
            
            let errorMsg = '❌ Géolocalisation refusée';
            if (error.code === error.PERMISSION_DENIED) {
                errorMsg = '❌ Vous avez refusé la géolocalisation';
            } else if (error.code === error.TIMEOUT) {
                errorMsg = '❌ Délai dépassé';
            }
            showGeoStatus(errorMsg, 'error');
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

if (detectBtn) {
    detectBtn.addEventListener('click', detectLocation);
}

// === 6. SOUMISSION DU FORMULAIRE - CORRIGÉE ===
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
    const addressType = document.querySelector('input[name="address_type"]:checked').value;
    
    const finalAddress = document.getElementById('finalAddress');
    const finalZip = document.getElementById('finalZip');
    const finalCity = document.getElementById('finalCity');
    const finalCountry = document.getElementById('finalCountry');
    
    if (addressType === 'auto') {
        // Récupérer l'adresse depuis les champs cachés de géolocalisation
        const autoAddr = detectedAddress ? detectedAddress.value : '';
        const autoZip = detectedPostalCode ? detectedPostalCode.value : '';
        const autoCity = detectedCity ? detectedCity.value : '';
        const autoCountry = detectedCountry ? detectedCountry.value : 'France';
        
        if (!autoAddr) {
            e.preventDefault();
            showGeoStatus('❌ Veuillez d\'abord détecter votre position', 'error');
            return;
        }
        
        finalAddress.value = autoAddr;
        finalZip.value = autoZip;
        finalCity.value = autoCity;
        finalCountry.value = autoCountry;
        
    } else {
        // Saisie manuelle
        const manualAddress = document.getElementById('manualAddress')?.value;
        const manualZip = document.getElementById('manualZip')?.value;
        const manualCity = document.getElementById('manualCity')?.value;
        const manualCountry = document.getElementById('manualCountry')?.value;
        
        if (!manualAddress) {
            e.preventDefault();
            showGeoStatus('❌ Veuillez saisir votre adresse', 'error');
            return;
        }
        
        finalAddress.value = manualAddress;
        finalZip.value = manualZip || '';
        finalCity.value = manualCity || '';
        finalCountry.value = manualCountry || 'France';
    }
    
    // Validation téléphone
    const phoneInput = document.getElementById('phoneNumber');
    if (phoneInput && phoneInput.value) {
        let phone = phoneInput.value.replace(/\D/g, '');
        if (phone.length > 0 && phone.length < 8) {
            e.preventDefault();
            showGeoStatus('❌ Numéro de téléphone invalide', 'error');
            return;
        }
    }
    
    // Validation Mobile Money
    if (paymentMobile && paymentMobile.checked) {
        const operator = document.querySelector('input[name="mobile_operator"]:checked');
        if (!operator) {
            e.preventDefault();
            showGeoStatus('❌ Veuillez sélectionner un opérateur Mobile Money', 'error');
            return;
        }
        const transactionId = document.getElementById('mobileTransactionId')?.value;
        if (!transactionId) {
            e.preventDefault();
            showGeoStatus('❌ Veuillez saisir votre numéro de transaction', 'error');
            return;
        }
    }
    
    // Validation carte bancaire
    if (paymentCard && paymentCard.checked) {
        const cardNumber = document.getElementById('cardNumber')?.value.replace(/\s/g, '');
        const cardExpiry = document.getElementById('cardExpiry')?.value;
        const cardCvv = document.getElementById('cardCvv')?.value;
        
        if (!cardNumber || cardNumber.length < 13) {
            e.preventDefault();
            showGeoStatus('❌ Numéro de carte invalide', 'error');
            return;
        }
        if (!cardExpiry || cardExpiry.length < 5) {
            e.preventDefault();
            showGeoStatus('❌ Date d\'expiration invalide', 'error');
            return;
        }
        if (!cardCvv || cardCvv.length < 3) {
            e.preventDefault();
            showGeoStatus('❌ CVV invalide', 'error');
            return;
        }
    }
    
    // Debug
    console.log('=== SOUMISSION COMMANDE ===');
    console.log('Type adresse:', addressType);
    console.log('Adresse:', finalAddress.value);
    console.log('Code postal:', finalZip.value);
    console.log('Ville:', finalCity.value);
    console.log('Pays:', finalCountry.value);
    console.log('Mode paiement:', document.querySelector('input[name="payment_method"]:checked')?.value);
});
</script>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>