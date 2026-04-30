<?php
// admin/mobile-money-accounts.php - Gestion des comptes Mobile Money
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();

// Mise à jour des comptes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_accounts'])) {
    foreach ($_POST['accounts'] as $operator => $data) {
        $phone = clean($data['phone']);
        $account_name = clean($data['name']);
        $is_active = isset($data['active']) ? 1 : 0;
        
        $stmt = $conn->prepare("
            UPDATE mobile_money_accounts 
            SET phone_number = ?, account_name = ?, is_active = ? 
            WHERE operator = ?
        ");
        $stmt->execute([$phone, $account_name, $is_active, $operator]);
    }
    setFlashMessage('success', 'Comptes Mobile Money mis à jour');
    header('Location: mobile-money-accounts.php');
    exit();
}

// Récupérer les comptes
$stmt = $conn->query("SELECT * FROM mobile_money_accounts ORDER BY operator");
$accounts = $stmt->fetchAll();

$operators = [
    'airtel' => 'Airtel Money',
    'mvola' => 'Mvola',
    'orange' => 'Orange Money'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptes Mobile Money - Administration</title>
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
        
        .account-card {
            background: #2a2a35;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .account-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #3a3a45;
        }
        
        .account-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .account-icon i {
            font-size: 1.5rem;
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
            font-size: 0.8rem;
            color: #a0a0b0;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 16px;
            background: #1a1a24;
            border: 1px solid #3a3a45;
            border-radius: 8px;
            color: white;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
        }
        
        .btn-save {
            background: #c14432;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .status-active {
            background: #10b981;
        }
        
        .status-inactive {
            background: #ef4444;
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
                <h1>Comptes Mobile Money</h1>
                <p>Configurez les comptes de réception pour les paiements Mobile Money</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-mobile-alt"></i> Configuration des opérateurs</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php foreach($accounts as $account): ?>
                    <div class="account-card">
                        <div class="account-header">
                            <div class="account-icon">
                                <i class="fas fa-<?php echo $account['operator'] === 'airtel' ? 'tower-cell' : ($account['operator'] === 'mvola' ? 'mobile' : 'sim-card'); ?>"></i>
                            </div>
                            <div>
                                <h3><?php echo $operators[$account['operator']]; ?></h3>
                                <span class="status-badge <?php echo $account['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $account['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Numéro de téléphone du compte</label>
                                <input type="tel" name="accounts[<?php echo $account['operator']; ?>][phone]" value="<?php echo $account['phone_number']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Nom du compte</label>
                                <input type="text" name="accounts[<?php echo $account['operator']; ?>][name]" value="<?php echo $account['account_name']; ?>">
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="accounts[<?php echo $account['operator']; ?>][active]" id="active_<?php echo $account['operator']; ?>" value="1" <?php echo $account['is_active'] ? 'checked' : ''; ?>>
                            <label for="active_<?php echo $account['operator']; ?>">Compte actif</label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" name="update_accounts" class="btn-save">
                        <i class="fas fa-save"></i> Enregistrer les modifications
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