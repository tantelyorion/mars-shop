<?php
// admin/payment-methods.php - Gestion des méthodes de paiement (CB et PayPal)
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$conn = getConnection();

// Activer/désactiver une méthode
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE payment_methods SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: payment-methods.php');
    exit();
}

// Mise à jour des paramètres (clés API, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $payment_method = clean($_POST['payment_method']);
    $settings = [];
    if ($payment_method === 'paypal') {
        $settings['client_id'] = clean($_POST['paypal_client_id']);
        $settings['client_secret'] = clean($_POST['paypal_client_secret']);
        $settings['mode'] = clean($_POST['paypal_mode']);
    } elseif ($payment_method === 'credit_card') {
        $settings['api_key'] = clean($_POST['cc_api_key']);
        $settings['enabled'] = true;
    }
    $settings_json = json_encode($settings);
    $stmt = $conn->prepare("UPDATE payment_methods SET settings = ? WHERE name = ?");
    $stmt->execute([$settings_json, $payment_method]);
    setFlashMessage('success', 'Paramètres mis à jour');
    header('Location: payment-methods.php');
    exit();
}

// Récupérer les données
$stmt = $conn->query("SELECT * FROM payment_methods ORDER BY sort_order");
$payment_methods = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Méthodes de paiement - Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f0f14;
            color: #fff;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1, h2, h3 {
            margin-bottom: 20px;
        }
        
        .card {
            background: #1a1a24;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid #2a2a35;
        }
        
        .methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .method-card {
            background: #2a2a35;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .method-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .method-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .method-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .method-icon i {
            font-size: 1.8rem;
        }
        
        .method-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .method-desc {
            font-size: 0.75rem;
            color: #a0a0b0;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #10b981;
            color: white;
        }
        
        .status-inactive {
            background: #ef4444;
            color: white;
        }
        
        .btn-toggle {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-toggle.active {
            background: #10b981;
            color: white;
        }
        
        .btn-toggle.inactive {
            background: #ef4444;
            color: white;
        }
        
        .btn-primary {
            background: #c14432;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background: #a63a2a;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.85rem;
            color: #a0a0b0;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 8px;
            color: white;
        }
        
        .settings-form {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #2a2a35;
        }
        
        .settings-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #c14432;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .settings-form .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← Retour au tableau de bord</a>
    
    <h1><i class="fas fa-credit-card"></i> Méthodes de paiement</h1>
    
    <!-- Méthodes de paiement -->
    <div class="card">
        <h2>Méthodes actives</h2>
        <div class="methods-grid">
            <?php foreach($payment_methods as $method): ?>
            <div class="method-card">
                <div class="method-header">
                    <div class="method-info">
                        <div class="method-icon">
                            <i class="fas <?php echo $method['name'] == 'credit_card' ? 'fa-credit-card' : 'fa-paypal'; ?>"></i>
                        </div>
                        <div>
                            <div class="method-name"><?php echo $method['display_name']; ?></div>
                            <div class="method-desc"><?php echo $method['description']; ?></div>
                        </div>
                    </div>
                    <div>
                        <span class="status-badge <?php echo $method['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $method['is_active'] ? 'Actif' : 'Inactif'; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Bouton toggle -->
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <a href="?toggle=1&id=<?php echo $method['id']; ?>" class="btn-toggle <?php echo $method['is_active'] ? 'inactive' : 'active'; ?>">
                        <?php echo $method['is_active'] ? 'Désactiver' : 'Activer'; ?>
                    </a>
                </div>
                
                <!-- Paramètres spécifiques (uniquement pour PayPal et CB) -->
                <?php if(in_array($method['name'], ['paypal', 'credit_card'])): ?>
                <div class="settings-form">
                    <form method="POST">
                        <input type="hidden" name="payment_method" value="<?php echo $method['name']; ?>">
                        <?php if($method['name'] === 'paypal'): ?>
                            <?php 
                            $settings = json_decode($method['settings'], true) ?? [];
                            ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Client ID</label>
                                    <input type="text" name="paypal_client_id" value="<?php echo $settings['client_id'] ?? ''; ?>" placeholder="Client ID PayPal">
                                </div>
                                <div class="form-group">
                                    <label>Client Secret</label>
                                    <input type="text" name="paypal_client_secret" value="<?php echo $settings['client_secret'] ?? ''; ?>" placeholder="Client Secret PayPal">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Mode</label>
                                <select name="paypal_mode">
                                    <option value="sandbox" <?php echo ($settings['mode'] ?? '') == 'sandbox' ? 'selected' : ''; ?>>Sandbox (test)</option>
                                    <option value="live" <?php echo ($settings['mode'] ?? '') == 'live' ? 'selected' : ''; ?>>Live (production)</option>
                                </select>
                            </div>
                        <?php elseif($method['name'] === 'credit_card'): ?>
                            <?php 
                            $settings = json_decode($method['settings'], true) ?? [];
                            ?>
                            <div class="form-group">
                                <label>Clé API (optionnel)</label>
                                <input type="text" name="cc_api_key" value="<?php echo $settings['api_key'] ?? ''; ?>" placeholder="Clé API du processeur de paiement">
                            </div>
                        <?php endif; ?>
                        <button type="submit" name="update_settings" class="btn-primary">Enregistrer les paramètres</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>