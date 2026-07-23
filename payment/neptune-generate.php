<?php
/**
 * Neptune Pay - Génération d'un paiement
 * Appelé depuis le checkout
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/neptune_pay.php';

requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Vérifier que les données sont présentes
$orderNumber = $_POST['order_number'] ?? '';
$amount = (float) ($_POST['amount'] ?? 0);
$currency = $_POST['currency'] ?? 'EUR';
$description = $_POST['description'] ?? 'Commande Mars Shop';

if (empty($orderNumber) || $amount <= 0) {
    header('Location: ../checkout.php?error=invalid_data');
    exit();
}

// Récupérer les infos client
$stmt = $conn->prepare("SELECT email, phone, full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Générer le paiement Neptune Pay
$payment = neptuneGeneratePayment(
    $amount,
    $currency,
    $orderNumber,
    $description,
    [
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'name' => $user['full_name'] ?? ''
    ]
);

if (!$payment) {
    // Erreur - rediriger vers checkout
    setFlashMessage('error', 'Erreur de connexion à Neptune Pay. Veuillez réessayer.');
    header('Location: ../checkout.php');
    exit();
}

// Stocker les infos en session pour la vérification
$_SESSION['neptune_payment'] = [
    'code' => $payment['code'],
    'payment_id' => $payment['payment_id'],
    'reference' => $payment['reference'],
    'order_number' => $orderNumber,
    'amount' => $amount,
    'currency' => $currency,
    'expires_at' => $payment['expires_at']
];

// Afficher la page de paiement Neptune Pay
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Neptune Pay - Mars Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f14;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .payment-container {
            max-width: 500px;
            width: 100%;
            background: #1a1a24;
            border-radius: 20px;
            padding: 32px;
            border: 1px solid #2a2a35;
            text-align: center;
        }
        
        .payment-header {
            margin-bottom: 24px;
        }
        
        .payment-header .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .payment-header .logo i {
            font-size: 32px;
            color: #00c2a8;
        }
        
        .payment-header h1 {
            font-size: 22px;
            font-weight: 700;
        }
        
        .payment-header p {
            color: #a0a0b0;
            font-size: 14px;
        }
        
        .payment-amount {
            background: #2a2a35;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .payment-amount .label {
            font-size: 13px;
            color: #a0a0b0;
        }
        
        .payment-amount .amount {
            font-size: 32px;
            font-weight: 700;
            color: #00c2a8;
        }
        
        .payment-amount .reference {
            font-size: 12px;
            color: #a0a0b0;
            margin-top: 8px;
        }
        
        .qr-container {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            display: inline-block;
        }
        
        .qr-container img {
            max-width: 200px;
            height: auto;
        }
        
        .payment-code {
            background: #0f0f14;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            font-family: monospace;
            font-size: 28px;
            font-weight: 700;
            color: #00c2a8;
            letter-spacing: 4px;
        }
        
        .payment-timer {
            color: #a0a0b0;
            font-size: 14px;
        }
        
        .payment-timer span {
            color: #fff;
            font-weight: 600;
        }
        
        .payment-instructions {
            text-align: left;
            margin: 24px 0;
            padding: 16px;
            background: #0f0f14;
            border-radius: 12px;
            border-left: 3px solid #00c2a8;
        }
        
        .payment-instructions li {
            list-style: none;
            padding: 6px 0;
            font-size: 14px;
            color: #a0a0b0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-instructions li i {
            color: #00c2a8;
            width: 20px;
            text-align: center;
        }
        
        .btn-back {
            display: inline-block;
            margin-top: 16px;
            color: #a0a0b0;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        .btn-back:hover {
            color: #c14432;
        }
        
        .status-pending {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            background: rgba(251, 191, 36, 0.15);
            color: #f59e0b;
            font-size: 13px;
            font-weight: 500;
        }
        
        .refresh-btn {
            background: #2a2a35;
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 12px;
            transition: background 0.2s;
        }
        
        .refresh-btn:hover {
            background: #3a3a45;
        }
    </style>
</head>
<body>
    <div class="payment-container neptune-payment-container">
        <div class="payment-header">
            <div class="logo">
                <i class="fas fa-water"></i>
                <span style="font-size:20px;font-weight:700;">Neptune Pay</span>
                <span style="font-size:12px;color:#a0a0b0;background:#2a2a35;padding:2px 10px;border-radius:12px;">× Mars Shop</span>
            </div>
            <h1>Paiement en ligne</h1>
            <p>Payez avec Neptune Pay en scannant le QR code</p>
        </div>
        
        <div class="payment-amount">
            <div class="label">Montant à payer</div>
            <div class="amount"><?php echo formatPrice($amount); ?></div>
            <div class="reference">Commande #<?php echo htmlspecialchars($orderNumber); ?></div>
        </div>
        
        <div class="qr-container">
            <img src="<?php echo htmlspecialchars($payment['qr_code_url']); ?>" alt="QR Code Neptune Pay">
        </div>
        
        <div class="payment-code">
            <?php echo htmlspecialchars($payment['code']); ?>
        </div>
        
        <div class="payment-timer">
            <i class="fas fa-clock"></i> Expire dans <span id="neptuneTimer">10:00</span>
        </div>
        
        <div class="payment-instructions">
            <ul>
                <li><i class="fas fa-qrcode"></i> Ouvrez l'application Neptune Pay</li>
                <li><i class="fas fa-camera"></i> Scannez le QR code ci-dessus</li>
                <li><i class="fas fa-check-circle"></i> Confirmez le paiement</li>
                <li><i class="fas fa-credit-card"></i> Validez votre transaction</li>
            </ul>
        </div>
        
        <div style="margin-top: 16px;">
            <span class="status-pending">
                <i class="fas fa-spinner fa-spin"></i> En attente de paiement...
            </span>
        </div>
        
        <button onclick="checkPaymentStatus()" class="refresh-btn">
            <i class="fas fa-sync-alt"></i> Vérifier le statut
        </button>
        
        <div id="statusMessage" style="margin-top: 12px; font-size: 14px;"></div>
        
        <a href="../checkout.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Retour au checkout
        </a>
    </div>
    
    <script>
        // ============================================================
        // COMPTE À REBOURS
        // ============================================================
        const endTime = <?php echo strtotime($payment['expires_at']) * 1000; ?>;
        const timerElement = document.getElementById('neptuneTimer');
        
        function updateTimer() {
            const now = Date.now();
            const diff = Math.max(0, endTime - now);
            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            timerElement.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            if (diff <= 0) {
                timerElement.textContent = 'Expiré';
                clearInterval(timerInterval);
                document.querySelector('.payment-container').innerHTML = `
                    <div style="text-align:center;padding:20px;">
                        <i class="fas fa-clock" style="font-size:48px;color:#ef4444;"></i>
                        <h2 style="margin-top:12px;color:#ef4444;">Paiement expiré</h2>
                        <p style="color:#a0a0b0;">Le temps imparti pour le paiement est écoulé.</p>
                        <a href="../checkout.php" class="btn-back" style="margin-top:16px;display:inline-block;">
                            <i class="fas fa-arrow-left"></i> Retour au checkout
                        </a>
                    </div>
                `;
            }
        }
        
        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
        
        // ============================================================
        // VÉRIFICATION DU STATUT
        // ============================================================
        let isChecking = false;
        
        function checkPaymentStatus() {
            if (isChecking) return;
            isChecking = true;
            
            const statusDiv = document.getElementById('statusMessage');
            const refreshBtn = document.querySelector('.refresh-btn');
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Vérification...';
            refreshBtn.disabled = true;
            statusDiv.innerHTML = '';
            
            const code = '<?php echo $payment['code']; ?>';
            const orderNumber = '<?php echo $orderNumber; ?>';
            
            fetch('../payment/neptune-check-status.php?code=' + encodeURIComponent(code) + '&order=' + encodeURIComponent(orderNumber))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.status === 'paid') {
                            statusDiv.innerHTML = `
                                <div style="background:rgba(16,185,129,0.15);color:#10b981;padding:12px;border-radius:10px;">
                                    <i class="fas fa-check-circle"></i> 
                                    ✅ Paiement confirmé ! Redirection en cours...
                                </div>
                            `;
                            setTimeout(() => {
                                window.location.href = '../order-success.php?order=' + orderNumber;
                            }, 2000);
                        } else if (data.status === 'expired') {
                            statusDiv.innerHTML = `
                                <div style="background:rgba(239,68,68,0.15);color:#ef4444;padding:12px;border-radius:10px;">
                                    <i class="fas fa-times-circle"></i> 
                                    Ce paiement a expiré. Veuillez recommencer.
                                </div>
                            `;
                        } else {
                            statusDiv.innerHTML = `
                                <div style="background:rgba(251,191,36,0.15);color:#f59e0b;padding:12px;border-radius:10px;">
                                    <i class="fas fa-clock"></i> 
                                    En attente de paiement... (${data.status})
                                </div>
                            `;
                        }
                    } else {
                        statusDiv.innerHTML = `
                            <div style="background:rgba(239,68,68,0.15);color:#ef4444;padding:12px;border-radius:10px;">
                                <i class="fas fa-exclamation-circle"></i> 
                                ${data.error || 'Erreur de vérification'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    statusDiv.innerHTML = `
                        <div style="background:rgba(239,68,68,0.15);color:#ef4444;padding:12px;border-radius:10px;">
                            <i class="fas fa-exclamation-circle"></i> 
                            Erreur de connexion. Veuillez réessayer.
                        </div>
                    `;
                })
                .finally(() => {
                    isChecking = false;
                    refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Vérifier le statut';
                    refreshBtn.disabled = false;
                });
        }
        
        // Vérification automatique toutes les 5 secondes
        setInterval(checkPaymentStatus, 5000);
        
        // Vérification initiale après 3 secondes
        setTimeout(checkPaymentStatus, 3000);
    </script>
</body>
</html>