<?php
// admin/payment-methods.php - Gestion des méthodes de paiement
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

// Mettre à jour les comptes Mobile Money
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mobile'])) {
    foreach ($_POST['accounts'] as $operator => $data) {
        $stmt = $conn->prepare("
            UPDATE mobile_money_accounts 
            SET phone_number = ?, account_name = ?, is_active = ? 
            WHERE operator = ?
        ");
        $stmt->execute([$data['phone'], $data['name'], $data['active'] ?? 0, $operator]);
    }
    setFlashMessage('success', 'Comptes Mobile Money mis à jour');
    header('Location: payment-methods.php');
    exit();
}

// Récupérer les données
$stmt = $conn->query("SELECT * FROM payment_methods ORDER BY sort_order");
$payment_methods = $stmt->fetchAll();

$stmt = $conn->query("SELECT * FROM mobile_money_accounts");
$mobile_accounts = $stmt->fetchAll();

// Transactions Mobile Money en attente
$stmt = $conn->prepare("
    SELECT mmt.*, o.order_number, u.username, u.email 
    FROM mobile_money_transactions mmt
    JOIN orders o ON mmt.order_id = o.id
    JOIN users u ON mmt.user_id = u.id
    WHERE mmt.status = 'pending'
    ORDER BY mmt.created_at DESC
");
$stmt->execute();
$pending_transactions = $stmt->fetchAll();
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
        }
        
        .btn-toggle.active {
            background: #10b981;
            color: white;
        }
        
        .btn-toggle.inactive {
            background: #ef4444;
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #2a2a35;
        }
        
        th {
            color: #a0a0b0;
            font-weight: 500;
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
        
        .btn-primary {
            background: #c14432;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.8rem;
            margin-right: 5px;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .pending-row {
            background: rgba(245, 158, 11, 0.1);
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
                <div class="method-info">
                    <div class="method-icon">
                        <i class="fab fa-<?php echo $method['logo']; ?>"></i>
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
                    <a href="?toggle=1&id=<?php echo $method['id']; ?>" class="btn-toggle <?php echo $method['is_active'] ? 'inactive' : 'active'; ?>" style="margin-left: 10px;">
                        <?php echo $method['is_active'] ? 'Désactiver' : 'Activer'; ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Comptes Mobile Money -->
    <div class="card">
        <h2><i class="fas fa-mobile-alt"></i> Comptes Mobile Money</h2>
        <form method="POST">
            <table>
                <thead>
                    <tr>
                        <th>Opérateur</th>
                        <th>Numéro de téléphone</th>
                        <th>Nom du compte</th>
                        <th>Actif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($mobile_accounts as $account): ?>
                    <tr>
                        <td>
                            <strong><?php echo $account['operator_name']; ?></strong>
                            <input type="hidden" name="accounts[<?php echo $account['operator']; ?>][operator]" value="<?php echo $account['operator']; ?>">
                        </td>
                        <td>
                            <input type="text" name="accounts[<?php echo $account['operator']; ?>][phone]" value="<?php echo $account['phone_number']; ?>" style="width: 200px;">
                        </td>
                        <td>
                            <input type="text" name="accounts[<?php echo $account['operator']; ?>][name]" value="<?php echo $account['account_name']; ?>" style="width: 200px;">
                        </td>
                        <td>
                            <input type="checkbox" name="accounts[<?php echo $account['operator']; ?>][active]" value="1" <?php echo $account['is_active'] ? 'checked' : ''; ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" name="update_mobile" class="btn-primary" style="margin-top: 20px;">Enregistrer les modifications</button>
        </form>
    </div>
    
    <!-- Transactions Mobile Money en attente -->
    <div class="card">
        <h2><i class="fas fa-clock"></i> Transactions Mobile Money en attente</h2>
        
        <?php if(count($pending_transactions) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Commande</th>
                    <th>Client</th>
                    <th>Opérateur</th>
                    <th>Montant</th>
                    <th>ID Transaction</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pending_transactions as $tx): ?>
                <tr class="pending-row">
                    <td><?php echo date('d/m/Y H:i', strtotime($tx['created_at'])); ?></td>
                    <td><?php echo $tx['order_number']; ?></td>
                    <td><?php echo $tx['username']; ?><br><small><?php echo $tx['email']; ?></small></td>
                    <td><?php echo $tx['operator_name']; ?></td>
                    <td><?php echo formatPrice($tx['amount']); ?></td>
                    <td><code><?php echo $tx['transaction_id']; ?></code></td>
                    <td>
                        <a href="verify-mobile-payment.php?id=<?php echo $tx['id']; ?>&action=verify" class="btn-success" onclick="return confirm('Valider cette transaction ?')">
                            <i class="fas fa-check"></i> Valider
                        </a>
                        <a href="verify-mobile-payment.php?id=<?php echo $tx['id']; ?>&action=reject" class="btn-danger" onclick="return confirm('Rejeter cette transaction ?')">
                            <i class="fas fa-times"></i> Rejeter
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Aucune transaction en attente.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>