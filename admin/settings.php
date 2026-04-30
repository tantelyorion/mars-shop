<?php
// admin/settings.php - Paramètres généraux
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();
$success = '';
$error = '';

// Sauvegarde des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_name = clean($_POST['shop_name']);
    $shop_email = clean($_POST['shop_email']);
    $shop_phone = clean($_POST['shop_phone']);
    $shop_address = clean($_POST['shop_address']);
    $shipping_cost = (float)$_POST['shipping_cost'];
    $free_shipping_min = (float)$_POST['free_shipping_min'];
    $tax_rate = (float)$_POST['tax_rate'];
    
    // Sauvegarde dans un fichier de configuration (ou en BDD)
    $config = [
        'shop_name' => $shop_name,
        'shop_email' => $shop_email,
        'shop_phone' => $shop_phone,
        'shop_address' => $shop_address,
        'shipping_cost' => $shipping_cost,
        'free_shipping_min' => $free_shipping_min,
        'tax_rate' => $tax_rate
    ];
    
    file_put_contents('../config/settings.json', json_encode($config, JSON_PRETTY_PRINT));
    $success = 'Paramètres enregistrés avec succès';
}

// Chargement des paramètres
$settings = [];
if (file_exists('../config/settings.json')) {
    $settings = json_decode(file_get_contents('../config/settings.json'), true);
}

// Paramètres par défaut
$default_settings = [
    'shop_name' => 'Mars Shop',
    'shop_email' => 'contact@marsshop.com',
    'shop_phone' => '+33 1 23 45 67 89',
    'shop_address' => '123 Rue de l\'Espace, 75001 Paris',
    'shipping_cost' => 5.99,
    'free_shipping_min' => 50,
    'tax_rate' => 10
];

$settings = array_merge($default_settings, $settings);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Administration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f14;
            color: #ffffff;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2a2a35;
        }
        
        .page-title h1 {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }
        
        .page-title p {
            color: #a0a0b0;
            font-size: 0.85rem;
        }
        
        .card {
            background: #1a1a24;
            border: 1px solid #2a2a35;
            border-radius: 16px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #2a2a35;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #a0a0b0;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #c14432;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #ef4444;
        }
        
        .btn-save {
            background: #c14432;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 20px;
        }
        
        .section-title {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #2a2a35;
        }
        
        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-header">
            <div class="page-title">
                <h1>Paramètres généraux</h1>
                <p>Configurez les informations de votre boutique</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <?php if($success): ?>
        <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-store"></i> Informations de la boutique</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom de la boutique</label>
                            <input type="text" name="shop_name" value="<?php echo clean($settings['shop_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email de contact</label>
                            <input type="email" name="shop_email" value="<?php echo clean($settings['shop_email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="shop_phone" value="<?php echo clean($settings['shop_phone']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Adresse</label>
                        <textarea name="shop_address" rows="3"><?php echo clean($settings['shop_address']); ?></textarea>
                    </div>
                    
                    <div class="section-title">
                        <h3><i class="fas fa-truck"></i> Livraison et taxes</h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Frais de livraison (€)</label>
                            <input type="number" step="0.01" name="shipping_cost" value="<?php echo $settings['shipping_cost']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Livraison gratuite à partir de (€)</label>
                            <input type="number" step="0.01" name="free_shipping_min" value="<?php echo $settings['free_shipping_min']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Taux de TVA (%)</label>
                        <input type="number" step="0.01" name="tax_rate" value="<?php echo $settings['tax_rate']; ?>">
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Enregistrer les paramètres
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
const toggle = document.getElementById('mobileToggle');
const sidebar = document.getElementById('adminSidebar');
if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
}
</script>
</body>
</html>