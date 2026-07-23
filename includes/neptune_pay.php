<?php
/**
 * Neptune Pay - Fonctions d'intégration
 * Appelées depuis le checkout
 */

require_once __DIR__ . '/../config/neptune_pay.php';

/**
 * Génère un paiement Neptune Pay
 * 
 * @param float $amount Montant à payer
 * @param string $currency Devise (EUR, MGA, etc.)
 * @param string $reference Référence unique (numéro de commande)
 * @param string $description Description du paiement
 * @param array $customer Infos client (email, phone)
 * @return array|false Données du paiement ou false en cas d'erreur
 */
function neptuneGeneratePayment($amount, $currency, $reference, $description, $customer = []) {
    global $NEPTUNE_CURRENCY_MAP;
    
    // Mapper la devise
    $currency = strtoupper($currency);
    $neptuneCurrency = isset($NEPTUNE_CURRENCY_MAP[$currency]) ? $NEPTUNE_CURRENCY_MAP[$currency] : 'EUR';
    
    // Préparer les données
    $data = [
        'amount' => (float) $amount,
        'currency' => $neptuneCurrency,
        'reference' => $reference,
        'description' => $description,
        'expires_in' => NEPTUNE_PAYMENT_EXPIRES_IN,
        'redirect_url' => NEPTUNE_CALLBACK_URL ?? 'https://mars-shop.com/payment/neptune-callback.php',
        'webhook_url' => NEPTUNE_WEBHOOK_URL
    ];
    
    // Ajouter les infos client si disponibles
    if (!empty($customer['email'])) {
        $data['payer_email'] = $customer['email'];
    }
    if (!empty($customer['phone'])) {
        $data['payer_phone'] = $customer['phone'];
    }
    
    // Appel API
    $ch = curl_init(NEPTUNE_API_URL . 'payment/generate.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . NEPTUNE_API_KEY,
        'X-API-Secret: ' . NEPTUNE_API_SECRET
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result['success']) {
            return $result['data'];
        }
    }
    
    // Log l'erreur
    error_log("Neptune Pay API Error: " . $response);
    return false;
}

/**
 * Vérifie le statut d'un paiement Neptune Pay
 * 
 * @param string $code Code de paiement (PAY-XXXX-XXXX)
 * @return array|false Statut du paiement ou false
 */
function neptuneGetPaymentStatus($code) {
    $url = NEPTUNE_API_URL . 'payment/status.php?code=' . urlencode($code);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . NEPTUNE_API_KEY,
        'X-API-Secret: ' . NEPTUNE_API_SECRET
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result['success']) {
            return $result['data'];
        }
    }
    
    return false;
}

/**
 * Vérifie la signature d'un webhook Neptune Pay
 */
function neptuneVerifyWebhookSignature($payload, $signature) {
    $expected = hash_hmac('sha256', $payload, NEPTUNE_WEBHOOK_SECRET);
    return hash_equals($expected, $signature);
}

/**
 * Génère un code de paiement formaté pour l'affichage
 */
function neptuneFormatCode($code) {
    return '<span style="font-family: monospace; font-size: 24px; font-weight: bold; background: #1a1a24; padding: 8px 16px; border-radius: 8px; color: #00c2a8; letter-spacing: 2px;">' . htmlspecialchars($code) . '</span>';
}

/**
 * Génère l'HTML pour afficher un QR code Neptune Pay
 */
function neptuneRenderQRCode($qrCodeUrl, $code) {
    return '
    <div style="text-align: center; padding: 20px;">
        <img src="' . htmlspecialchars($qrCodeUrl) . '" alt="QR Code Neptune Pay" style="max-width: 200px; height: auto; border-radius: 12px; background: white; padding: 12px;">
        <p style="margin-top: 12px; font-size: 14px; color: #a0a0b0;">
            Code : ' . neptuneFormatCode($code) . '
        </p>
        <p style="font-size: 12px; color: #a0a0b0;">
            <i class="fas fa-clock"></i> Expire dans <span id="neptuneTimer">10:00</span>
        </p>
        <p style="font-size: 12px; color: #a0a0b0; margin-top: 8px;">
            <i class="fas fa-qrcode"></i> Scannez le QR code avec l\'application Neptune Pay
        </p>
        <p style="font-size: 12px; color: #a0a0b0;">
            <i class="fas fa-keyboard"></i> Ou saisissez le code dans l\'application
        </p>
    </div>
    ';
}

/**
 * JavaScript pour le compte à rebours
 */
function neptuneCountdownJS($expiresAt) {
    $timestamp = strtotime($expiresAt);
    return '
    <script>
    (function() {
        const endTime = ' . ($timestamp * 1000) . ';
        const timerElement = document.getElementById("neptuneTimer");
        
        function updateTimer() {
            const now = Date.now();
            const diff = Math.max(0, endTime - now);
            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            timerElement.textContent = String(minutes).padStart(2, "0") + ":" + String(seconds).padStart(2, "0");
            
            if (diff <= 0) {
                timerElement.textContent = "Expiré";
                clearInterval(interval);
                document.querySelector(".neptune-payment-container").innerHTML = `
                    <div style="text-align:center;padding:30px;">
                        <i class="fas fa-clock" style="font-size:48px;color:#ef4444;"></i>
                        <p style="margin-top:12px;color:#ef4444;">Ce paiement a expiré.</p>
                        <p style="font-size:14px;color:#a0a0b0;">Veuillez réessayer.</p>
                    </div>
                `;
            }
        }
        
        const interval = setInterval(updateTimer, 1000);
        updateTimer();
    })();
    </script>
    ';
}